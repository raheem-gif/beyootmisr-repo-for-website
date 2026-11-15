# ESNAD (إسناد)

A platform for Egypt where people find, book and pay a verified lawyer, or press one button to get a lawyer on the phone, on video or at a police station. It is neutral booking software. ESNAD gives no legal advice, takes no share of a lawyer's fee and never holds a lawyer's fee in its own account.

Laravel 12, PHP 8.2+, MySQL 8 or MariaDB 10.6+, Blade with Alpine.js (CSP build), Tailwind CSS 4, Filament 4 for the admin. One codebase runs on cPanel shared hosting (`APP_HOSTING_MODE=shared`) and on a small VPS (`vps`).

The build follows the brief in phases (section 20). **Phase 1, Foundation, is done.** The report is below.

## Run it locally

```bash
composer install
npm ci && npm run build
cp .env.example .env            # then set APP_ENV=local, APP_DEBUG=true, DB_CONNECTION=sqlite
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan esnad:create-admin you@example.com
php artisan serve
```

- Public site: `/ar` (default) and `/en`
- Admin: `/admin` (two-factor setup is forced at first sign-in)
- Component gallery: `/ar/design` (not available in production)
- Server check: `/install/check?token=INSTALL_TOKEN`
- With `SMS_DRIVER=log` and no WhatsApp keys, sign-in codes are written to `storage/logs/laravel.log`.

Checks to run before every deploy:

```bash
php artisan test                # Pest, 68 tests
php artisan esnad:copy-check    # banned words, em dashes, exclamation marks, emojis (section 16)
vendor/bin/pint --test
```

## Deploy

**Shared hosting without SSH (Turbify cPanel).** Run `deploy/build-zip.sh` on your computer. It builds `dist/esnad-shared-*.zip` with the app folder (`esnad/`, above the web root) and `public_html/`. Upload and extract it in the home folder, create `esnad/.env` from `.env.example`, set a long `INSTALL_TOKEN`, then open `/install/migrate?token=…&seed=1` once (it locks itself after use) and `/install/check?token=…`. Add one cron line:

```
* * * * * /usr/local/bin/php /home/USER/esnad/artisan schedule:run >> /dev/null 2>&1
```

In shared mode the scheduler also works the database queue for up to 50 seconds each minute.

**VPS or shared hosting with SSH.** `deploy/deploy-ssh.sh [branch]` pulls, installs, migrates, seeds (seeders never overwrite admin edits) and caches. In VPS mode use `deploy/supervisor-esnad-worker.conf` for the queue worker.

Put free Cloudflare in front for DNS, TLS, static caching and Turnstile.

## Phase 1 report

### Built

