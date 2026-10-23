<?php
/**
 * Form helpers. All values are escaped here; field names use dotted paths that
 * map to PHP's bracket syntax: 'contact.email' → name="contact[email]".
 *
 *   AdmForm::$errors = $in->errors();   // errors keyed by dotted path are shown under the fields
 *
 *   adm_form_open(array $o = []): string     <form method=post> + CSRF. o: action (URL, default current),
 *                                             files (bool → multipart), id, class, dirty (bool → warn on leave),
 *                                             confirm (string → native confirm() before submit)
 *   adm_form_close(): string
 *   adm_csrf_input(): string
 *   adm_field(array $o): string              one labelled control. o:
 *       path, label, type (text|textarea|email|url|tel|number|date|month|password|select|checkbox|file),
 *       value, hint, error (default: AdmForm::$errors[path]), required, max (maxlength), rows,
 *       dir ('ltr' for URLs/phones/emails — automatic for those types), options (select: value => label),
 *       placeholder, autocomplete, accept (file), multiple (file), attrs (extra attributes), class,
 *       counter (bool: live character counter)
 *   adm_submit(string $label, array $o = []): string     o: variant (primary|secondary|danger|ghost), icon,
 *                                                        name, value, confirm, class, small
 *   adm_action_button(array $o): string     one-click POST action in its own tiny form:
 *       label, action (URL), fields (hidden name => value), variant, icon, confirm, small
 *   adm_link_button(string $label, string $href, array $o = []): string
 *   adm_repeater(array $o): string          add/remove rows (admin.js clones a <template>). o:
 *       path (e.g. 'methods'), items (list of arrays), row (callable(string $path, array $item): string — use
 *       "$path.title" etc.), blank (array for new rows), add_label, max, legend_prefix ('وسيلة التبرع' → «وسيلة التبرع 2»),
 *       empty (text when there are no rows), remove_label, remove_confirm
 *       keep_keys (bool: use the given array keys as row keys — when re-showing posted rows with errors)
 *       Rows are posted as path[<key>][field]; read them with array_values(adm_post(path, [])).
 *   adm_image_field(array $o): string       image upload with preview, alt text and caption. o:
 *       name (file input name, default 'image'), current (image record or null), alt_path, caption_path,
 *       remove_path (checkbox to delete the current image), label, hint, multiple, required_alt
 *   adm_pdf_field(array $o): string         PDF upload. o: name, current (path), label, hint
 *   adm_errors_summary(array $errors): string  alert listing all errors (links to the fields)
 */
declare(strict_types=1);
defined('BM_APP') || exit;

final class AdmForm
{
    /** @var array<string,string> */
    public static $errors = [];
}

function adm_name(string $path): string
{
    $parts = explode('.', $path);
    $name = array_shift($parts);
    foreach ($parts as $p) {
        $name .= '[' . $p . ']';
    }
    return $name;
}

function adm_id(string $path): string
{
    return 'f-' . trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', $path), '-');
}

function adm_csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(adm_csrf_token()) . '">';
}

function adm_form_open(array $o = []): string
{
    $a = [
        'method' => 'post',
        'action' => $o['action'] ?? adm_current_url(),
        'class' => trim('adm-form ' . ($o['class'] ?? '')),
        'id' => $o['id'] ?? null,
        'enctype' => !empty($o['files']) ? 'multipart/form-data' : null,
        'data-dirty' => !empty($o['dirty']) ? '1' : null,
        'data-confirm' => $o['confirm'] ?? null,
        'novalidate' => !empty($o['novalidate']) ? true : null,
    ];
    return '<form' . attrs($a) . '>' . adm_csrf_input();
}

function adm_form_close(): string
{
    return '</form>';
}

