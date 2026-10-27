#!/usr/bin/env python3
"""Run Laravel tests only against a newly initialized, disposable MySQL instance."""
import argparse
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
    return subprocess.run([MYSQL, '--protocol=TCP', '-h127.0.0.1', f'-P{port}', '-uroot', '-N', '-B', '-e', sql],
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
    args = parser.parse_args()
    if not all([MYSQLD, MYSQL, PHP]):
        raise SystemExit('mysqld, mysql and php must be installed')
    if not RUNTIME.is_dir():
        raise SystemExit('Expected the backend .test-verification directory')
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
        created = mysql(port, 'CREATE DATABASE rosewood_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
        if created.returncode:
            raise RuntimeError(f'Cannot create isolated schema: {created.stderr[-1000:]}')
        print('Verified fresh MySQL datadir and new rosewood_test schema.', flush=True)
        env = os.environ.copy()
        env.update({'APP_ENV': 'testing', 'DB_CONNECTION': 'mysql', 'DB_HOST': '127.0.0.1',
                    'DB_PORT': str(port), 'DB_DATABASE': 'rosewood_test', 'DB_USERNAME': 'root',
                    'DB_PASSWORD': '', 'DB_URL': '', 'DB_SOCKET': '', 'TEST_DATABASE_DISPOSABLE': '1',
                    'TEST_STORAGE_ROOT': str(storage), 'LARAVEL_STORAGE_PATH': str(storage),
                    'CACHE_STORE': 'array', 'SESSION_DRIVER': 'array', 'QUEUE_CONNECTION': 'sync',
                    'MAIL_MAILER': 'array', 'TMPDIR': str(tmp),
                    'APP_CONFIG_CACHE': str(cache / 'config.php'),
                    'APP_PACKAGES_CACHE': str(cache / 'packages.php'),
                    'APP_SERVICES_CACHE': str(cache / 'services.php')})
        summary = {}
        for label, selection in suites(args.group):
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
