#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "Starting system-test backend on :8010 using DB=rosewood_system_test"

php -r '
$app = require "scripts/system_test_bootstrap.php";
$info = system_test_assert_safe_database($app);
fwrite(STDOUT, "guard_ok database={$info["database"]} host={$info["host"]}\n");
'

exec php -S 127.0.0.1:8010 "$ROOT/scripts/system_test_router.php" >> "$PW_SYSTEM_TEST_RUNTIME/backend-server.log" 2>&1
