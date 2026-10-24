# Beyoot Misr website — developer guide

This document explains how the site is built so it can be maintained and extended.
For hosting/upload steps see the main `README.md`; for the admin panel (editor guide
and first-run setup) see [`ADMIN.md`](ADMIN.md).

## Architecture in one paragraph

The public site is **plain static HTML** (fast, secure, works on any host). It is
*generated* by a small PHP static-site generator that lives in `public/app/`:
templates (`app/templates`) + editable content (`app/content/*.json`) → `.html`
files in `public/`. The same generator runs in two places:

1. On your computer: `php public/app/build.php` (PHP 7.4+ CLI).
2. On the server: the admin panel (`/admin/`) rebuilds the pages after every save,
   so editors never touch code (content document §12 "إدارة المحتوى").

Visitors never execute PHP. If PHP is unavailable on the hosting plan, the site
still works; only the admin panel won't.

```
public/                     ← upload the CONTENTS of this folder to the web root
  index.html, about.html…   ← generated pages (committed, ready to upload)
  activities/*.html         ← one page per activity (generated)
  projects/*.html           ← one page per published project (generated)
  stories/*.html            ← one page per published story (generated)
  assets/css/*.css          ← generated, minified bundles of app/styles/*.css (core.css + one per page stylesheet)
  assets/js/site.js         ← generated bundle of app/scripts/*.js
  assets/fonts, assets/img  ← Cairo font (OFL), favicon, OG image
  uploads/images, reports   ← files uploaded through the admin panel
  admin/                    ← admin panel (PHP)
  app/
    bootstrap.php           ← loads everything below
    build.php               ← CLI entry point
    lib/                    ← core.php, data.php, registry.php, components.php, illustrations.php, build.php
    templates/layout.php    ← <html> shell (head, header, footer, WhatsApp button)
    templates/partials/     ← header.php, footer.php
    templates/pages/*.php   ← one template per page type
    styles/*.css            ← CSS sources: 00–09 = shared core, 10+ = one file per page (see Build)
    scripts/*.js            ← JS sources, concatenated in name order
    content/*.json.php      ← ALL editable content, as guarded JSON (see below)
    admin/                  ← admin panel libraries (sections/, security, uploads, store…)
    storage/                ← runtime data (admin users, logs, backups) — never commit
```

## Content files (`app/content`)

Each file is **guarded JSON**: `<name>.json.php` whose first line is `<?php exit; ?>`
followed by the JSON. Requested over HTTP it runs as PHP and returns nothing, so
unpublished drafts never leak even on hosts that ignore `.htaccess`. Read and write
them only through `bm_json_read()` / `bm_json_write()` / `bm_content()` (they handle the
guard line); a legacy plain `<name>.json` is still read and is replaced by the guarded
file on the next admin save. Below, names are given without the `.json.php` suffix.

| File | What it holds |
|---|---|
| `texts` | Every piece of copy on the site, grouped by page. Taken word-for-word from the content document (PDF). Keys starting with `_` (e.g. `_label`) are metadata for the admin panel, not shown on the site. Strings may contain placeholders: `{hotline}`, `{whatsapp_general}`, `{whatsapp_legal}`, `{registration_number}`, `{registration_year}`, `{year}` — filled from `settings.json`. |
| `settings` | Contact numbers, address, email, social links, legal identity, donation methods, site URL, logo. Empty fields are hidden on the site. |
| `stats` | «أثرنا بالأرقام» numbers. A number is shown **only** when it has a value **and** `updated_at` is set (verified & dated, §02/§08). Otherwise the card reads «يُعلن بعد التوثيق». |
| `projects` | Projects (status `ongoing`=جارٍ / `done`=منجز, location, images, stage updates…). Only `published: true` projects get a page. Cost/donations are shown only when `show_financials` is true. |
| `stories` | Impact stories (types: treatment, livelihood, roof, success, mosque). Only published stories get a page. |
| `reports` | PDF reports (title, year, file path in `uploads/reports`). |
| `galleries` | Photo galleries per activity slug. |

Hand-edited files are forgiving: every known field is given the type of its default
(`bm_typed()` in `lib/data.php`), so a `null` or a number in a text field renders as text
instead of failing the whole build.

`app/lib/texts-defaults.json.php` exists only on servers updated with the code-update zip
(`tools/package.sh` writes it from `texts.json`). `bm_content('texts')` fills keys the server's
`texts.json` lacks from it, so copy added by a new template is never blank; server edits win
and lists are taken whole.

Image records look like `{"src": "uploads/images/abc.jpg", "thumb": "uploads/images/abc-640.jpg", "w": 1600, "h": 1067, "alt": "…", "caption": "…"}`.

## Template conventions

* Templates are PHP files in `app/templates/pages/`. They start with
  `defined('BM_APP') || exit;` and set `BM::$page['title']` and
  `BM::$page['description']` (from `texts.json` `meta_title` / `meta_description`).
