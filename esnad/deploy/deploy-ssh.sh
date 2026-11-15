#!/usr/bin/env bash
# Deploys over SSH and Git where the host allows it (a VPS, or shared
# hosting with SSH). Run on the server inside the app folder.
#
# Usage: deploy/deploy-ssh.sh [branch]   (default: main)
set -euo pipefail
cd "$(dirname "$0")/.."
BRANCH="${1:-main}"

php artisan down --retry=30 || true
git fetch --prune origin "$BRANCH"
git reset --hard "origin/$BRANCH"

composer install --no-dev --optimize-autoloader --no-interaction
if command -v npm >/dev/null; then npm ci && npm run build; fi

php artisan migrate --force
php artisan db:seed --force          # seeders never overwrite admin edits
php artisan optimize
php artisan filament:optimize || true
php artisan queue:restart

if [ "$(grep -E '^APP_HOSTING_MODE=' .env | cut -d= -f2)" = "vps" ]; then
  # Supervisor keeps queue:work running in VPS mode.
  sudo supervisorctl restart esnad-worker:* || true
fi

php artisan up
echo "Deployed $(git rev-parse --short HEAD)."
