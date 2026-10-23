<?php
/**
 * Content access with defaults, so templates and the admin panel always see
 * complete records even when a JSON file is missing fields.
 */
declare(strict_types=1);
defined('BM_APP') || exit;

function bm_settings_defaults(): array
{
    return [
        'site_url' => '',
        'logo' => '',
        'contact' => [
            'hotline' => '',
            'whatsapp_general' => '',
            'whatsapp_legal' => '',
            'address' => '',
            'address_detail' => '',
            'email' => '',
            'hours' => '',
            'map_url' => '',
        ],
        'social' => [
            'facebook' => '',
            'instagram' => '',
        ],
        'legal' => [
            'registration_number' => '',
            'registration_year' => '',
            'registration_authority' => '',
            'public_benefit' => '',
            'fundraising_permit' => '',
        ],
        'donations' => [
            'enabled' => false,
            'methods' => [],
            'allocation_note' => '',
        ],
    ];
}

function bm_settings(): array
{
    return array_replace_recursive(bm_settings_defaults(), (array) bm_content('settings'));
}

/* ------------------------------------------------------------------------ */
/* Images                                                                    */
/* ------------------------------------------------------------------------ */

function bm_image_defaults(): array
{
    return ['src' => '', 'thumb' => '', 'w' => 0, 'h' => 0, 'alt' => '', 'caption' => ''];
}

function bm_images($list): array
{
    $out = [];
    foreach ((array) $list as $img) {
        if (is_string($img)) {
            $img = ['src' => $img];
        }
        if (!is_array($img) || empty($img['src'])) {
            continue;
        }
        $img = array_replace(bm_image_defaults(), $img);
        if ($img['thumb'] === '') {
            $img['thumb'] = $img['src'];
        }
        $out[] = $img;
    }
    return $out;
}

/* ------------------------------------------------------------------------ */
/* Activities                                                                */
/* ------------------------------------------------------------------------ */

function bm_activity(string $slug): array
{
    $reg = bm_activity_registry();
    if (!isset($reg[$slug])) {
        return [];
    }
    $texts = t('activities.items.' . $slug);
    $texts = is_array($texts) ? $texts : [];
    return array_replace([
        'slug' => $slug,
        'num' => $reg[$slug]['num'],
        'icon' => $reg[$slug]['icon'],
        'name' => '',
        'subtitle' => '',
        'description' => '',
        'tagline' => '',
        'assistance_title' => '',
        'assistance' => [],
        'url' => 'activities/' . $slug . '.html',
    ], $texts, ['slug' => $slug, 'num' => $reg[$slug]['num'], 'icon' => $reg[$slug]['icon'], 'url' => 'activities/' . $slug . '.html']);
}

function bm_activities(): array
{
    $out = [];
    foreach (array_keys(bm_activity_registry()) as $slug) {
        $out[$slug] = bm_activity($slug);
    }
    return $out;
}

function bm_gallery(string $slug): array
{
    $all = (array) bm_content('galleries');
    return bm_images($all[$slug] ?? []);
}

/* ------------------------------------------------------------------------ */
/* Stats (أثرنا بالأرقام)                                                    */
/* ------------------------------------------------------------------------ */

function bm_stats(): array
{
    $raw = (array) bm_content('stats');
    $items = [];
    foreach ((array) ($raw['items'] ?? []) as $item) {
        if (!is_array($item) || empty($item['id'])) {
            continue;
        }
        $items[$item['id']] = array_replace([
            'id' => '',
            'label' => '',
            'value' => null,
            'suffix' => '',
            'icon' => 'badge-check',
            'activity' => '',
            'home' => false,
            'achievements' => true,
        ], $item);
    }
    return [
        'updated_at' => (string) ($raw['updated_at'] ?? ''),
        'period' => (string) ($raw['period'] ?? ''),
        'items' => $items,
    ];
}

/**
 * A number is shown only when it has a value AND the statistics carry a
 * review date ("إحصاءات مُراجعة ومؤرخة"). Otherwise the card says it will be
 * announced after verification.
 */
function bm_stat_published(array $item, ?array $stats = null): bool
{
    $stats = $stats ?? bm_stats();
    $v = $item['value'] ?? null;
    return $v !== null && trim((string) $v) !== '' && trim($stats['updated_at']) !== '';
}

/** Stats flagged for a given place: 'home' | 'achievements' | activity slug. */
function bm_stats_for(string $where): array
{
    $stats = bm_stats();
    $out = [];
    foreach ($stats['items'] as $item) {
        if (($where === 'home' && !empty($item['home']))
            || ($where === 'achievements' && !empty($item['achievements']))
            || ($where !== 'home' && $where !== 'achievements' && $item['activity'] === $where)) {
            $out[] = $item;
        }
    }
    return $out;
}

