#!/usr/bin/env python3
"""Run Laravel or Playwright tests against a verified, disposable MySQL instance."""
import argparse
import base64
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import time
import uuid
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
RUNTIME = ROOT / '.test-verification'
MYSQLD = shutil.which('mysqld')
MYSQL = shutil.which('mysql')
PHP = shutil.which('php')


def mysql(port, sql):
    return subprocess.run([MYSQL, '--no-defaults', '--protocol=TCP', '-h127.0.0.1', f'-P{port}', '-uroot', '-N', '-B', '-e', sql],
                          capture_output=True, text=True)


def free_port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


def suites(group):
    if group == 'focused':
        return [('email', ['tests/Integration/Timebox2/UserEmailPolicyTest.php']),
                ('utility-seed', ['tests/Integration/Timebox3/UtilitySeederTest.php']),
                ('demo-data', ['tests/Integration/Timebox5/DemoDataIntegrityTest.php'])]
    if group == 'repeat':
        return [('repeat-seed', ['tests/Integration/Timebox5/DemoDataIntegrityTest.php', '--filter=repeated seeding'])]
    if group == 'seed-core':
        return [('utility-seed', ['tests/Integration/Timebox3/UtilitySeederTest.php']),
                ('repeat-seed', ['tests/Integration/Timebox5/DemoDataIntegrityTest.php', '--filter=repeated seeding'])]
    if group == 'seed':
        return [('utility-seed', ['tests/Integration/Timebox3/UtilitySeederTest.php']),
                ('demo-data', ['tests/Integration/Timebox5/DemoDataIntegrityTest.php'])]
    if group == 'unit-timeboxes':
        return [(f'Unit-Timebox{i}', [f'tests/Unit/Timebox{i}']) for i in range(1, 6)]
    if group == 'integration-timeboxes':
        return [(f'Integration-Timebox{i}', [f'tests/Integration/Timebox{i}']) for i in range(1, 6)]
    if group == 'integration':
        return [('Integration', ['--testsuite=Integration'])]
    if group == 'aggregate':
        return [('Unit', ['--testsuite=Unit']), ('Integration', ['--testsuite=Integration']), ('Full', [])]
    if group == 'all':
        result = [(f'Unit-Timebox{i}', [f'tests/Unit/Timebox{i}']) for i in range(1, 6)]
        result += [(f'Integration-Timebox{i}', [f'tests/Integration/Timebox{i}']) for i in range(1, 6)]
        result += [('Unit', ['--testsuite=Unit']), ('Integration', ['--testsuite=Integration']), ('Full', [])]
        return result
    return [(group, sys.argv[sys.argv.index('--') + 1:])]