function adm_field(array $o): string
{
    $path = (string) ($o['path'] ?? '');
    $type = (string) ($o['type'] ?? 'text');
    $id = (string) ($o['id'] ?? adm_id($path));
    $name = (string) ($o['name'] ?? adm_name($path));
    $label = (string) ($o['label'] ?? '');
    $value = $o['value'] ?? '';
    $hint = (string) ($o['hint'] ?? '');
    $error = (string) ($o['error'] ?? (AdmForm::$errors[$path] ?? ''));
    $ltr = in_array($type, ['url', 'email', 'tel', 'password', 'number'], true) ? 'ltr' : null;
    $dir = $o['dir'] ?? $ltr;
    $describedBy = trim(($hint !== '' ? $id . '-hint ' : '') . ($error !== '' ? $id . '-err' : ''));
    $common = [
        'id' => $id,
        'name' => $name,
        'required' => !empty($o['required']) ? true : null,
        'aria-invalid' => $error !== '' ? 'true' : null,
        'aria-describedby' => $describedBy !== '' ? $describedBy : null,
        'dir' => $dir,
        'placeholder' => $o['placeholder'] ?? null,
        'autocomplete' => $o['autocomplete'] ?? null,
        'maxlength' => isset($o['max']) && in_array($type, ['text', 'textarea', 'email', 'url', 'tel', 'password'], true) ? (string) (int) $o['max'] : null,
        'data-counter' => !empty($o['counter']) ? '1' : null,
    ];
    $common = array_replace($common, (array) ($o['attrs'] ?? []));

    if ($type === 'checkbox') {
        $control = '<input type="checkbox" value="1"' . attrs($common + ['checked' => !empty($value) ? true : null]) . '>';
        $html = '<div class="adm-field adm-field--check' . (!empty($o['class']) ? ' ' . e($o['class']) : '') . '">'
            . '<label class="adm-check" for="' . e($id) . '">' . $control . '<span>' . e($label) . '</span></label>';
    } else {
        if ($type === 'textarea') {
            $rows = (int) ($o['rows'] ?? max(3, min(14, (int) ceil(bm_strlen((string) $value) / 70) + substr_count((string) $value, "\n") + 1)));
            $control = '<textarea' . attrs($common + ['rows' => (string) $rows]) . '>' . e((string) $value) . '</textarea>';
        } elseif ($type === 'select') {
            $control = '<select' . attrs($common) . '>';
            foreach ((array) ($o['options'] ?? []) as $v => $l) {
                $control .= '<option value="' . e((string) $v) . '"' . ((string) $v === (string) $value ? ' selected' : '') . '>' . e((string) $l) . '</option>';
            }
            $control .= '</select>';
        } elseif ($type === 'file') {
            $control = '<input type="file"' . attrs(array_replace($common, [
                'accept' => $o['accept'] ?? null,
                'multiple' => !empty($o['multiple']) ? true : null,
                'name' => $name . (!empty($o['multiple']) ? '[]' : ''),
            ])) . '>';
        } else {
            $extra = ['type' => $type];
            if ($type !== 'password') {
                $extra['value'] = is_scalar($value) ? (string) $value : '';
            }
            if ($type === 'number') {
                $extra['type'] = 'text';
                $extra['inputmode'] = 'decimal';
            }
            $control = '<input' . attrs($extra + $common) . '>';
        }
        $req = !empty($o['required']) ? ' <span class="adm-req" aria-hidden="true">*</span>' : '';
        $html = '<div class="adm-field' . (!empty($o['class']) ? ' ' . e($o['class']) : '') . ($error !== '' ? ' has-error' : '') . '">'
            . '<label class="adm-label" for="' . e($id) . '">' . e($label) . $req . '</label>'
            . $control;
    }
    if ($hint !== '') {
        $html .= '<p class="adm-hint" id="' . e($id) . '-hint">' . e($hint) . '</p>';
    }
    if ($error !== '') {
        $html .= '<p class="adm-error" id="' . e($id) . '-err">' . adm_icon('circle-alert') . '<span>' . e($error) . '</span></p>';
    }
    return $html . '</div>';
}

function adm_submit(string $label, array $o = []): string
{
    $variant = (string) ($o['variant'] ?? 'primary');
    $a = [
        'type' => 'submit',
        'class' => trim('adm-btn adm-btn--' . $variant . (!empty($o['small']) ? ' adm-btn--sm' : '') . ' ' . ($o['class'] ?? '')),
        'name' => $o['name'] ?? null,
        'value' => $o['value'] ?? null,
        'data-confirm' => $o['confirm'] ?? null,
        'data-busy' => $o['busy'] ?? 'جارٍ الحفظ…',
        'formaction' => $o['formaction'] ?? null,
        'formnovalidate' => !empty($o['formnovalidate']) ? true : null,
    ];
    $icon = isset($o['icon']) ? adm_icon((string) $o['icon']) : '';
    return '<button' . attrs($a) . '>' . $icon . '<span>' . e($label) . '</span></button>';
}

