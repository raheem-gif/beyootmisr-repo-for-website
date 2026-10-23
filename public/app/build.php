<?php
/**
 * Command-line build: php public/app/build.php [--strict] [--only=home,about]
 * Regenerates every static page from app/templates and app/content.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

$strict = in_array('--strict', $argv, true);
$only = [];
foreach ($argv as $arg) {
    if (strpos($arg, '--only=') === 0) {
        $only = array_filter(explode(',', substr($arg, 7)));
    }
}
$start = microtime(true);

try {
    $report = bm_build($only);
} catch (Throwable $e) {
    fwrite(STDERR, 'Build failed: ' . $e->getMessage() . "\n");
    if ($e->getPrevious()) {
        fwrite(STDERR, '  ' . get_class($e->getPrevious()) . ' in ' . $e->getPrevious()->getFile() . ':' . $e->getPrevious()->getLine() . "\n");
    }
    exit(1);
}

printf("Built %d pages in %.2fs\n", $report['pages'], microtime(true) - $start);
foreach ($report['written'] as $f) {
    echo "  wrote   $f\n";
}
foreach ($report['removed'] as $f) {
    echo "  removed $f\n";
}
foreach ($report['warnings'] as $w) {
    fwrite(STDERR, "  warning: $w\n");
}
exit($strict && $report['warnings'] ? 2 : 0);
