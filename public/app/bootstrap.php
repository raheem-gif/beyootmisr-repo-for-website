<?php
/**
 * Beyoot Misr site — shared bootstrap.
 *
 * Loaded by the CLI build (app/build.php) and by the admin panel (admin/).
 * Public pages are plain static HTML generated from app/templates + app/content,
 * so visitors never execute PHP.
 */
declare(strict_types=1);

if (!defined('BM_APP')) {
    define('BM_APP', true);
}

define('BM_VERSION', '1.0.0');
define('BM_APP_DIR', __DIR__);
define('BM_PUBLIC', dirname(__DIR__));
define('BM_CONTENT', BM_APP_DIR . '/content');
define('BM_STORAGE', BM_APP_DIR . '/storage');

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
date_default_timezone_set('Africa/Cairo');

require BM_APP_DIR . '/lib/core.php';
require BM_APP_DIR . '/lib/registry.php';
require BM_APP_DIR . '/lib/data.php';
require BM_APP_DIR . '/lib/illustrations.php';
require BM_APP_DIR . '/lib/components.php';
require BM_APP_DIR . '/lib/build.php';
