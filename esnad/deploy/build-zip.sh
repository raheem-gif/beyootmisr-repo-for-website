#!/usr/bin/env bash
# Builds a zip for cPanel shared hosting without SSH (section 3.3).
#
# Layout on the server:
#   /home/USER/esnad/        everything except public/  (above the web root)
#   /home/USER/public_html/  the contents of public/
# public_html/index.php is patched to point at ../esnad.
#
# Usage: deploy/build-zip.sh [app-folder-name]   (default: esnad)
set -euo pipefail
cd "$(dirname "$0")/.."
APP_DIR="${1:-esnad}"
OUT="dist"
STAGE="$OUT/stage"

rm -rf "$STAGE" && mkdir -p "$STAGE/$APP_DIR" "$STAGE/public_html"

composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction
npm ci
npm run build
php artisan esnad:copy-check

rsync -a --exclude-from=- ./ "$STAGE/$APP_DIR/" <<'EXCL'
.git
.github
node_modules
tests
dist
public
storage/logs/*
storage/framework/cache/data/*
storage/framework/sessions/*
storage/framework/views/*
database/database.sqlite
.env
.phpunit.result.cache
vendor/**/.git
EXCL
rsync -a --exclude 'hot' --exclude 'storage' public/ "$STAGE/public_html/"

# Point the front controller at the app folder above the web root.
sed -i "s#__DIR__.'/../#__DIR__.'/../$APP_DIR/#g" "$STAGE/public_html/index.php"

# Laravel must know where the public folder is.
cat > "$STAGE/$APP_DIR/bootstrap/public-path.php" <<PHP
<?php
// Written by deploy/build-zip.sh for the split shared-hosting layout.
return realpath(__DIR__.'/../../public_html') ?: __DIR__.'/../public';
PHP

STAMP="$(date +%Y%m%d-%H%M)"
(cd "$STAGE" && zip -qr "../esnad-shared-$STAMP.zip" .)
rm -rf "$STAGE"
echo "Built $OUT/esnad-shared-$STAMP.zip"
echo "Next: upload, extract in the home folder, create .env in $APP_DIR/, then open /install/migrate?token=... once and /install/check?token=..."

# Restore dev dependencies for local work.
composer install --no-interaction >/dev/null
