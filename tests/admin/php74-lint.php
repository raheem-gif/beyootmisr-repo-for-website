<?php
/**
 * Static check for PHP 8-only syntax/functions in the admin code, so it keeps
 * running on PHP 7.4 shared hosting even though development uses PHP 8.
 * Usage: php tests/admin/php74-lint.php [dir…]   (default: public/admin public/app/admin)
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$dirs = array_slice($argv, 1) ?: [$root . '/public/admin', $root . '/public/app/admin'];
$php8Functions = [
    'str_contains', 'str_starts_with', 'str_ends_with', 'array_is_list', 'get_debug_type', 'fdiv',
    'preg_last_error_msg', 'get_resource_id', 'enum_exists', 'array_find', 'array_any', 'array_all',
    'mb_str_pad', 'json_validate', 'str_increment', 'iterator_apply_keys',
];
$problems = [];
$files = [];
foreach ($dirs as $d) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && substr($f->getFilename(), -4) === '.php') {
            $files[] = $f->getPathname();
        }
    }
}
sort($files);

foreach ($files as $file) {
    $tokens = token_get_all((string) file_get_contents($file));
    $sig = []; // significant tokens (no whitespace/comments)
    foreach ($tokens as $t) {
        if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $sig[] = $t;
    }
    $n = count($sig);
    $line = function ($i) use ($sig) {
        for ($j = $i; $j >= 0; $j--) {
            if (is_array($sig[$j])) {
                return $sig[$j][2];
            }
        }
        return 0;
    };
    $text = function ($t): string {
        return is_array($t) ? $t[1] : $t;
    };
    $report = function (int $i, string $what) use (&$problems, $file, $line, $root) {
        $problems[] = str_replace($root . '/', '', $file) . ':' . $line($i) . '  ' . $what;
    };
    for ($i = 0; $i < $n; $i++) {
        $t = $sig[$i];
        $id = is_array($t) ? $t[0] : null;
        $tx = $text($t);
        if (defined('T_MATCH') && $id === T_MATCH) {
            $report($i, 'match expression (PHP 8)');
        }
        if (defined('T_NULLSAFE_OBJECT_OPERATOR') && $id === T_NULLSAFE_OBJECT_OPERATOR) {
            $report($i, 'nullsafe operator ?-> (PHP 8)');
        }
        if (defined('T_ATTRIBUTE') && $id === T_ATTRIBUTE) {
            $report($i, 'attribute #[...] (PHP 8)');
        }
        if (defined('T_ENUM') && $id === T_ENUM) {
            $report($i, 'enum (PHP 8.1)');
        }
        if (defined('T_READONLY') && $id === T_READONLY) {
            $report($i, 'readonly (PHP 8.1)');
        }
        if ($id === T_STRING && in_array(strtolower($tx), $php8Functions, true) && ($sig[$i + 1] ?? '') === '(') {
            $prev = $text($sig[$i - 1] ?? '');
            if ($prev !== '->' && $prev !== '::' && $prev !== 'function') {
                $report($i, $tx . '() (PHP 8+)');
            }
        }
        // catch (Exception) without a variable
        if ($id === T_CATCH) {
            $j = $i + 2;
            while ($j < $n && $text($sig[$j]) !== ')') {
                $j++;
            }
            if (!is_array($sig[$j - 1]) || $sig[$j - 1][0] !== T_VARIABLE) {
                $report($i, 'catch without variable (PHP 8)');
            }
        }
        // Parameter / use lists: trailing comma, promoted constructor params, union/mixed/static types.
        if ($id === T_FUNCTION || $id === T_FN || $id === T_USE) {
            $j = $i + 1;
            while ($j < $n && $text($sig[$j]) !== '(' && $text($sig[$j]) !== ';' && $text($sig[$j]) !== '{') {
                $j++;
            }
            if ($j >= $n || $text($sig[$j]) !== '(') {
                continue;
            }
            $depth = 0;
            $k = $j;
            for (; $k < $n; $k++) {
                $c = $text($sig[$k]);
                if ($c === '(' || $c === '[') {
                    $depth++;
                } elseif ($c === ')' || $c === ']') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                } elseif ($depth === 1 && in_array(strtolower($c), ['public', 'private', 'protected'], true)) {
                    $report($k, 'constructor property promotion (PHP 8)');
                } elseif ($depth === 1 && $c === '|') {
                    $report($k, 'union type (PHP 8)');
                } elseif ($depth === 1 && strtolower($c) === 'mixed' && is_array($sig[$k + 1] ?? null) && $sig[$k + 1][0] === T_VARIABLE) {
                    $report($k, 'mixed type (PHP 8)');
                }
            }
            if ($k < $n && $text($sig[$k - 1]) === ',') {
                $report($k, 'trailing comma in parameter/use list (PHP 8)');
            }
            if ($id !== T_USE && $text($sig[$k + 1] ?? '') === ':') {
                $rt = strtolower($text($sig[$k + 2] ?? ''));
                $rt2 = $text($sig[$k + 3] ?? '');
                if ($rt === 'static' || $rt === 'mixed' || $rt === 'never' || $rt2 === '|') {
                    $report($k + 2, 'return type ' . $rt . ($rt2 === '|' ? '|…' : '') . ' (PHP 8)');
                }
            }
        }
        // Named arguments: "(" or "," then identifier then ":" (not "::")
        if ($id === T_STRING && ($sig[$i + 1] ?? null) === ':' && in_array($text($sig[$i - 1] ?? ''), ['(', ','], true)) {
            $report($i, 'named argument "' . $tx . ':" (PHP 8)');
        }
        // throw as an expression
        if ($id === T_THROW && in_array($text($sig[$i - 1] ?? ''), ['??', '?:', '=>', '?', ':', '&&', '||', '='], true)) {
            $report($i, 'throw expression (PHP 8)');
        }
        // new in initializers / static::class on objects are rare; $obj::class:
        if ($id === T_VARIABLE && $text($sig[$i + 1] ?? '') === '::' && strtolower($text($sig[$i + 2] ?? '')) === 'class') {
            $report($i, '$object::class (PHP 8)');
        }
    }
}

if ($problems) {
    echo "PHP 7.4 compatibility problems:\n  " . implode("\n  ", $problems) . "\n";
    exit(1);
}
echo 'php74-lint: ' . count($files) . " files OK\n";