def parse_xml(path):
    if not path.exists():
        return {'tests': 0, 'passed': 0, 'failures': 0, 'errors': 1, 'skipped': 0}
    tree = ET.parse(path)
    cases = tree.findall('.//testcase')
    failed = sum(case.find('failure') is not None for case in cases)
    errors = sum(case.find('error') is not None for case in cases)
    skipped = sum(case.find('skipped') is not None for case in cases)
    return {'tests': len(cases), 'passed': len(cases) - failed - errors - skipped,
            'failures': failed, 'errors': errors, 'skipped': skipped}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--group', choices=['focused', 'repeat', 'seed-core', 'seed', 'unit-timeboxes', 'integration-timeboxes', 'integration', 'aggregate', 'all'], default='focused')
    parser.add_argument('--system-suite', choices=['all', 'Timebox1', 'Timebox2', 'Timebox3', 'Timebox4', 'Timebox5', 'cross'])
    args, extra = parser.parse_known_args()
    if extra and not args.system_suite:
        parser.error('unrecognized arguments: ' + ' '.join(extra))
    frontend = ROOT.parent / 'frontend'
    selection = ('tests/system' if args.system_suite == 'all' else
                 'tests/system/Timebox4/cross-module.spec.js' if args.system_suite == 'cross' else
                 f'tests/system/{args.system_suite}/')
    system_command = [shutil.which('node') or 'node', str(frontend / 'node_modules/@playwright/test/cli.js'), 'test', selection, *extra]
    # Discovery must remain available without starting services or touching a database.
    if args.system_suite and '--list' in extra:
        raise SystemExit(subprocess.call(system_command, cwd=frontend))
    if not all([MYSQLD, MYSQL, PHP]):
        raise SystemExit('mysqld, mysql and php must be installed')
    RUNTIME.mkdir(exist_ok=True)
    run = RUNTIME / 'runs' / uuid.uuid4().hex
    run.mkdir(parents=True, exist_ok=False)
    data = run / 'mysql-data'
    data.mkdir()
    storage = run / 'storage'
    for suffix in ['framework/cache/data', 'framework/views', 'framework/sessions', 'logs', 'app/public', 'public/images']:
        (storage / suffix).mkdir(parents=True, exist_ok=True)
    cache = run / 'cache'
    cache.mkdir()
    tmp = run / 'tmp'
    tmp.mkdir()
    database = 'rosewood_system_test' if args.system_suite else 'rosewood_test'
    if args.system_suite:
        public = run / 'public'
        shutil.copytree(ROOT / 'public', public,
                        ignore=lambda directory, names: [name for name in names if (Path(directory) / name).is_symlink()])
        (public / 'storage').symlink_to(storage / 'app/public', target_is_directory=True)
    port = free_port()
    print(f'Isolated runtime: {run}; port: {port}', flush=True)
    init = subprocess.run([MYSQLD, '--no-defaults', '--initialize-insecure', f'--datadir={data}',
                           f'--log-error={run / "mysql-init.log"}'], capture_output=True, text=True)
    if init.returncode:
        raise SystemExit(f'MySQL initialization failed: {init.stderr[-2000:]}')
    log = (run / 'mysql.log').open('w')
    proc = subprocess.Popen([MYSQLD, '--no-defaults', f'--datadir={data}', f'--port={port}',
                             '--bind-address=127.0.0.1', f'--socket=/tmp/rr-test-{run.name[:12]}.sock',
                             f'--pid-file={run / "mysql.pid"}', '--mysqlx=OFF'],
                            stdout=log, stderr=subprocess.STDOUT)
    try:
        for _ in range(100):
            if proc.poll() is not None:
                raise RuntimeError(f'Isolated MySQL exited: {proc.returncode}')
            identity = mysql(port, 'SELECT @@port, @@datadir')
            if identity.returncode == 0:
                break
            time.sleep(.2)
        else:
            raise RuntimeError('Isolated MySQL did not start')
        actual_port, actual_data = identity.stdout.strip().split('\t')
        if int(actual_port) != port or Path(actual_data).resolve() != data.resolve():
            raise RuntimeError('MySQL identity mismatch; refusing to create test schema or run tests')
        created = mysql(port, f'CREATE DATABASE {database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
        if created.returncode:
            raise RuntimeError(f'Cannot create isolated schema: {created.stderr[-1000:]}')
        print(f'Verified fresh MySQL datadir and new {database} schema.', flush=True)
        env = os.environ.copy()
        env.update({'APP_ENV': 'testing', 'DB_CONNECTION': 'mysql', 'DB_HOST': '127.0.0.1',
                    'DB_PORT': str(port), 'DB_DATABASE': database, 'DB_USERNAME': 'root',
                    'DB_PASSWORD': '', 'DB_URL': '', 'DB_SOCKET': '', 'TEST_DATABASE_DISPOSABLE': '1',
                    'TEST_STORAGE_ROOT': str(storage), 'LARAVEL_STORAGE_PATH': str(storage),
                    'CACHE_STORE': 'array', 'SESSION_DRIVER': 'array', 'QUEUE_CONNECTION': 'sync',
                    'MAIL_MAILER': 'array', 'TMPDIR': str(tmp),
                    'APP_CONFIG_CACHE': str(cache / 'config.php'),
                    'APP_PACKAGES_CACHE': str(cache / 'packages.php'),
                    'APP_SERVICES_CACHE': str(cache / 'services.php')})
        summary = {}
        if args.system_suite:
            env.pop('NO_COLOR', None)
            env.update({'APP_ENV': 'local', 'APP_DEBUG': 'true',
                        'APP_KEY': 'base64:' + base64.b64encode(os.urandom(32)).decode(),
                        'APP_URL': 'http://127.0.0.1:8010', 'FRONTEND_URL': 'http://localhost:5174',
                        'PW_SYSTEM_TEST_RUNTIME': str(run), 'PW_SYSTEM_TEST_MYSQL_DATA': str(data),
                        'PW_SYSTEM_RESULTS_FILE': str(run / 'system-results.json'),
                        'VIEW_COMPILED_PATH': str(storage / 'framework/views'),
                        'PASSWORD_RESET_LOCAL_DELIVERY_TEST': 'false',
                        'CORS_ALLOWED_ORIGINS': 'http://localhost:5174',
                        'PW_FRONTEND_URL': 'http://localhost:5174', 'PW_BACKEND_URL': 'http://127.0.0.1:8010'})
            (run / 'database-identity.json').write_text(json.dumps({
                'database': database, 'port': port, 'datadir': str(data), 'storage': str(storage)}, indent=2))
            print(f'Running browser suite: {args.system_suite}', flush=True)
            started = time.monotonic()
            with (run / 'system-output.txt').open('w') as output:
                with subprocess.Popen(system_command, cwd=frontend, env=env, stdout=subprocess.PIPE,
                                      stderr=subprocess.STDOUT, text=True) as browser:
                    for line in browser.stdout:
                        print(line, end='', flush=True)
                        output.write(line)
                    code = browser.wait()
            report = run / 'system-results.json'
            stats = json.loads(report.read_text()).get('stats', {}) if report.exists() else {}
            counts = {'passed': stats.get('expected', 0), 'failed': stats.get('unexpected', 0),
                      'skipped': stats.get('skipped', 0), 'flaky': stats.get('flaky', 0),
                      'exit_code': code, 'seconds': round(time.monotonic() - started, 1)}
            counts['total'] = sum(counts[key] for key in ['passed', 'failed', 'skipped', 'flaky'])
            summary[args.system_suite] = counts
            (run / 'summary.json').write_text(json.dumps(summary, indent=2))
            print(f"System {args.system_suite}: {counts['total']} total | {counts['passed']} passed | "
                  f"{counts['failed']} failed | {counts['skipped']} skipped | {counts['flaky']} flaky", flush=True)
            if not report.exists() and code == 0:
                raise RuntimeError('Browser runner produced no JSON evidence')
        for label, selection in ([] if args.system_suite else suites(args.group)):
            report = run / f'{label}.xml'
            command = [PHP, '-d', f'sys_temp_dir={tmp}', 'artisan', 'test', *selection,
                       '--colors=never', f'--log-junit={report}']
            print(f'Running {label}: php artisan test {" ".join(selection)}', flush=True)
            started = time.monotonic()
            with (run / f'{label}.output.txt').open('w') as output:
                result = subprocess.run(command, cwd=ROOT, env=env, stdout=output, stderr=subprocess.STDOUT)
            counts = parse_xml(report)
            counts.update({'exit_code': result.returncode, 'seconds': round(time.monotonic() - started, 1)})
            summary[label] = counts
            print(f'{label}: {counts}', flush=True)
            (run / 'summary.json').write_text(json.dumps(summary, indent=2))
    finally:
        if proc.poll() is None:
            verified = mysql(port, 'SELECT @@port, @@datadir')
            if verified.returncode == 0:
                fields = verified.stdout.strip().split('\t')
                if len(fields) == 2 and int(fields[0]) == port and Path(fields[1]).resolve() == data.resolve():
                    mysql(port, 'SHUTDOWN')
            try:
                proc.wait(timeout=15)
            except subprocess.TimeoutExpired:
                proc.terminate()
                proc.wait(timeout=15)
        log.close()
        if proc.poll() is not None and data.parent == run:
            shutil.rmtree(data)
        print(f'Results retained at {run}', flush=True)
    if any(result['exit_code'] != 0 for result in summary.values()):
        raise SystemExit(1)


if __name__ == '__main__':
    main()