/* ------------------------------------------------------------------------ */
/* Projects                                                                  */
/* ------------------------------------------------------------------------ */

function bm_project_defaults(): array
{
    return [
        'id' => '',
        'title' => '',
        'activity' => '',
        'location' => '',
        'goal' => '',
        'status' => 'ongoing',
        'summary' => '',
        'description' => '',
        'images' => [],
        'updates' => [],
        'updated_at' => '',
        'cost' => '',
        'donations' => '',
        'show_financials' => false,
        'published' => false,
        'created_at' => '',
    ];
}

function bm_normalize_project(array $p): array
{
    $p = array_replace(bm_project_defaults(), $p);
    $p['id'] = slugify((string) $p['id']);
    if (!in_array($p['status'], bm_project_statuses(), true)) {
        $p['status'] = 'ongoing';
    }
    $p['images'] = bm_images($p['images']);
    $updates = [];
    foreach ((array) $p['updates'] as $u) {
        if (!is_array($u)) {
            continue;
        }
        $u = array_replace(['date' => '', 'title' => '', 'text' => '', 'images' => []], $u);
        $u['images'] = bm_images($u['images']);
        $updates[] = $u;
    }
    // Newest stage first.
    usort($updates, function ($a, $b) {
        return strcmp((string) $b['date'], (string) $a['date']);
    });
    $p['updates'] = $updates;
    $p['url'] = 'projects/' . $p['id'] . '.html';
    return $p;
}

function bm_projects(bool $publishedOnly = true, string $activity = ''): array
{
    $out = [];
    foreach ((array) bm_content('projects') as $p) {
        if (!is_array($p) || empty($p['id'])) {
            continue;
        }
        $p = bm_normalize_project($p);
        if ($publishedOnly && !$p['published']) {
            continue;
        }
        if ($activity !== '' && $p['activity'] !== $activity) {
            continue;
        }
        $out[] = $p;
    }
    // Ongoing first, then most recently updated.
    usort($out, function ($a, $b) {
        if ($a['status'] !== $b['status']) {
            return $a['status'] === 'ongoing' ? -1 : 1;
        }
        return strcmp((string) $b['updated_at'], (string) $a['updated_at']);
    });
    return $out;
}

/** Cover image: first project image, else the newest update image. */
function bm_project_cover(array $p): ?array
{
    if (!empty($p['images'])) {
        return $p['images'][0];
    }
    foreach ($p['updates'] as $u) {
        if (!empty($u['images'])) {
            return $u['images'][0];
        }
    }
    return null;
}

/** All images of a project: main gallery then stages. */
function bm_project_all_images(array $p): array
{
    $all = $p['images'];
    foreach ($p['updates'] as $u) {
        foreach ($u['images'] as $img) {
            $all[] = $img;
        }
    }
    return $all;
}

/* ------------------------------------------------------------------------ */
/* Stories                                                                   */
/* ------------------------------------------------------------------------ */

function bm_story_defaults(): array
{
    return [
        'id' => '',
        'type' => 'treatment',
        'title' => '',
        'summary' => '',
        'body' => '',
        'activity' => '',
        'date' => '',
        'images' => [],
        'published' => false,
    ];
}

function bm_normalize_story(array $s): array
{
    $s = array_replace(bm_story_defaults(), $s);
    $s['id'] = slugify((string) $s['id']);
    if (!isset(bm_story_types()[$s['type']])) {
        $s['type'] = 'treatment';
    }
    $s['images'] = bm_images($s['images']);
    $s['url'] = 'stories/' . $s['id'] . '.html';
    return $s;
}

function bm_stories(bool $publishedOnly = true): array
{
    $out = [];
    foreach ((array) bm_content('stories') as $s) {
        if (!is_array($s) || empty($s['id'])) {
            continue;
        }
        $s = bm_normalize_story($s);
        if ($publishedOnly && !$s['published']) {
            continue;
        }
        $out[] = $s;
    }
    usort($out, function ($a, $b) {
        return strcmp((string) $b['date'], (string) $a['date']);
    });
    return $out;
}

/* ------------------------------------------------------------------------ */
/* Reports                                                                   */
/* ------------------------------------------------------------------------ */

function bm_reports(bool $publishedOnly = true): array
{
    $out = [];
    foreach ((array) bm_content('reports') as $r) {
        if (!is_array($r) || empty($r['id']) || empty($r['file'])) {
            continue;
        }
        $r = array_replace(['id' => '', 'title' => '', 'year' => '', 'description' => '', 'file' => '', 'size' => 0, 'published' => false], $r);
        if ($publishedOnly && !$r['published']) {
            continue;
        }
        $out[] = $r;
    }
    usort($out, function ($a, $b) {
        return strcmp((string) $b['year'], (string) $a['year']);
    });
    return $out;
}
