<?php
/**
 * Static site generator. Renders every page from app/templates + app/content
 * into plain .html files in the public folder, and bundles CSS/JS.
 *
 * Used by the CLI (php app/build.php) and by the admin panel after each save.
 */
declare(strict_types=1);
defined('BM_APP') || exit;

/** Every page to generate, including one page per published project/story. */
function bm_pages(): array
{
    $pages = bm_static_pages();
    foreach (bm_projects(true) as $p) {
        $pages[] = ['path' => $p['url'], 'tpl' => 'project', 'nav' => 'achievements', 'project' => $p['id']];
    }
    foreach (bm_stories(true) as $s) {
        $pages[] = ['path' => $s['url'], 'tpl' => 'story', 'nav' => 'stories', 'story' => $s['id']];
    }
    return $pages;
}

function bm_page_defaults(): array
{
    return [
        'path' => 'index.html',
        'tpl' => 'home',
        'nav' => '',
        'title' => '',
        'description' => '',
        'image' => '',
        'noindex' => false,
        'absolute' => false,
        'body_class' => '',
    ];
}

/** Render one page to an HTML string. */
function bm_render_page(array $page): string
{
    BM::$page = array_replace(bm_page_defaults(), $page);
    $tpl = BM_APP_DIR . '/templates/pages/' . basename((string) BM::$page['tpl']) . '.php';
    if (!is_file($tpl)) {
        throw new RuntimeException('Missing template: ' . BM::$page['tpl']);
    }
    $level = ob_get_level();
    try {
        ob_start();
        (static function (string $__tpl) {
            include $__tpl;
        })($tpl);
        $content = (string) ob_get_clean();

        ob_start();
        (static function (string $__layout, string $content) {
            include $__layout;
        })(BM_APP_DIR . '/templates/layout.php', $content);
        $html = (string) ob_get_clean();
    } catch (Throwable $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        throw new RuntimeException('Error rendering ' . BM::$page['path'] . ': ' . $e->getMessage(), 0, $e);
    }
    return $html;
}

/** Concatenate source files (sorted by name) into one bundle. */
function bm_bundle(string $srcDir, string $pattern, string $banner): string
{
    $files = glob($srcDir . '/' . $pattern) ?: [];
    sort($files, SORT_STRING);
    $out = $banner . "\n";
    foreach ($files as $f) {
        $out .= "\n/* ---- " . basename($f) . " ---- */\n" . file_get_contents($f) . "\n";
    }
    return $out;
}

/** Write only when content changed (keeps mtimes and caches stable). */
function bm_write_if_changed(string $file, string $contents): bool
{
    if (is_file($file) && file_get_contents($file) === $contents) {
        return false;
    }
    bm_write_file($file, $contents);
    return true;
}

function bm_sitemap(array $pages): string
{
    $base = absolute_url();
    if ($base === '') {
        return '';
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($pages as $p) {
        if (!empty($p['noindex'])) {
            continue;
        }
        $xml .= '  <url><loc>' . e(absolute_url($p['path'])) . '</loc></url>' . "\n";
    }
    return $xml . "</urlset>\n";
}

function bm_robots(): string
{
    $out = "User-agent: *\nDisallow: /admin/\nDisallow: /app/\n";
    $base = absolute_url();
    if ($base !== '') {
        $out .= "\nSitemap: " . absolute_url('sitemap.xml') . "\n";
    }
    return $out;
}

/**
 * Build the whole site.
 * $only: limit rendering to these template names (development aid; skips cleanup).
 * @return array{written:string[],removed:string[],warnings:string[],pages:int}
 */
function bm_build(array $only = []): array
{
    $lockFile = BM_STORAGE . '/build.lock';
    if (!is_dir(BM_STORAGE)) {
        @mkdir(BM_STORAGE, 0755, true);
    }
    $lock = @fopen($lockFile, 'c');
    if ($lock) {
        flock($lock, LOCK_EX);
    }
    try {
        bm_content_reset();
        BM::$missingTexts = [];
        $report = ['written' => [], 'removed' => [], 'warnings' => [], 'pages' => 0];

        // 1) Assets first, so page templates hash the fresh files.
        $css = bm_bundle(BM_APP_DIR . '/styles', '*.css', '/* Beyoot Misr — generated from app/styles by the build. Do not edit here. */');
        if (bm_write_if_changed(BM_PUBLIC . '/assets/css/site.css', $css)) {
            $report['written'][] = 'assets/css/site.css';
        }
        $js = bm_bundle(BM_APP_DIR . '/scripts', '*.js', '/* Beyoot Misr — generated from app/scripts by the build. Do not edit here. */');
        if (bm_write_if_changed(BM_PUBLIC . '/assets/js/site.js', $js)) {
            $report['written'][] = 'assets/js/site.js';
        }

        // 2) Render every page in memory; write only if all succeed.
        $pages = bm_pages();
        if ($only) {
            $pages = array_values(array_filter($pages, function ($p) use ($only) {
                return in_array($p['tpl'], $only, true);
            }));
        }
        $rendered = [];
        foreach ($pages as $page) {
            $rendered[$page['path']] = bm_render_page($page);
        }
        foreach ($rendered as $path => $html) {
            if (bm_write_if_changed(BM_PUBLIC . '/' . $path, $html)) {
                $report['written'][] = $path;
            }
        }
        $report['pages'] = count($rendered);

        if ($only) {
            foreach (array_keys(BM::$missingTexts) as $key) {
                $report['warnings'][] = 'Missing text key: ' . $key;
            }
            return $report;
        }

        // 3) Sitemap / robots.
        $sitemap = bm_sitemap($pages);
        if ($sitemap !== '') {
            if (bm_write_if_changed(BM_PUBLIC . '/sitemap.xml', $sitemap)) {
                $report['written'][] = 'sitemap.xml';
            }
        } elseif (is_file(BM_PUBLIC . '/sitemap.xml')) {
            @unlink(BM_PUBLIC . '/sitemap.xml');
            $report['removed'][] = 'sitemap.xml';
        }
        if (bm_write_if_changed(BM_PUBLIC . '/robots.txt', bm_robots())) {
            $report['written'][] = 'robots.txt';
        }

        // 4) Remove pages of projects/stories that were deleted or unpublished.
        $manifestFile = BM_STORAGE . '/build-manifest.json';
        $previous = (array) bm_json_read($manifestFile, []);
        foreach ($previous as $old) {
            if (!is_string($old) || isset($rendered[$old])) {
                continue;
            }
            if (preg_match('#^(projects|stories)/[a-z0-9-]+\.html$#', $old) && is_file(BM_PUBLIC . '/' . $old)) {
                @unlink(BM_PUBLIC . '/' . $old);
                $report['removed'][] = $old;
            }
        }
        bm_json_write($manifestFile, array_keys($rendered));

        foreach (array_keys(BM::$missingTexts) as $key) {
            $report['warnings'][] = 'Missing text key: ' . $key;
        }
        return $report;
    } finally {
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
