<?php
/**
 * «النصوص» — editor for content/texts.json.
 *
 * One form per top-level group (saved independently, merged into the fresh file
 * under a lock). The save is structure-preserving: it walks the CURRENT structure
 * (texts.json + keys only in the shipped texts-defaults, merged like bm_content()),
 * takes posted values only for existing editable leaves, keeps "_" metadata keys,
 * and rejects unknown keys. A hash of the group detects edits made by someone else
 * in the meantime (the form is shown again with the editor's values).
 */
defined('BM_APP') || exit;
require_once BM_APP_DIR . '/admin/texts-labels.php';

/** Lists of objects whose rows editors may add/remove (path => fields of a row). Others keep their fixed count. */
$repeatable = ['contact.faq' => ['q', 'a']];
$repeatLegend = ['contact.faq' => 'السؤال'];
$repeatAdd = ['contact.faq' => 'إضافة سؤال'];

AdmPage::$title = 'النصوص';
AdmPage::$lead = 'نصوص صفحات الموقع كما وردت في وثيقة المحتوى. افتح القسم الذي تريده، عدّل النص، ثم اضغط «حفظ». يُحفظ كل قسم وحده ويُحدَّث الموقع تلقائيًا.';

/*
 * Keys that exist only in app/lib/texts-defaults.json.php (shipped by a code update for copy
 * added by new templates) are merged in exactly as bm_content('texts') does for the public
 * site, so they are editable right after the update: the form shows them, the structure check
 * accepts them, and saving a group writes them into texts.json.
 */
$withDefaults = function ($data) {
    return is_array($data) ? bm_merge_texts(bm_texts_defaults(), $data) : $data;
};
$texts = $withDefaults(adm_content_load('texts'));
if (!is_array($texts)) {
    $texts = [];
}