function adm_link_button(string $label, string $href, array $o = []): string
{
    $variant = (string) ($o['variant'] ?? 'secondary');
    $a = [
        'href' => $href,
        'class' => trim('adm-btn adm-btn--' . $variant . (!empty($o['small']) ? ' adm-btn--sm' : '') . ' ' . ($o['class'] ?? '')),
        'target' => !empty($o['external']) ? '_blank' : null,
        'rel' => !empty($o['external']) ? 'noopener' : null,
    ];
    $icon = isset($o['icon']) ? adm_icon((string) $o['icon']) : '';
    return '<a' . attrs($a) . '>' . $icon . '<span>' . e($label) . '</span></a>';
}

/** A single POST action (delete, rebuild…) as its own small form, with optional confirm(). */
function adm_action_button(array $o): string
{
    $html = '<form method="post" class="adm-inline-form" action="' . e((string) ($o['action'] ?? adm_current_url())) . '"'
        . (!empty($o['confirm']) ? ' data-confirm="' . e((string) $o['confirm']) . '"' : '') . '>' . adm_csrf_input();
    foreach ((array) ($o['fields'] ?? []) as $k => $v) {
        $html .= '<input type="hidden" name="' . e((string) $k) . '" value="' . e((string) $v) . '">';
    }
    $html .= adm_submit((string) ($o['label'] ?? ''), [
        'variant' => $o['variant'] ?? 'secondary',
        'icon' => $o['icon'] ?? null,
        'small' => !empty($o['small']),
        'busy' => $o['busy'] ?? 'جارٍ التنفيذ…',
        'class' => $o['class'] ?? '',
    ]);
    return $html . '</form>';
}

/**
 * Repeatable rows. The row renderer receives the dotted path of the row
 * ("methods.3") and the row data. A <template> holds a blank row for admin.js.
 */
function adm_repeater(array $o): string
{
    $path = (string) $o['path'];
    // keep_keys: keep the posted row keys (so errors keyed "path.<key>.field" land on the right row).
    $items = !empty($o['keep_keys']) ? (array) ($o['items'] ?? []) : array_values((array) ($o['items'] ?? []));
    $row = $o['row'];
    $max = (int) ($o['max'] ?? 50);
    $prefix = (string) ($o['legend_prefix'] ?? 'عنصر');
    $rowHtml = function (string $key, array $item, int $n) use ($path, $row, $prefix) {
        $title = $prefix . ' ' . ($n > 0 ? $n : 'جديد');
        return '<fieldset class="adm-repeat__row" data-row data-row-key="' . e($key) . '">'
            . '<legend class="adm-repeat__legend"><span data-row-title>' . e($title) . '</span></legend>'
            . $row($path . '.' . $key, $item)
            . '<div class="adm-repeat__tools"><button type="button" class="adm-btn adm-btn--ghost adm-btn--sm adm-btn--danger-text" data-row-remove'
            . ' data-confirm="' . e((string) ($o['remove_confirm'] ?? 'حذف هذا العنصر؟ لن يُحذف نهائيًا حتى تضغط «حفظ».')) . '">'
            . adm_icon('trash-2') . '<span>' . e((string) ($o['remove_label'] ?? 'حذف')) . '</span></button></div>'
            . '</fieldset>';
    };
    $html = '<div class="adm-repeat" data-repeat data-max="' . $max . '" data-legend="' . e($prefix) . '">';
    $html .= '<div class="adm-repeat__rows" data-rows>';
    $n = 0;
    foreach ($items as $i => $item) {
        $html .= $rowHtml((string) $i, (array) $item, ++$n);
    }
    $html .= '</div>';
    if (!$items && !empty($o['empty'])) {
        $html .= '<p class="adm-repeat__empty" data-empty>' . e((string) $o['empty']) . '</p>';
    }
    $html .= '<template data-template>' . $rowHtml('__i__', (array) ($o['blank'] ?? []), 0) . '</template>';
    $html .= '<button type="button" class="adm-btn adm-btn--secondary adm-btn--sm" data-row-add>' . adm_icon('plus')
        . '<span>' . e((string) ($o['add_label'] ?? 'إضافة')) . '</span></button>';
    return $html . '</div>';
}

