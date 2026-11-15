<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Scans translation files and seeded content for the banned words, phrases
 * and behaviours of section 16. Exits non-zero on any hit, so the build fails.
 */
class CopyCheck extends Command
{
    protected $signature = 'esnad:copy-check';

    protected $description = 'Fail on banned words, phrases, em dashes, exclamation marks and emojis in copy';

    public function handle(): int
    {
        $hits = self::scan(base_path());
        foreach ($hits as $hit) {
            $this->line("<fg=red>{$hit['file']}:{$hit['line']}</> {$hit['rule']}  <fg=gray>{$hit['text']}</>");
        }
        if ($hits !== []) {
            $this->error(count($hits).' copy rule violations.');

            return self::FAILURE;
        }
        $this->info('Copy check passed.');

        return self::SUCCESS;
    }

    /** @return list<array{file: string, line: int, rule: string, text: string}> */
    public static function scan(string $base): array
    {
        $rules = require $base.'/config/copy_rules.php';
        $finder = (new Finder)->files()->in([$base.'/lang', $base.'/database/content'])->name(['*.php', '*.html', '*.json']);
        $hits = [];

        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', substr($file->getRealPath(), strlen(realpath($base)) + 1));
            $isArabic = str_contains($relative, '/ar/') || str_ends_with($relative, '.ar.html');
            $allowed = $rules['allow'][$relative] ?? [];

            foreach (preg_split('/\R/u', $file->getContents()) as $n => $line) {
                // Only the quoted strings in PHP files are copy; skip comments.
                $text = str_ends_with($relative, '.php') ? self::phpStrings($line) : strip_tags($line);
                if (trim($text) === '') {
                    continue;
                }
                $check = function (string $rule) use (&$hits, $relative, $n, $line, $allowed) {
                    if (! in_array($rule, $allowed, true)) {
                        $hits[] = ['file' => $relative, 'line' => $n + 1, 'rule' => $rule, 'text' => trim(mb_substr($line, 0, 140))];
                    }
                };

                foreach ($rules['patterns'] as $regex => $name) {
                    if (preg_match($regex, $text)) {
                        $check($name);
                    }
                }
                if ($isArabic) {
                    foreach ($rules['arabic'] as $phrase) {
                        if (mb_strpos($text, $phrase) !== false) {
                            $check("banned Arabic phrase \"$phrase\"");
                        }
                    }
                } else {
                    foreach ($rules['english'] as $word) {
                        if (preg_match('/(?<![\p{L}\-])'.preg_quote($word, '/').'(?![\p{L}\-])/iu', $text)) {
                            $check("banned English word \"$word\"");
                        }
                    }
                }
            }
        }

        return $hits;
    }

    private static function phpStrings(string $line): string
    {
        if (preg_match('#^\s*(//|\*|/\*)#', $line)) {
            return '';
        }
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'|\"((?:[^\"\\\\]|\\\\.)*)\"/u", $line, $m);
        $strings = array_merge(array_filter($m[1]), array_filter($m[2]));
        // Array keys are identifiers, not copy: drop strings followed by =>.
        foreach ($strings as $i => $s) {
            if (preg_match('/[\'"]'.preg_quote($s, '/').'[\'"]\s*=>/u', $line)) {
                unset($strings[$i]);
            }
        }

        return implode(' ', $strings);
    }
}