$isList = function ($a): bool {
    return is_array($a) && ($a === [] || array_keys($a) === range(0, count($a) - 1));
};
$allStrings = function (array $a): bool {
    foreach ($a as $v) {
        if (!is_string($v)) {
            return false;
        }
    }
    return true;
};
$isMeta = function ($k): bool {
    return is_string($k) && $k !== '' && $k[0] === '_';
};
$isLong = function (string $key, string $value): bool {
    return in_array($key, ['text', 'description', 'meta_description', 'a', 'guide', 'statement', 'closing', 'intro', 'card_text', 'contact_note', 'privacy_note'], true)
        || bm_strlen($value) > 90 || strpos($value, "\n") !== false;
};
$hashOf = function ($group): string {
    return sha1((string) json_encode($group, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
};
$knownVars = array_keys(bm_text_vars());
$varNames = [
    'hotline' => 'رقم الخط الساخن',
    'whatsapp_general' => 'رقم واتساب المؤسسة',
    'whatsapp_legal' => 'رقم واتساب «إحنا معاكي»',
    'registration_number' => 'رقم الإشهار',
    'registration_year' => 'سنة الإشهار',
    'year' => 'السنة الحالية',
    'n' => 'رقم الصورة',
    'total' => 'عدد الصور',
    'num' => 'رقم النشاط',
    'activity' => 'اسم النشاط',
    'project' => 'اسم المشروع',
    'location' => 'موقع المشروع',
];
$placeholders = function (string $s): array {
    preg_match_all('/\{([a-z_]+)\}/', $s, $m);
    return array_values(array_unique($m[1]));
};

/* ------------------------------------------------------------------------ */
/* Save                                                                      */
/* ------------------------------------------------------------------------ */

$errors = [];
$override = [];      // group => candidate values to show again (validation error / conflict)
$openGroup = isset($_GET['open']) && is_string($_GET['open']) ? $_GET['open'] : '';

if (strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST') {
    $group = isset($_POST['group']) && is_string($_POST['group']) ? $_POST['group'] : '';
    $posted = $_POST['t'] ?? [];
    $hash = isset($_POST['h']) && is_string($_POST['h']) ? $_POST['h'] : '';
    if ($group === '' || $isMeta($group) || !isset($texts[$group]) || !is_array($texts[$group])) {
        adm_flash('error', 'هذا القسم غير موجود. أعد تحميل الصفحة.');
        adm_redirect('texts');
    }
    if (!is_array($posted)) {
        $posted = [];
    }
    $conflict = false;
    $candidate = null;

    // Merge posted values into the current structure (recursive closure).
    $merge = function ($cur, $post, array $path) use (&$merge, &$errors, $repeatable, $isList, $allStrings, $isMeta, $isLong, $knownVars, $placeholders) {
        $dotted = implode('.', $path);
        $err = 't.' . $dotted;
        $label = adm_text_label($path);
        if (is_string($cur)) {
            if ($post === null) {
                return $cur; // field not in the form (e.g. added meanwhile): keep it
            }
            if (!is_string($post)) {
                $errors[$err] = '«' . $label . '»: قيمة غير صالحة.';
                return $cur;
            }
            $long = $isLong((string) end($path), $cur);
            if (!adm_valid_utf8($post)) {
                $errors[$err] = '«' . $label . '»: يحتوي النص على رموز غير صالحة.';
                return $cur;
            }
            $v = adm_clean($post, $long);
            $max = $long ? 4000 : 400;
            if ($v === '' && trim($cur) !== '') {
                $errors[$err] = '«' . $label . '» لا يمكن تركه فارغًا لأنه يظهر في الموقع.';
            } elseif (bm_strlen($v) > $max) {
                $errors[$err] = '«' . $label . '» طويل جدًا: الحد الأقصى ' . $max . ' حرف.';
            } else {
                $unknown = array_diff($placeholders($v), $knownVars, $placeholders($cur));
                if ($unknown) {
                    $errors[$err] = '«' . $label . '»: المتغير {' . reset($unknown) . '} غير معروف. اكتب المتغيرات كما هي بين القوسين { } أو احذفها.';
                }
            }
            return $v;
        }
        if (is_array($cur) && $isList($cur) && $allStrings($cur)) {
            if ($post === null) {
                return $cur;
            }
            if (!is_string($post)) {
                $errors[$err] = '«' . $label . '»: قيمة غير صالحة.';
                return $cur;
            }
            $items = adm_lines($post, 1000, 100000);
            if (count($items) > 100) {
                $errors[$err] = '«' . $label . '»: الحد الأقصى 100 سطر.';
            } elseif (!$items && $cur) {
                $errors[$err] = '«' . $label . '»: اكتب عنصرًا واحدًا على الأقل (كل عنصر في سطر).';
            }
            foreach ($items as $item) {
                if (bm_strlen($item) > 1500) {
                    $errors[$err] = '«' . $label . '»: أحد الأسطر طويل جدًا (الحد الأقصى 1500 حرف).';
                    break;
                }
                $unknown = array_diff($placeholders($item), $knownVars, $placeholders(implode(' ', $cur)));
                if ($unknown) {
                    $errors[$err] = '«' . $label . '»: المتغير {' . reset($unknown) . '} غير معروف.';
                    break;
                }
            }
            return array_slice($items, 0, 100);
        }
        if (is_array($cur) && $isList($cur)) {
            // List of objects ({title,text}, {q,a}…)
            if ($post === null) {
                return $cur;
            }
            if (!is_array($post)) {
                $errors[$err] = '«' . $label . '»: قيمة غير صالحة.';
                return $cur;
            }
            if (isset($repeatable[$dotted])) {
                $keys = $repeatable[$dotted];
                $out = [];
                $n = 0;
                foreach ($post as $row) {
                    $n++;
                    if (!is_array($row)) {
                        $errors[$err] = '«' . $label . '»: قيمة غير صالحة.';
                        continue;
                    }
                    foreach (array_keys($row) as $rk) {
                        if (!in_array($rk, $keys, true)) {
                            $errors[$err] = '«' . $label . '»: حقل غير معروف. أعد تحميل الصفحة.';
                            continue 2;
                        }
                    }
                    $item = [];
                    $empty = true;
                    $missing = false;
                    foreach ($keys as $k) {
                        $raw = isset($row[$k]) && is_string($row[$k]) ? $row[$k] : '';
                        $v = adm_clean($raw, $isLong($k, ''));
                        if (bm_strlen($v) > 4000) {
                            $errors[$err . '.' . $n] = '«' . $label . '» ' . $n . ': النص طويل جدًا.';
                        }
                        $item[$k] = $v;
                        $empty = $empty && $v === '';
                        $missing = $missing || $v === '';
                    }
                    if ($empty) {
                        continue; // blank row = removed
                    }
                    if ($missing) {
                        $errors[$err] = '«' . $label . '»: أكمل كل الحقول في العنصر رقم ' . $n . ' أو احذفه.';
                    }
                    $out[] = $item;
                }
                if (count($out) > 50) {
                    $errors[$err] = '«' . $label . '»: الحد الأقصى 50 عنصرًا.';
                } elseif (!$out && $cur) {
                    $errors[$err] = '«' . $label . '»: أبقِ عنصرًا واحدًا على الأقل.';
                }
                return $out;
            }
            foreach (array_keys($post) as $pk) {
                if (!array_key_exists($pk, $cur)) {
                    $errors[$err] = '«' . $label . '»: عدد العناصر تغيّر. أعد تحميل الصفحة.';
                    return $cur;
                }
            }
            $out = [];
            foreach ($cur as $i => $item) {
                $out[$i] = $merge($item, $post[$i] ?? null, array_merge($path, [(string) $i]));
            }
            return $out;
        }
        if (is_array($cur)) {
            if ($post === null) {
                return $cur;
            }
            if (!is_array($post)) {
                $errors[$err] = '«' . $label . '»: قيمة غير صالحة.';
                return $cur;
            }
            foreach (array_keys($post) as $pk) {
                if ($isMeta($pk) || !array_key_exists($pk, $cur)) {
                    $errors['t.' . $dotted] = 'حقل غير معروف «' . $dotted . '.' . $pk . '». أعد تحميل الصفحة؛ ربما تغيّر هيكل النصوص.';
                    return $cur;
                }
            }
            $out = [];
            foreach ($cur as $k => $v) {
                $out[$k] = $isMeta($k) ? $v : $merge($v, $post[$k] ?? null, array_merge($path, [(string) $k]));
            }
            return $out;
        }
        return $cur; // numbers / booleans are not editable here
    };

    // Changed field paths, for the audit log (a list of strings counts as one field).
    $diff = function ($a, $b, string $prefix) use (&$diff, $isList, $allStrings): array {
        if ($a === $b) {
            return [];
        }
        if (!is_array($a) || !is_array($b) || ($isList($a) && $allStrings($a)) || count($a) !== count($b)) {
            return [$prefix];
        }
        $out = [];
        foreach ($a as $k => $v) {
            $out = array_merge($out, array_key_exists($k, $b) ? $diff($v, $b[$k], $prefix . '.' . $k) : [$prefix . '.' . $k]);
        }
        return $out;
    };

    $groupLabel = (string) ($texts[$group]['_label'] ?? $group);
    try {
        $result = adm_content_save('texts', function ($data) use ($group, $posted, $hash, $merge, $hashOf, $withDefaults, &$candidate, &$conflict, &$errors) {
            $full = $withDefaults($data);
            if (!is_array($full) || !isset($full[$group]) || !is_array($full[$group])) {
                throw new AdmUserError('هذا القسم لم يعد موجودًا. أعد تحميل الصفحة.');
            }
            $cur = $full[$group]; // the file's group + keys only in the shipped defaults
            $candidate = $merge($cur, $posted, [$group]);
            if ($errors) {
                return $data;
            }
            if (!hash_equals($hashOf($cur), $hash)) {
                $conflict = true;
                return $data;
            }
            if ($candidate === $cur) {
                return $data; // nothing edited: do not write default keys on their own
            }
            $data[$group] = $candidate;
            return $data;
        }, 'texts.save', function ($old, $new) use ($group, $groupLabel, $diff, $withDefaults) {
            $before = $withDefaults($old);
            $paths = $diff($before[$group] ?? [], $new[$group] ?? [], $group);
            $shown = array_slice($paths, 0, 6);
            return '«' . $groupLabel . '»: ' . adm_plural(count($paths), 'حقل واحد', 'حقلان', 'حقول', 'حقلًا')
                . ' (' . implode('، ', $shown) . (count($paths) > 6 ? '، …' : '') . ')';
        });
        if (!$errors && !$conflict) {
            if ($result['changed']) {
                adm_flash('success', 'حُفظت نصوص «' . $groupLabel . '». ' . adm_build_summary($result['report']));
            } else {
                adm_flash('info', 'لم تتغير أي نصوص في «' . $groupLabel . '».');
            }
            adm_redirect('texts', ['open' => $group], 'g-' . $group);
        }
    } catch (AdmUserError $e) {
        adm_flash('error', $e->getMessage(), $e->details);
        $errors = $errors ?: ['t.' . $group => $e->getMessage()];
    }
    // Show the form again with the editor's values.
    $texts = $withDefaults(adm_content_load('texts'));
    if ($candidate !== null) {
        $override[$group] = $candidate;
    }
    if ($conflict) {
        adm_flash('warning', 'عدّل مستخدم آخر قسم «' . $groupLabel . '» أثناء عملك، فلم يُحفظ شيء بعد. تعديلاتك ما زالت في الحقول أدناه فوق النسخة الأحدث؛ راجعها ثم اضغط «حفظ» مرة أخرى.');
    }
    $openGroup = $group;
    AdmForm::$errors = $errors;
}

/* ------------------------------------------------------------------------ */
/* Render                                                                    */
/* ------------------------------------------------------------------------ */

$countFields = function ($node) use (&$countFields, $isMeta, $isList, $allStrings): int {
    if (is_string($node)) {
        return 1;
    }
    if (!is_array($node)) {
        return 0;
    }
    if ($isList($node) && $allStrings($node)) {
        return 1;
    }
    $n = 0;
    foreach ($node as $k => $v) {
        if (!$isMeta($k)) {
            $n += $countFields($v);
        }
    }
    return $n;
};

$fieldHint = function (array $path, string $value, bool $isListField) use ($placeholders, $varNames): string {
    $parts = [];
    if ($isListField) {
        $parts[] = 'اكتب كل عنصر في سطر مستقل.';
    }
    $vars = $placeholders($value);
    if ($vars) {
        $named = [];
        foreach ($vars as $v) {
            $named[] = '{' . $v . '} = ' . ($varNames[$v] ?? $v);
        }
        $parts[] = 'لا تغيّر ما بين القوسين { }؛ يُملأ تلقائيًا: ' . implode('، ', $named) . '.';
    }
    if (end($path) === 'meta_description') {
        $parts[] = 'يُفضّل ألا يزيد على 160 حرفًا.';
    }
    return implode(' ', $parts);
};

$field = function (array $path, string $value, string $name) use ($isLong, $fieldHint): string {
    $key = (string) end($path);
    $long = $isLong($key, $value);
    $dotted = implode('.', $path);
    return '<div class="adm-tfield" data-tfield data-key="' . e($dotted) . '">'
        . adm_field([
            'path' => 't.' . $dotted,
            'name' => $name,
            'type' => $long ? 'textarea' : 'text',
            'label' => adm_text_label($path),
            'value' => $value,
            'max' => $long ? 4000 : 400,
            'hint' => $fieldHint($path, $value, false),
            'counter' => $key === 'meta_description' || $key === 'meta_title',
        ])
        . '<code class="adm-key" dir="ltr">' . e($dotted) . '</code></div>';
};

$listField = function (array $path, array $items, string $name) use ($fieldHint): string {
    $dotted = implode('.', $path);
    $value = implode("\n", $items);
    return '<div class="adm-tfield" data-tfield data-key="' . e($dotted) . '">'
        . adm_field([
            'path' => 't.' . $dotted,
            'name' => $name,
            'type' => 'textarea',
            'label' => adm_text_label($path),
            'value' => $value,
            'rows' => max(3, min(16, count($items) + (int) ceil(bm_strlen($value) / 90) + 1)),
            'hint' => $fieldHint($path, $value, true),
            'attrs' => ['class' => 'adm-lines', 'data-lines' => '1'],
        ])
        . '<code class="adm-key" dir="ltr">' . e($dotted) . '</code></div>';
};

$heading = function (array $node, array $path): string {
    if (isset($node['_label']) && is_string($node['_label'])) {
        return $node['_label'];
    }
    foreach (['name', 'title', 'q'] as $k) {
        if (isset($node[$k]) && is_string($node[$k]) && $node[$k] !== '') {
            return $node[$k];
        }
    }
    return adm_text_label($path);
};

$render = function ($node, array $path, string $name, int $depth) use (&$render, $field, $listField, $heading, $isMeta, $isList, $allStrings, $repeatable, $repeatLegend, $repeatAdd, $isLong): string {
    $html = '';
    foreach ($node as $k => $v) {
        if ($isMeta($k)) {
            continue;
        }
        $p = array_merge($path, [(string) $k]);
        $n = $name . '[' . $k . ']';
        if (is_string($v)) {
            $html .= $field($p, $v, $n);
        } elseif (is_array($v) && $isList($v) && $allStrings($v)) {
            $html .= $listField($p, $v, $n);
        } elseif (is_array($v) && $isList($v)) {
            $dotted = implode('.', $p);
            if (isset($repeatable[$dotted])) {
                $blank = array_fill_keys($repeatable[$dotted], '');
                $inner = adm_repeater([
                    'path' => 't.' . $dotted,
                    'items' => $v,
                    'blank' => $blank,
                    'add_label' => $repeatAdd[$dotted] ?? 'إضافة عنصر',
                    'legend_prefix' => $repeatLegend[$dotted] ?? 'عنصر',
                    'max' => 50,
                    'row' => function (string $rowPath, array $item) use ($isLong, $p, $blank) {
                        $parts = explode('.', $rowPath);
                        $idx = end($parts);
                        $out = '';
                        foreach (array_replace($blank, array_intersect_key($item, $blank)) as $ik => $iv) {
                            $iv = is_string($iv) ? $iv : '';
                            $fp = array_merge($p, [$idx, (string) $ik]);
                            $out .= adm_field([
                                'path' => 't.' . implode('.', $fp),
                                'name' => 't[' . implode('][', array_slice($fp, 1)) . ']',
                                'type' => $isLong((string) $ik, $iv) || $ik === 'a' ? 'textarea' : 'text',
                                'label' => adm_text_label([$p[0], (string) $ik]),
                                'value' => $iv,
                                'max' => 4000,
                            ]);
                        }
                        return $out;
                    },
                ]);
                $html .= '<fieldset class="adm-sub adm-sub--list" data-tfield data-key="' . e($dotted) . '"><legend>' . e(adm_text_label($p)) . '</legend>'
                    . '<p class="adm-hint">يمكنك إضافة عناصر أو حذفها. يظهر الترتيب على الموقع كما هو هنا.</p>' . $inner . '</fieldset>';
            } else {
                $rows = '';
                foreach ($v as $i => $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $rows .= '<fieldset class="adm-sub adm-sub--item"><legend>' . e(($i + 1) . '. ' . excerpt($heading($item, array_merge($p, [(string) $i])), 60)) . '</legend>'
                        . $render($item, array_merge($p, [(string) $i]), $n . '[' . $i . ']', $depth + 1) . '</fieldset>';
                }
                $html .= '<fieldset class="adm-sub adm-sub--list"><legend>' . e(adm_text_label($p)) . '</legend>' . $rows . '</fieldset>';
            }
        } elseif (is_array($v)) {
            $inner = $render($v, $p, $n, $depth + 1);
            if ($depth >= 1) {
                $html .= '<details class="adm-sub adm-sub--fold" data-fold><summary><span>' . e($heading($v, $p)) . '</span>' . adm_icon('chevron-down', 'adm-fold__chev') . '</summary><div class="adm-sub__body">' . $inner . '</div></details>';
            } else {
                $html .= '<fieldset class="adm-sub"><legend>' . e($heading($v, $p)) . '</legend>' . $inner . '</fieldset>';
            }
        }
    }
    return $html;
};

$groups = [];
foreach ($texts as $k => $v) {
    if (!$isMeta($k) && is_array($v)) {
        $groups[$k] = $v;
    }
}

if (!$groups) {
    echo adm_empty('file-text', 'لا توجد نصوص', 'ملف النصوص فارغ أو غير موجود.');
    return;
}
?>
<div class="adm-toolbar" data-text-tools>
  <div class="adm-search">
    <label class="adm-label" for="text-search">ابحث في النصوص</label>
    <div class="adm-search__box">
      <?= adm_icon('search') ?>
      <input type="search" id="text-search" class="adm-search__input" placeholder="اكتب كلمة من النص أو اسم الحقل…" data-text-search autocomplete="off">
    </div>
    <p class="adm-hint" data-search-status aria-live="polite"></p>
  </div>
  <div class="adm-toolbar__actions">
    <button type="button" class="adm-btn adm-btn--ghost adm-btn--sm" data-expand-all><?= adm_icon('chevron-down') ?><span>فتح الكل</span></button>
    <button type="button" class="adm-btn adm-btn--ghost adm-btn--sm" data-collapse-all><?= adm_icon('chevron-up') ?><span>طي الكل</span></button>
  </div>
</div>

<nav class="adm-chips" aria-label="أقسام النصوص">
  <ul role="list">
<?php foreach ($groups as $g => $node): ?>
    <li><a href="#g-<?= e($g) ?>" data-open-group="g-<?= e($g) ?>"><?= e((string) ($node['_label'] ?? $g)) ?></a></li>
<?php endforeach; ?>
  </ul>
</nav>

<div class="adm-groups">
<?php foreach ($groups as $g => $node):
    $data = $override[$g] ?? $node;
    $isOpen = $openGroup === $g;
    $label = (string) ($node['_label'] ?? $g);
    $groupErrors = array_filter($errors, function ($k) use ($g) {
        return strpos($k, 't.' . $g . '.') === 0 || $k === 't.' . $g;
    }, ARRAY_FILTER_USE_KEY);
?>
  <details class="adm-group" id="g-<?= e($g) ?>" data-group<?= $isOpen ? ' open' : '' ?>>
    <summary class="adm-group__summary">
      <span class="adm-group__title"><?= e($label) ?></span>
      <span class="adm-group__meta"><?= e(adm_plural($countFields($node), 'حقل واحد', 'حقلان', 'حقول', 'حقلًا')) ?></span>
      <?= adm_icon('chevron-down', 'adm-group__chev') ?>
    </summary>
    <div class="adm-group__body">
      <?= adm_form_open(['action' => adm_url('texts', ['open' => $g]), 'dirty' => true, 'class' => 'adm-textform']) ?>
        <input type="hidden" name="group" value="<?= e($g) ?>">
        <input type="hidden" name="h" value="<?= e($hashOf($node)) ?>">
        <?= adm_errors_summary($groupErrors) ?>
        <?= $render($data, [$g], 't', 0) ?>
        <div class="adm-savebar">
          <?= adm_submit('حفظ «' . $label . '»', ['icon' => 'save', 'busy' => 'جارٍ الحفظ وتحديث الموقع…']) ?>
          <span class="adm-savebar__note" data-dirty-note hidden>لديك تعديلات غير محفوظة في هذا القسم</span>
        </div>
      <?= adm_form_close() ?>
    </div>
  </details>
<?php endforeach; ?>
</div>
<p class="adm-hint adm-search-empty" data-search-empty hidden>لا توجد نتائج مطابقة. جرّب كلمة أخرى.</p>