| Area | What is in place |
| --- | --- |
| Hosting modes | `APP_HOSTING_MODE` switches the queue strategy (scheduler-driven worker on shared, Supervisor on VPS). All service keys and the operator identity live in `.env`, so the app can move to an Egyptian data centre without code changes |
| `/install/check` | Token-protected pass or fail list: PHP version, the 11 required extensions, memory and upload limits, `proc_open`, database and utf8mb4, migrations, writable folders, `.env` outside the web root, rewrites, HTTPS, cron and queue heartbeats, outbound HTTPS to Cloudflare, Paymob and Meta, server country, webhook URL, email driver, WhatsApp, SMS and Turnstile keys, operator identity, legal pages reviewed by counsel, debug mode. Also inside the admin under System |
| `/install/migrate` | One-time signed route that runs migrations (and seeds with `&seed=1`) on hosts without SSH, then locks itself |
| Locales and RTL | `/ar` and `/en` with `dir` and `lang` on the root, hreflang pairs, logical CSS properties only, directional icons mirrored, phone numbers and IDs isolated left to right, Western digits everywhere (`ar-EG-u-nu-latn`). All copy lives in `lang/ar` and `lang/en`, and a test fails if their keys differ |
| Design system | Tokens from section 15.2 in `resources/css/app.css`. Accent `#1F64A8` (the logo blue `#509BDF` only reaches 2.96:1 on white, so it is kept for the logo artwork and a deeper blue of the same hue carries actions at 6.09:1). Black pill for "اطلب محامي الآن". Self-hosted IBM Plex Sans Arabic (four weights, two preloaded) and Noto Naskh Arabic for legal text. Components: buttons, inputs, OTP input, select, tabs, modal, bottom sheet, toast, empty state, skeleton, lawyer card, price breakdown, status stepper, file tile, badges, alerts |
| Authentication | Egyptian mobile number plus a 6-digit code, WhatsApp Cloud API first and SMS as fallback, with a delivery log per attempt. Codes are hashed, expire in 5 minutes, lock after 5 wrong tries, one resend a minute, 5 sends per number and 20 per IP an hour, plus route rate limits. Turnstile on the sign-in form when keys are set. Eastern digits accepted everywhere and stored as Western |
| Welcome step | Name, form of address (أنتَ or أنتِ), governorate and the four consents |
| Roles | All 16 roles of section 5 seeded with spatie/laravel-permission. Every admin screen checks a permission on the server. Super admins pass all checks. Only super admins edit staff accounts or roles |
| Admin shell | Filament 4 at `/admin` in Arabic, right to left, ESNAD colours and font. Two-factor sign-in (authenticator app with recovery codes) is required for every staff member. 30-minute idle timeout, optional IP allowlist, local initials avatars (the default sends names to an outside service) |
| Audit log | Append-only `audit_logs`. The model refuses updates and deletes, MySQL triggers refuse them too, and each row carries a SHA-256 hash chained to the previous row, with a "verify chain" button. Staff opening a user's record, viewing access logs, changing settings, pages, flags or governorates are all recorded. Passwords and secrets are redacted |
| Consent register | Four separate, unticked consents (service, sensitive case data, transfer abroad, marketing) stored with time, version, method, scope, IP and user agent. Rows cannot be edited. Withdrawal sets a timestamp, and giving consent again creates a new row. Users see their history and withdraw from their account page. Read-only register for the DPO in the admin |
| Legal pages | Terms of use, client terms, lawyer terms, privacy notice, cookie notice, cancellation and refund policy, review policy, forum rules, fraud warning, DPO contact and legal aid, in Modern Standard Arabic and English, editable in the admin, rendered through an HTML sanitiser, with operator identity filled from `.env`. Published outside production only. In production `/install/check` fails until each has a counsel review date |
| Footer | Operator identity, commercial register, tax card, VAT number, address, phone, WhatsApp, email, DPO and PDPC licence (each shown once set), neutral-platform statement, fraud warning and links to every legal page |
| Anti-Cybercrime logs | `access_logs` with user, IP, method, route name, status and device, never query strings. Pruned after 180 days by the scheduler. Register table for judicial orders |
| Settings | Versioned settings: every change keeps the old value and asks for a reason, and goes into the audit log. Feature flags (search, request, forum, tools, analytics) start off |
| Data | 27 governorates with national ID codes; Cairo, Giza and Alexandria active |
| PWA | Manifest, icons from the logo, service worker that caches the shell and serves an offline page, never account pages |
| Security | Nonce-based content security policy with no `unsafe-eval`, HSTS on HTTPS, `nosniff`, `DENY` framing, encrypted sessions and national ID, Argon2id passwords, CSRF on every form, security.txt, robots.txt that blocks account and admin pages |
| Error pages | 403, 404, 419, 429, 500 and 503 in both languages, plus the offline page |
| Copy check | `php artisan esnad:copy-check` scans every translation and legal file for the 107 banned English items, the Arabic list, em and en dashes, exclamation marks and emojis. It runs in the test suite and in `build-zip.sh` |

### Tests

68 Pest tests, 240 assertions, all passing: phone normalising (Eastern and Persian digits, every prefix form), national ID structure, money formatting, Arabic search normaliser, the full OTP sign-up with WhatsApp and SMS fallback, wrong-code lockout, resend and per-number limits, expiry, suspended accounts, consent give, withdraw and immutability, audit append-only and tamper detection, settings versioning, admin access by role, forced two-factor setup, install check token, heartbeats, the admin command, the copy check and the Arabic and English key match. Every admin screen and the two-factor sign-in were also opened in Chromium, and the public pages were checked at 360 px and 1280 px for horizontal scroll and console errors. Public JavaScript is 72 KB (24 KB gzipped).

### Skipped or partial, and why

- **No staging deploy.** No hosting account is connected to this work. `deploy/build-zip.sh` and `/install/check` are ready for the first upload.
- **Firebase SMS fallback** is not wired. Firebase phone authentication sends the SMS from the browser SDK and needs a Firebase project. The SMS channel has a `log` driver for staging and an `http` driver for a local gateway such as WE Business SMS. WhatsApp templates also need Meta approval before codes go out.
- **Email and Google sign-in** for clients come with phase 2, where lawyers also need email and password.
- **Turnstile** is wired on the sign-in form and verified on the server, but runs only once keys are set.
- **Screenshot tests and axe checks** are not in CI yet. They were run by hand for this phase.
- **Filament primary buttons** use Filament's own shade choice for contrast, which is lighter than the site accent. Worth a custom theme in phase 2.
- **Reference capture (section 15.5)** needs a person with Chrome DevTools on hlc.com, lw.com and kirkland.com.

### Before launch (from the brief's checklist, still open)

Fill every operator value in `.env`, written answers from Turbify on section 3.1, Meta business verification and the Arabic OTP template, PDPC licences and a named DPO, counsel's review of each legal page (then set the review date in the admin), and a real `security.txt` contact.