/** Image upload block: preview of the current image, file input, alt text, caption, remove checkbox. */
function adm_image_field(array $o): string
{
    $name = (string) ($o['name'] ?? 'image');
    $current = $o['current'] ?? null;
    $altPath = (string) ($o['alt_path'] ?? $name . '_alt');
    $capPath = $o['caption_path'] ?? null;
    $id = adm_id($name);
    $html = '<div class="adm-upload">';
    $html .= '<div class="adm-upload__label">' . e((string) ($o['label'] ?? 'الصورة')) . '</div>';
    if (is_array($current) && !empty($current['src'])) {
        $thumb = !empty($current['thumb']) ? $current['thumb'] : $current['src'];
        $html .= '<figure class="adm-upload__current"><img src="' . e(adm_public_url((string) $thumb)) . '" alt="' . e((string) ($current['alt'] ?? '')) . '" loading="lazy">'
            . '<figcaption>' . e((string) ($current['alt'] ?? '')) . '</figcaption></figure>';
        if (!empty($o['remove_path'])) {
            $html .= adm_field(['type' => 'checkbox', 'path' => (string) $o['remove_path'], 'label' => (string) ($o['remove_label'] ?? 'حذف الصورة الحالية')]);
        }
    }
    $hint = trim((string) ($o['hint'] ?? '') . ' ' . adm_upload_limits_text('image'));
    if (!adm_image_ready()) {
        $html .= '<p class="adm-alert adm-alert--warning">' . adm_icon('triangle-alert') . '<span>رفع الصور غير متاح لأن مكتبة GD غير مفعّلة على الاستضافة.</span></p>';
    } else {
        $html .= adm_field([
            'type' => 'file',
            'path' => $name,
            'name' => $name,
            'id' => $id,
            'label' => (string) ($o['file_label'] ?? (is_array($current) && !empty($current['src']) ? 'استبدال الصورة' : 'اختر صورة')),
            'accept' => 'image/jpeg,image/png,image/webp',
            'multiple' => !empty($o['multiple']),
            'hint' => $hint,
            'attrs' => ['data-preview' => $id . '-preview'],
            'error' => AdmForm::$errors[$name] ?? '',
        ]);
        $html .= '<div class="adm-upload__preview" id="' . e($id) . '-preview" hidden></div>';
        if (empty($o['no_alt'])) {
            $html .= adm_field([
                'path' => $altPath,
                'label' => (string) ($o['alt_label'] ?? 'وصف الصورة (نص بديل)'),
                'value' => (string) ($o['alt'] ?? (is_array($current) ? ($current['alt'] ?? '') : '')),
                'max' => 250,
                'hint' => 'جملة قصيرة تصف ما في الصورة، مثل: «أعمال صب سقف المسجد». مطلوب عند رفع صورة جديدة.',
            ]);
        }
        if ($capPath !== null) {
            $html .= adm_field([
                'path' => (string) $capPath,
                'label' => 'تعليق يظهر تحت الصورة (اختياري)',
                'value' => (string) ($o['caption'] ?? (is_array($current) ? ($current['caption'] ?? '') : '')),
                'max' => 300,
            ]);
        }
    }
    $html .= adm_privacy_reminder();
    return $html . '</div>';
}

function adm_pdf_field(array $o): string
{
    $name = (string) ($o['name'] ?? 'pdf');
    $html = '<div class="adm-upload">';
    if (!empty($o['current'])) {
        $html .= '<p class="adm-upload__file">' . adm_icon('file-text') . '<a href="' . e(adm_public_url((string) $o['current'])) . '" target="_blank" rel="noopener">'
            . e(basename((string) $o['current'])) . '</a></p>';
    }
    $html .= adm_field([
        'type' => 'file',
        'path' => $name,
        'name' => $name,
        'label' => (string) ($o['label'] ?? (!empty($o['current']) ? 'استبدال الملف' : 'اختر ملف PDF')),
        'accept' => 'application/pdf,.pdf',
        'hint' => trim((string) ($o['hint'] ?? '') . ' ' . adm_upload_limits_text('pdf')),
        'error' => AdmForm::$errors[$name] ?? '',
    ]);
    $html .= adm_privacy_reminder();
    return $html . '</div>';
}

function adm_errors_summary(array $errors): string
{
    if (!$errors) {
        return '';
    }
    $html = '<div class="adm-alert adm-alert--error" role="alert" tabindex="-1" data-focus>' . adm_icon('circle-alert')
        . '<div><strong>لم يُحفظ شيء بعد. راجع ما يلي:</strong><ul>';
    foreach ($errors as $path => $msg) {
        $html .= '<li><a href="#' . e(adm_id((string) $path)) . '">' . e((string) $msg) . '</a></li>';
    }
    return $html . '</ul></div></div>';
}
