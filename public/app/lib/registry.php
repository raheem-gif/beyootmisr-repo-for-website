<?php
/**
 * Fixed site structure: activities, story types, project statuses.
 * Words live in content/texts.json (editable); slugs and icons live here so
 * editing a text can never break a URL.
 */
declare(strict_types=1);
defined('BM_APP') || exit;

/** The ten activities, in the order of the content document (section 05). */
function bm_activity_registry(): array
{
    return [
        'salama'           => ['num' => '01', 'icon' => 'heart-pulse'],
        'janna-wa-masajid' => ['num' => '02', 'icon' => 'mosque'],
        'al-gharimin'      => ['num' => '03', 'icon' => 'link-2-off'],
        'afrah'            => ['num' => '04', 'icon' => 'gem'],
        'maa-wa-namaa'     => ['num' => '05', 'icon' => 'droplets'],
        'najah'            => ['num' => '06', 'icon' => 'graduation-cap'],
        'al-maida'         => ['num' => '07', 'icon' => 'soup'],
        'kiswa'            => ['num' => '08', 'icon' => 'shirt'],
        'ziyada'           => ['num' => '09', 'icon' => 'sprout'],
        'mazad-al-khair'   => ['num' => '10', 'icon' => 'gavel'],
    ];
}

/** Story templates from section 07. */
function bm_story_types(): array
{
    return [
        'treatment'  => ['icon' => 'heart-pulse'],
        'livelihood' => ['icon' => 'sprout'],
        'roof'       => ['icon' => 'house'],
        'success'    => ['icon' => 'graduation-cap'],
        'mosque'     => ['icon' => 'mosque'],
    ];
}

/** Project status values (section 08: جارٍ / منجز). */
function bm_project_statuses(): array
{
    return ['ongoing', 'done'];
}

/**
 * Every page the build generates. Data-driven pages (projects, stories) are
 * appended by bm_pages() in build.php.
 */
function bm_static_pages(): array
{
    $pages = [
        ['path' => 'index.html', 'tpl' => 'home', 'nav' => 'home'],
        ['path' => 'about.html', 'tpl' => 'about', 'nav' => 'about'],
        ['path' => 'charter.html', 'tpl' => 'charter', 'nav' => 'about'],
        ['path' => 'activities.html', 'tpl' => 'activities', 'nav' => 'activities'],
    ];
    foreach (array_keys(bm_activity_registry()) as $slug) {
        $pages[] = ['path' => 'activities/' . $slug . '.html', 'tpl' => 'activity', 'nav' => 'activities', 'activity' => $slug];
    }
    return array_merge($pages, [
        ['path' => 'ehna-maaki.html', 'tpl' => 'ehna-maaki', 'nav' => 'activities'],
        ['path' => 'stories.html', 'tpl' => 'stories', 'nav' => 'stories'],
        ['path' => 'achievements.html', 'tpl' => 'achievements', 'nav' => 'achievements'],
        ['path' => 'get-involved.html', 'tpl' => 'get-involved', 'nav' => 'get_involved'],
        ['path' => 'contact.html', 'tpl' => 'contact', 'nav' => 'contact'],
        ['path' => 'privacy.html', 'tpl' => 'privacy', 'nav' => ''],
        ['path' => '404.html', 'tpl' => '404', 'nav' => '', 'absolute' => true, 'noindex' => true],
    ]);
}
