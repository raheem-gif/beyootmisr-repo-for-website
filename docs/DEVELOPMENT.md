# Beyoot Misr website — developer guide

This document explains how the site is built so it can be maintained and extended.
For hosting/upload steps see the main `README.md`.

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
  assets/css/site.css       ← generated bundle of app/styles/*.css
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
    styles/*.css            ← CSS sources, concatenated in name order
    scripts/*.js            ← JS sources, concatenated in name order
    content/*.json          ← ALL editable content (see below)
    storage/                ← runtime data (admin users, logs, backups) — never commit
```

## Content files (`app/content`)

| File | What it holds |
|---|---|
| `texts.json` | Every piece of copy on the site, grouped by page. Taken word-for-word from the content document (PDF). Keys starting with `_` (e.g. `_label`) are metadata for the admin panel, not shown on the site. Strings may contain placeholders: `{hotline}`, `{whatsapp_general}`, `{whatsapp_legal}`, `{registration_number}`, `{registration_year}`, `{year}` — filled from `settings.json`. |
| `settings.json` | Contact numbers, address, email, social links, legal identity, donation methods, site URL, logo. Empty fields are hidden on the site. |
| `stats.json` | «أثرنا بالأرقام» numbers. A number is shown **only** when it has a value **and** `updated_at` is set (verified & dated, §02/§08). Otherwise the card reads «يُعلن بعد التوثيق». |
| `projects.json` | Projects (status `ongoing`=جارٍ / `done`=منجز, location, images, stage updates…). Only `published: true` projects get a page. Cost/donations are shown only when `show_financials` is true. |
| `stories.json` | Impact stories (types: treatment, livelihood, roof, success, mosque). Only published stories get a page. |
| `reports.json` | PDF reports (title, year, file path in `uploads/reports`). |
| `galleries.json` | Photo galleries per activity slug. |

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

## CSS conventions

* Mobile-first, RTL-first. Use **logical properties** (`margin-inline-start`,
  `padding-block`, `inset-inline-end`…) — never `left/right`.
* Use design tokens from `styles/00-tokens.css` (`--blue`, `--blue-700`,
  `--amber`, `--ink`, `--slate`, `--surface`, `--line`, `--radius…`, `--shadow…`).
  Brand colours come from the PDF: blue `#1984C0`, amber `#EFA927`, ink `#122A3D`, slate `#5A6772`.
  For small blue text on white use `--blue-700` (contrast 5.3:1). Never put white text on amber.
* Page-specific CSS goes in its own file (`styles/2X-<page>.css`) and is scoped
  under the body class `.tpl-<template-name>`.
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

## PHP compatibility

Code targets **PHP 7.4+** (shared hosting may not run PHP 8): no `match`, no
nullsafe `?->`, no named arguments, no `str_contains`/`str_starts_with`, no
union types, no enums, no `readonly`.
