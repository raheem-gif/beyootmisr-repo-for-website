<?php
/**
 * Brand illustrations as inline SVG (no stock photography, per the content
 * document §12). Colours are the brand blue/amber and their tints.
 */
declare(strict_types=1);
defined('BM_APP') || exit;

/**
 * Hero illustration: a row of Egyptian homes — flat roofs, a water tank, a
 * mosque, a palm — whose windows light up one by one. Each lit window is a
 * home with a story: «حكاية من مليون حكاية».
 */
function skyline_svg(): string
{
    // [x, y, w, h, lit?, arch?]
    $windows = [
        // House 1 (tall, left)
        [58, 206, 22, 30, 1, 1], [100, 206, 22, 30, 0, 1],
        [58, 256, 22, 30, 0, 1], [100, 256, 22, 30, 1, 1],
        [58, 306, 22, 30, 1, 1],
        // House 2 (pitched roof)
        [160, 276, 20, 26, 1, 0], [204, 276, 20, 26, 0, 0],
        // Mosque arches
        [262, 316, 16, 30, 1, 1], [290, 316, 16, 30, 1, 1], [318, 316, 16, 30, 0, 1],
        // House 4 (pale)
        [388, 238, 22, 28, 0, 0], [428, 238, 22, 28, 1, 0],
        [388, 282, 22, 28, 1, 0], [428, 282, 22, 28, 0, 0],
        [388, 326, 22, 28, 0, 0],
        // House 5 (water tank)
        [494, 296, 20, 26, 1, 1], [530, 296, 20, 26, 0, 1],
    ];
    $win = '';
    $delay = 0.0;
    foreach ($windows as $i => [$x, $y, $w, $h, $lit, $arch]) {
        $cls = $lit ? 'lit' : 'dim';
        $style = $lit ? ' style="--d:' . number_format(0.35 + $delay, 2, '.', '') . 's"' : '';
        if ($lit) {
            $delay += 0.28;
        }
        if ($arch) {
            $r = $w / 2;
            $win .= '<path class="' . $cls . '"' . $style . ' d="M' . $x . ' ' . ($y + $h) . 'V' . ($y + $r) . 'a' . $r . ' ' . $r . ' 0 0 1 ' . $w . ' 0V' . ($y + $h) . 'z"/>';
        } else {
            $win .= '<rect class="' . $cls . '"' . $style . ' x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="3"/>';
        }
    }

    return '<svg class="skyline" viewBox="0 0 600 440" overflow="visible" role="img" aria-labelledby="skyline-title" focusable="false">'
        . '<title id="skyline-title">' . te('site.slogan') . '</title>'
        // sun of hope
        . '<circle cx="430" cy="138" r="98" fill="#FDEBC8"/>'
        . '<circle cx="430" cy="138" r="62" fill="#F8D48A" opacity=".55"/>'
        // birds
        . '<path d="M318 92q8-8 16 0q8-8 16 0" fill="none" stroke="#5BA8D6" stroke-width="3" stroke-linecap="round"/>'
        . '<path d="M360 64q6-6 12 0q6-6 12 0" fill="none" stroke="#9FCBE8" stroke-width="3" stroke-linecap="round"/>'
        // ground
        . '<path d="M-1400 392h3400v60h-3400z" fill="#DCEDF8"/>'
        . '<path d="M-1400 392c600-6 1200-10 1700-6s900 8 1700 0v12h-3400z" fill="#B9DBF0"/>'
        // palm tree (behind house 2)
        . '<path d="M168 392c4-60 8-130 16-196" fill="none" stroke="#1572A6" stroke-width="8" stroke-linecap="round"/>'
        . '<g fill="none" stroke="#1984C0" stroke-width="7" stroke-linecap="round">'
        . '<path d="M184 196c-20-14-42-14-58-2"/><path d="M184 196c-8-22-26-34-46-34"/>'
        . '<path d="M184 196c14-18 36-22 56-12"/><path d="M184 196c22-6 42 4 52 20"/><path d="M184 196c2-22 14-38 32-44"/></g>'
        // house 1 — tall, flat roof with parapet
        . '<rect x="36" y="182" width="110" height="210" rx="4" fill="#1984C0"/>'
        . '<path d="M32 176h118v12H32z" fill="#1572A6"/><path d="M44 166h22v10H44zM116 166h22v10h-22z" fill="#1572A6"/>'
        . '<path d="M96 392v-34a12 12 0 0 1 24 0v34z" fill="#0F5A85"/>'
        // house 2 — pitched roof (the classic "home")
        . '<path d="M140 254l49-44 49 44z" fill="#1572A6"/>'
        . '<rect x="146" y="252" width="86" height="140" fill="#5BA8D6"/>'
        . '<circle class="lit" style="--d:.2s" cx="189" cy="238" r="8"/>'
        . '<path d="M176 392v-30a13 13 0 0 1 26 0v30z" fill="#EFA927"/>'
        // mosque — dome and minaret
        . '<path d="M262 292c0-44 72-44 72 0z" fill="#1984C0"/>'
        . '<path d="M298 248v-16" stroke="#1572A6" stroke-width="3" stroke-linecap="round"/><circle cx="298" cy="228" r="5" fill="#EFA927"/>'
        . '<rect x="246" y="290" width="104" height="102" rx="3" fill="#1572A6"/>'
        . '<rect x="352" y="204" width="16" height="188" fill="#1984C0"/>'
        . '<path d="M348 240h24v7h-24z" fill="#0F5A85"/><path d="M352 204l8-18 8 18z" fill="#0F5A85"/>'
        // house 4 — pale, parapet notches
        . '<rect x="372" y="222" width="96" height="170" rx="3" fill="#9FCBE8"/>'
        . '<path d="M368 214h104v10H368z" fill="#5BA8D6"/>'
        . '<path d="M424 392v-30h26v30z" fill="#5BA8D6"/>'
        // house 5 — rooftop water tank
        . '<rect x="478" y="276" width="88" height="116" rx="3" fill="#1984C0"/>'
        . '<path d="M474 270h96v10h-96z" fill="#1572A6"/>'
        . '<rect x="506" y="238" width="34" height="24" rx="5" fill="#1572A6"/><path d="M510 262v8M536 262v8" stroke="#0F5A85" stroke-width="3"/>'
        . '<path d="M512 392v-26a10 10 0 0 1 20 0v26z" fill="#0F5A85"/>'
        . '<g class="windows">' . $win . '</g>'
        . '</svg>';
}

