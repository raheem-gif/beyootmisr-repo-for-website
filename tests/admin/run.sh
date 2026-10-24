#!/usr/bin/env bash
# Admin panel tests: fresh sandbox copy of public/ → PHP 7.4 lint → unit tests → HTTP flow tests
# (stage 1: auth, texts, settings, users, backups; stage 2: content sections; then the same server
# restarted with the functions restrictive shared hosts disable).
# Usage: tests/admin/run.sh [sandbox-dir] [port]
# Never touches the real site: everything runs on a disposable copy.
set -euo pipefail
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
SANDBOX="${1:-${TMPDIR:-/tmp}/bm-admin-sandbox}"
PORT="${2:-8095}"
SITE="$SANDBOX/site"

rm -rf "$SANDBOX"
mkdir -p "$SANDBOX"
cp -r "$REPO/public" "$SITE"
# Start from a clean runtime state (no users, logs, sessions, uploads).
find "$SITE/app/storage" -mindepth 1 ! -name index.html -exec rm -rf {} + 2>/dev/null || true
find "$SITE/uploads/images" "$SITE/uploads/reports" -mindepth 1 ! -name index.html -delete 2>/dev/null || true

echo "== PHP 7.4 compatibility"
php "$REPO/tests/admin/php74-lint.php"
for f in $(find "$REPO/public/admin" "$REPO/public/app/admin" -name '*.php'); do php -l "$f" > /dev/null; done

echo "== Unit tests"
php "$REPO/tests/admin/unit-test.php" "$SITE"
php "$REPO/tests/admin/unit-content-test.php" "$SITE"

SERVER=
trap 'kill $SERVER 2>/dev/null || true' EXIT
# start_server [php -d options…]: (re)start php -S on $PORT and wait until it answers.
start_server() {
  if [ -n "$SERVER" ]; then kill "$SERVER" 2>/dev/null || true; wait "$SERVER" 2>/dev/null || true; fi
  php "$@" -S "127.0.0.1:$PORT" -t "$SITE" >> "$SANDBOX/server.log" 2>&1 &
  SERVER=$!
  for _ in $(seq 1 50); do
    if curl -s -o /dev/null "http://127.0.0.1:$PORT/robots.txt"; then return 0; fi
    sleep 0.1
  done
}

echo "== HTTP tests (php -S 127.0.0.1:$PORT)"
start_server
php "$REPO/tests/admin/http-test.php" "http://127.0.0.1:$PORT" "$SITE"

echo "== HTTP tests: content sections (stats, projects, stories, reports, galleries)"
php "$REPO/tests/admin/http-content-test.php" "http://127.0.0.1:$PORT" "$SITE"

echo "== HTTP tests: restrictive host (functions shared hosts often disable)"
start_server -d disable_functions=set_time_limit,ignore_user_abort,ini_set,chmod,exec,shell_exec,system,passthru,proc_open,popen,putenv,symlink,php_uname
php "$REPO/tests/admin/http-host-limits-test.php" "http://127.0.0.1:$PORT" "$SITE"
