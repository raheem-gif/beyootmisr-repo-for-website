<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Drafts of the legal pages, from database/content/legal/{slug}.{ar,en}.html.
 * They are published outside production for review. In production a legal
 * page needs counsel_reviewed_on before /install/check passes.
 */
class LegalPageSeeder extends Seeder
{
    public const SLUGS = ['terms', 'client-terms', 'lawyer-terms', 'privacy', 'cookies', 'refunds', 'reviews', 'community', 'fraud', 'dpo', 'legal-aid'];

    public function run(): void
    {
        $dir = database_path('content/legal');
        foreach (self::SLUGS as $slug) {
            $ar = @file_get_contents("$dir/$slug.ar.html");
            $en = @file_get_contents("$dir/$slug.en.html");
            if ($ar === false || $en === false) {
                continue;
            }
            $page = Page::firstOrNew(['slug' => $slug]);
            // Never overwrite a page an editor has changed.
            if ($page->exists && $page->updated_by !== null) {
                continue;
            }
            $page->fill([
                'kind' => $slug === 'legal-aid' ? 'info' : 'legal',
                'title_ar' => trans("site.legal_pages.$slug", [], 'ar'),
                'title_en' => trans("site.legal_pages.$slug", [], 'en'),
                'body_ar' => $ar,
                'body_en' => $en,
                'version' => '2026-11-15',
                'is_published' => ! app()->isProduction(),
            ])->save();
        }
    }
}