/** Decorative grid of arched windows (page hero backgrounds, empty media). */
function hero_windows_svg(string $class = 'windows-art'): string
{
    $out = '<svg class="' . e($class) . '" viewBox="0 0 360 200" aria-hidden="true" focusable="false" preserveAspectRatio="xMidYMid slice">';
    $lit = [2, 7, 9, 16, 20];
    $n = 0;
    for ($row = 0; $row < 3; $row++) {
        for ($col = 0; $col < 8; $col++) {
            $x = 14 + $col * 44 + ($row % 2) * 22;
            $y = 18 + $row * 62;
            $cls = in_array($n, $lit, true) ? 'w-lit' : 'w';
            $out .= '<path class="' . $cls . '" d="M' . $x . ' ' . ($y + 40) . 'V' . ($y + 11) . 'a11 11 0 0 1 22 0V' . ($y + 40) . 'z"/>';
            $n++;
        }
    }
    return $out . '</svg>';
}

/** Footer skyline silhouette. */
function footer_skyline_svg(): string
{
    $shapes = '';
    $lights = '';
    $x = 0;
    $heights = [44, 62, 38, 70, 50, 34, 58, 46, 66, 40, 54, 36, 60, 48, 72, 42, 56, 38, 64, 50, 44, 68, 36, 52];
    foreach ($heights as $i => $h) {
        $w = 52 + ($i * 13) % 26;
        $y = 80 - $h;
        if ($i % 3 === 1) {
            $lights .= '<rect x="' . ($x + $w / 2 - 4) . '" y="' . (int) ($y + $h * 0.5) . '" width="8" height="10" rx="2"/>';
        }
        if ($i % 5 === 2) {
            // pitched roof
            $shapes .= '<path d="M' . $x . ' ' . ($y + 12) . 'l' . ($w / 2) . '-14l' . ($w / 2) . ' 14V80H' . $x . 'z"/>';
        } elseif ($i % 7 === 4) {
            // dome
            $shapes .= '<path d="M' . $x . ' 80V' . ($y + 14) . 'h' . ($w * 0.15) . 'a' . ($w * 0.35) . ' ' . ($w * 0.35) . ' 0 0 1 ' . ($w * 0.7) . ' 0h' . ($w * 0.15) . 'V80z"/>';
        } else {
            $shapes .= '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '"/>';
        }
        $x += $w + 4;
    }
    return '<svg class="footer-skyline" viewBox="0 0 1440 80" preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false">'
        . '<g fill="#122A3D">' . $shapes . '</g><g fill="#EFA927" opacity=".9">' . $lights . '</g></svg>';
}