* **All copy comes from `texts.json`** via `t('key')` (raw), `te('key')` (escaped,
  for output) or `tl('key')` (list). Never hard-code Arabic copy in templates.
* Build markup with the helpers in `lib/components.php` (`page_hero`,
  `section_head`, `btn`, `activity_card`, `project_card`, `story_card`,
  `stat_grid`, `gallery`, `check_list`, `steps`, `faq`, `chips`, `notice`,
  `empty_state`, `help_lines`, `wa_form`, `cta_share`, `charter_band`, `tagline`…).
  They escape their inputs.
* Escape every dynamic value: `e($value)`. Output links with `u('path.html')`
  (relative URLs that work from any folder depth and from disk).
* **Do not declare named functions inside templates** — a template such as
  `activity.php` renders ten times per build. Use closures if needed.
* One `<h1>` per page (the `page_hero` title). Sections use `<section aria-labelledby>`
  with an `<h2 id>`.
* Data-driven sections must have an honest empty state (no fake numbers, no
  stock photos — content document §08, §12).
* Section titles with the amber bar: `section_head(['bar' => true, …])` (CSS `.section-title--bar`).
* Shortening PDF copy (cards, meta descriptions): `sentence_excerpt()` cuts at a sentence
  boundary, never mid-phrase, so the document's qualifiers are kept.
* Phone/WhatsApp links: `wa_general()`, `wa_general_number()` (falls back to the hotline),
  `wa_legal()`, `tel_href()`. A number that cannot be dialled renders no link at all.
* Whether donating is possible: use `bm_can_donate()` / `bm_donation_methods()`
  (`lib/data.php`) so every page agrees (only titled methods with details or an
  official `https://` link count).

## CSS conventions

* Mobile-first, RTL-first. Use **logical properties** (`margin-inline-start`,
  `padding-block`, `inset-inline-end`…) — never `left/right`.
* Use design tokens from `styles/00-tokens.css` (`--blue`, `--blue-700`,
  `--amber`, `--ink`, `--slate`, `--surface`, `--line`, `--radius…`, `--shadow…`).
  Brand colours come from the PDF: blue `#1984C0`, amber `#EFA927`, ink `#122A3D`, slate `#5A6772`.
  For small blue text on white use `--blue-700` (contrast 5.3:1). Never put white text on amber.
* Page-specific CSS goes in its own file (`styles/2X-<page>.css`) and is scoped
  under the body class `.tpl-<template-name>`. Scoping is required: each page only
  loads the page stylesheets that mention its `.tpl-<name>` class (see Build).
* Focus: `:focus-visible` draws `var(--focus-ring)` (dark blue, visible on white and
  next to amber buttons). A dark section sets `--focus-ring: var(--amber)` — the list
  is in `01-base.css`.
* Breakpoints: 560, 640, 720, 900/960, 1024, 1280 (desktop navigation).
* Respect `prefers-reduced-motion`.

## Build

```bash
php public/app/build.php            # build everything
php public/app/build.php --strict   # fail on missing text keys (use before committing)
php public/app/build.php --only=about,charter   # quick partial build while developing
php -S 127.0.0.1:8080 -t public     # preview at http://127.0.0.1:8080/
```

The build writes files only when their content changed, bundles CSS/JS with a
content hash in the URL (cache-busting), writes `robots.txt`, and writes
`sitemap.xml` + canonical URLs once `settings.site_url` is set.

CSS is split so a page downloads only what it uses: `styles/00–09-*.css` form
`assets/css/core.css` (every page); every other `styles/NN-<name>.css` becomes
`assets/css/<name>.css`, linked by the template `<name>` and by every template whose
`.tpl-<template>` class appears in it (`20-about.css` → about and charter). Bundles are
minified (comments and whitespace only). Stale bundles and stale project/story pages
(unpublished or deleted) are removed by the build.

## Verification

```bash
php public/app/build.php --strict                         # 0 warnings expected
python3 -I tools/check-links.py public                    # internal links and #anchors
python3 -I tools/check-content.py docs/content-source.txt public   # every public line of the PDF is on the site
bash tests/admin/run.sh /tmp/bm-admin-sandbox 8095        # admin unit + HTTP tests on a throwaway copy
node tools/check-ui.js <node_modules with playwright> http://127.0.0.1:8080   # drawer, menus, WhatsApp, lightbox, overflow
```

`docs/content-source.txt` is the client's content document as extracted text (each line
tagged with its colour in the PDF: BLUE headings, ink body, gray developer notes).

## PHP compatibility

Code targets **PHP 7.4+** (shared hosting may not run PHP 8): no `match`, no
nullsafe `?->`, no named arguments, no `str_contains`/`str_starts_with`, no
union types, no enums, no `readonly`.
