#!/usr/bin/env bash
# Admin panel tests: fresh sandbox copy of public/ → PHP 7.4 lint → unit tests → HTTP flow tests
# (stage 1: auth, texts, settings, users, backups; stage 2: content sections).
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
find "$SITE/app/storage" -mindepth 1 ! -name index.html ! -name build-manifest.json -exec rm -rf {} + 2>/dev/null || true
find "$SITE/uploads/images" "$SITE/uploads/reports" -mindepth 1 ! -name index.html -delete 2>/dev/null || true

echo "== PHP 7.4 compatibility"
php "$REPO/tests/admin/php74-lint.php"
for f in $(find "$REPO/public/admin" "$REPO/public/app/admin" -name '*.php'); do php -l "$f" > /dev/null; done

echo "== Unit tests"
php "$REPO/tests/admin/unit-test.php" "$SITE"
php "$REPO/tests/admin/unit-content-test.php" "$SITE"

echo "== HTTP tests (php -S 127.0.0.1:$PORT)"
php -S "127.0.0.1:$PORT" -t "$SITE" > "$SANDBOX/server.log" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
for _ in $(seq 1 50); do
  if curl -s -o /dev/null "http://127.0.0.1:$PORT/robots.txt"; then break; fi
  sleep 0.1
done
php "$REPO/tests/admin/http-test.php" "http://127.0.0.1:$PORT" "$SITE"

echo "== HTTP tests: content sections (stats, projects, stories, reports, galleries)"
php "$REPO/tests/admin/http-content-test.php" "http://127.0.0.1:$PORT" "$SITE"
