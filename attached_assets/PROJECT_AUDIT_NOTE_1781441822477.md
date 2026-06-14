# Project Audit Note — Corporate Website & Admin Panel (akashdigital-wp)

> **Purpose of this note:** This is a full code audit of the uploaded zip, written so that an AI assistant (or developer) can pick it up and fix the project section by section, without needing to re-explore the whole codebase from scratch. Findings are grouped by topic, each with file paths, line references, and a concrete fix recommendation. Severity tags: 🔴 Critical, 🟠 High, 🟡 Medium, 🔵 Low/Cosmetic.

---

## 0. छोटो सारांश (नेपालीमा)

यो प्रोजेक्ट **राम्रो आधारमा बनेको छ** — `includes/head.php` ले सबै CSS/JS एकै ठाउँबाट लोड गर्छ, `siteSettings()`/`cms()` ले admin बाट गरिएको change public page मा देखाउने structure राम्रो छ, र language file (en/np) पूरै match छ। तर तलका कुराहरूमा सफा/uniform गर्न जरुरी छ:

1. 🔴 **Security**: `includes/superadmin.php` मा default password `Admin@12345` hardcode छ, plain-text mode ON छ। `includes/config.php` मा `APP_SECRET_KEY` पनि default value सहित commit भएको छ (SETUP.md मा त्यही key copy गर्न भनिएको छ — यो सबै site मा same key हुन सक्छ)। `diagnostic.php` ले DB config जानकारी सार्वजनिक देखाउँछ।
2. 🔴 **Database**: `database.sql` मा `notifications` table **दुई पटक फरक structure** सहित छ (एउटा `is_read/read_at`, अर्को `seen_at`)। कोडमा दुवै column नाम प्रयोग भएकाले notification "unread count" feature production वा dev मा से कुनै एकातिर भत्किन्छ।
3. 🟠 **CSS/UI uniformity**: `.form-input`, `.form-select`, `.form-textarea`, `.admin-table`, `.stat-card`, `.st-stat__value/__label` जस्ता classes `theme.css` र `admin-forms.css`/`pages.css` दुवैमा फरक-फरक value सहित duplicate छन्। "Stat card" को कम्तिमा **5 फरक version** छन् (theme.css, admin.css, crm-dashboard.php, charge-history.php, clients.php — सबैको markup/class फरक)।
4. 🟠 **Forms/Tables uniformity**: `includes/helpers.php` मा राम्रो `formInput()/formSelect()/formTextarea()` helper बनेको छ तर **67 मध्ये 1 admin page** ले मात्र प्रयोग गर्छ। त्यसैगरी `includes/admin-list-helper.php` (search/filter/pagination लाई uniform बनाउनको लागि) **67 मध्ये 5 page** मा मात्र छ — बाँकी pages ले आफ्नै तरिकाले search/table बनाएका छन्।
5. 🟡 **Unused/leftover files**: `attached_assets/` (xlsx + screenshot, dev leftover), `diagnostic.php`, `DEPLOYMENT_READY.md` (पुरानो changelog), public `gallery.php` (navbar/footer मा link नै छैन), `admin/api-tokens.php` (sidebar menu मा link छैन) — यी सबै हटाउने वा जोड्ने जरुरी।
6. 🟡 **Docs**: README.md ले `/team.php` भन्ने अलग page छ भन्छ तर वास्तवमा त्यो `about.php#team` भित्र छ। `api/index.php` को comment मा पुरानो project नाम "sahakari-php" छ।
7. 🟠 **Performance**: `assets/css/daisyui.min.css` (**2.8MB!**) हरेक admin/portal page मा load हुन्छ, जबकि project को आफ्नै `theme.css` (188KB) मा पूरा design system पहिले नै छ। DaisyUI लाई purge/trim गर्न सकिन्छ।

तल हरेक बुँदाको detail, file path, र fix सुझाव छ। यो note लाई AI लाई दिनुहोस् र step-by-step ठीक गराउनुहोस् — पहिले Critical (🔴), त्यसपछि High (🟠), अनि Medium/Low।

---

## 1. Overall Architecture Assessment

**The good parts (keep these patterns):**
- `includes/head.php` is a well-designed single source of truth for `<head>` (fonts, CSS, JS, theme toggle, dynamic brand-color CSS injection). This already solved the "duplicated `<link>` tags" problem that used to exist across `header.php`, `admin-layout.php`, `portal-layout.php`, auth pages, and error pages.
- `includes/helpers.php` centralizes `siteSettings()`, `cms()` (bilingual content), CSRF helpers, badges, audit logging, and form-builder helpers. The admin→public data flow (e.g. company name/address/contact, legal pages, banners) generally works through `site_settings` correctly.
- Bilingual support (`lang/en.php` vs `lang/np.php`) is in full parity — 227/227 keys match. No action needed there.
- `.htaccess` has solid security headers (CSP, HSTS, X-Frame-Options) and correctly blocks `includes/`, `cron/`, `dev-config.php` from direct web access.
- `uploads/.htaccess` correctly denies PHP execution inside upload directories.

**The structural problem:** the project clearly grew by having many admin pages built independently over time (per-page `<style>` blocks, hand-rolled forms/tables/stat-cards), while *later* someone added shared helper systems (`head.php`, `admin-list-helper.php`, `formInput()` etc.) that were never retrofitted into the older pages. The result is two parallel "design systems" coexisting — the new shared one (mostly unused) and the old per-page one (used almost everywhere). This is the root cause of almost all the "uniformity" issues described below. **The fix strategy should be: adopt the shared helpers as the single standard, and migrate the old pages to use them — not invent a third system.**

---

## 2. 🔴 CRITICAL — Security Issues (fix first, before anything else)

### 2.1 Hardcoded default superadmin credentials
**File:** `includes/superadmin.php`
```php
define('SUPERADMIN_EMAIL', getenv('SUPERADMIN_EMAIL') ?: 'admin@company.com');
define('SUPERADMIN_PASS_PLAIN', getenv('SUPERADMIN_PASS_PLAIN') ?: 'Admin@12345');
define('SUPERADMIN_PASS_HASH', getenv('SUPERADMIN_PASS_HASH') ?: '');
```
- Ships with **plain-text password mode enabled** and a real-looking default password (`Admin@12345`) for `admin@company.com`, committed to the repo.
- **Fix:** Remove the hardcoded fallback entirely (or make the app refuse to boot / show a setup screen if neither `SUPERADMIN_PASS_HASH` nor `SUPERADMIN_PASS_PLAIN` env vars are set). At minimum, force the admin to set a real password via env var on first deploy, and document this prominently in SETUP.md. Plain-text password storage mode should not exist in a "production-ready" build at all — bcrypt only.

### 2.2 Hardcoded `APP_SECRET_KEY` default, reused across deployments
**File:** `includes/config.php`
```php
if (!defined('APP_SECRET_KEY')) define('APP_SECRET_KEY', '2b1ef9a6d8ebd35717297cf300f812a6347734f07af63404f1951bf522f0f011');
```
- `SETUP.md` tells every new site owner to "copy this ready-made key" — **the exact same string**. This key derives `SESSION_SECRET`, which is used for session/CSRF security. If multiple deployments of this codebase use the same default key (because they followed SETUP.md literally and didn't generate their own), session/cookie security guarantees weaken across all of them.
- **Fix:** Remove the hardcoded value from `config.php`. Either (a) make `config.php` auto-generate a random key on first run and write it to `config-production.php`/`dev-config.php`, or (b) keep the "Setup Required" error screen (it already exists for the empty-string case) and require every deployment to generate its own key — don't ship a working default at all. Also update `SETUP.md` to stop providing a copy-pasteable key; instead show the `php -r "echo bin2hex(random_bytes(32));"` command only.

### 2.3 `diagnostic.php` exposes DB configuration
**File:** `/diagnostic.php` (project root, publicly reachable at `/diagnostic.php`)
- Prints `DB_DRIVER`, `DB_HOST`, `DB_NAME`, `DB_USER`, whether `DB_PASS` is set, SQLite path/existence, and attempts a live DB connection — all to an unauthenticated visitor.
- **Fix:** Delete this file entirely from the production build (it's a one-time debugging tool). If it must stay for setup troubleshooting, gate it behind `requireAdmin()` and/or `APP_ENV === 'development'` like `public/error.log.php` does.

### 2.4 `public/error.log.php` — error log viewer left in the codebase
- Already gated by `APP_ENV !== 'development'` → 403, which is good. But as a defense-in-depth measure, this file should simply not be deployed to production at all (exclude it from the deploy package), since a misconfigured `APP_ENV` would expose server paths and stack traces.

### 2.5 `config-production.php.example` leaks real project identity
**File:** `config-production.php.example`
```php
define('DB_NAME',    'akashdig_admin');
define('DB_USER',    'akashdig_admin');
define('SITE_URL', 'https://aakashdigital.com.np');
```
- README/SETUP describe this as a generic "for cooperatives and tech companies" template, but the example config reveals the real client's domain and DB-user naming convention. If this repo is meant to be reused as a white-label template, replace these with generic placeholders (`your_database_name`, `https://yourdomain.com`) consistent with `config.php`'s own placeholders.

---

## 3. 🔴 Database Schema Issues

### 3.1 `notifications` table is defined TWICE in `database.sql` with DIFFERENT, incompatible columns
**File:** `database.sql`
- **Line ~483** — `CREATE TABLE IF NOT EXISTS notifications (... priority, is_read, read_at ...)`
- **Line ~700** — `CREATE TABLE IF NOT EXISTS notifications (... seen_at ...)` — *different schema, same table name*

Because both use `IF NOT EXISTS`, only the **first** one (line 483, with `is_read`/`read_at`/`priority`) actually gets created when importing into MySQL — the second is silently skipped.

Meanwhile, `includes/sqlite-init.php` (used for local/dev SQLite) defines `notifications` with the **`seen_at`** schema (matching the *second*, skipped MySQL definition).

**Code that queries `seen_at`** (works only on SQLite dev DB, breaks on MySQL prod DB):
- `includes/notify.php` (lines 29, 37, 40)
- `includes/portal-layout.php` (line 186 — unread bell badge)
- `portal/notifications.php` (line 44)

**Code that queries `is_read`** (works only on MySQL prod DB, breaks on SQLite dev DB):
- `portal/index.php` (line 91 — dashboard notification count)

Both call sites are wrapped in `try/catch`, so the page won't crash — but the **notification unread-count badge silently shows 0 / never updates** on whichever environment doesn't match, and every page load spams `error_log` with "Unknown column" (MySQL) or "no such column" (SQLite) errors.

**Fix (pick ONE canonical schema and apply everywhere):**
1. Decide on one schema — recommend the `seen_at` version (simpler: `seen_at IS NULL` = unread) since it's what 3 of the 4 call sites already use.
2. In `database.sql`: delete the duplicate block (line ~700-712) and rewrite the first `notifications` table (line ~483) to use `seen_at DATETIME DEFAULT NULL` instead of `priority`/`is_read`/`read_at` (or keep both sets of columns if some other code relies on `priority` — search the codebase for `notifications.*priority` first; if nothing else uses it, drop it).
3. Update `portal/index.php` line 91 to use `seen_at IS NULL` instead of `is_read=0`.
4. Add a migration in `includes/db-migrations.php` for existing MySQL installs that may have been created with the old (line-483) schema, to add `seen_at` and drop/rename the obsolete columns.
5. Verify `includes/sqlite-init.php`'s definition matches the final agreed schema (it currently already matches `seen_at`, so step 2-3 should align dev and prod).

### 3.2 `includes/sqlite-init.php` is missing 5 tables that exist in `database.sql`
Tables present in `database.sql` but **not created** by `sqlite-init.php` (so they don't exist in the local/dev SQLite database at all):
- `activity_log` → used by `includes/activity-timeline.php`
- `client_status_history` → used by `admin/client-termination.php`
- `client_termination` → used by `admin/client-termination.php` and referenced in `includes/db-migrations.php`
- `onboarding_progress` → used by `portal/onboarding.php`
- `site_pages` → used by `includes/exporter.php`

**Fix:** Add `CREATE TABLE IF NOT EXISTS ...` for all 5 tables to `includes/sqlite-init.php` (using the same column definitions as `database.sql`, translated to SQLite types: `INT AUTO_INCREMENT PRIMARY KEY` → `INTEGER PRIMARY KEY AUTOINCREMENT`, `DATETIME ... ON UPDATE CURRENT_TIMESTAMP` → plain `DATETIME`, etc., following the conventions already used for the other 62 tables in that file).

### 3.3 `includes/db-migrations.php` uses MySQL-only syntax that `sqliteCompat()` doesn't translate
- `runDbMigrations()` (called on every admin page load AND on the public homepage via `index.php`) repeatedly uses `SHOW TABLES LIKE '...'` and `SHOW COLUMNS FROM ... LIKE '...'`.
- `sqliteCompat()` in `includes/db.php` only translates `ON DUPLICATE KEY UPDATE`, `IF()`, `FIELD()`, `CURDATE()`, `NOW()`, `DATE_ADD`/`DATE_SUB`, and `=!col` — **not** `SHOW TABLES`/`SHOW COLUMNS`.
- Result: on SQLite, every migration check throws (caught, logged to `error_log`), so **no migration in this file ever actually runs under SQLite** — including the `client_termination` table creation (Migration 11), which means combined with §3.2, `admin/client-termination.php` is fully broken in the dev/SQLite environment.

**Fix:** Either (a) add SQLite-equivalent checks (`SELECT name FROM sqlite_master WHERE type='table' AND name=?` for `SHOW TABLES`, and `PRAGMA table_info(table)` for `SHOW COLUMNS`) with a small branch on `DB_DRIVER`, or (b) simply ensure every table this file might create also has a baseline definition in `sqlite-init.php` (per §3.2) so the MySQL-only migration path is only needed for *upgrading* existing MySQL installs, never for fresh SQLite installs.

---

## 4. 🟠 CSS / Theme / Form / Button / Stat-Card Uniformity Issues

This is the core of the "uniform global CSS / theme / buttons / forms / stat card" request. The project has **two competing layers of styling**: `assets/theme.css` (188KB global design system, loaded on every page) and page-specific stylesheets (`admin-forms.css`, `pages.css`, `home.css`) plus a 2.8MB third-party framework (`daisyui.min.css`) loaded only on admin/portal. The same component classes are frequently re-declared with different values across these files, and individual admin pages add their own `<style>` overrides on top — so "what a button/input/stat-card actually looks like" depends on which page you're on and in which order the browser applied the cascading rules.

### 4.1 `.form-input` / `.form-select` / `.form-textarea` defined twice with different values
- **`assets/theme.css`** (lines ~723-746): base definition — `padding: 0.625rem 0.875rem`, `border-radius: var(--radius-md)`, `box-shadow: var(--shadow-xs)`.
- **`assets/css/admin-forms.css`** (lines ~50-70): a *second, full redefinition* with `!important` — `padding: 0.5rem 0.75rem !important`, `font-size: 1rem !important`, no `box-shadow`. The file comment says *"lighter padding. `!important` wins over theme.css defaults"* — i.e. this is a deliberate override, but it means the same CSS rule exists twice and a future edit to one file silently has no visible effect because the other wins via `!important`.

**Fix:** Pick one canonical definition (recommend keeping `admin-forms.css`'s `!important`-free version as the global default in `theme.css`, since admin/portal forms are the majority of form usage), delete the duplicate, and remove `!important` once there's only one source of truth.

### 4.2 `.admin-table` defined twice with different values
- **`assets/theme.css`** (lines ~4753-4794): `font-size: var(--text-sm)`, `th { padding: 1rem; font-weight: 700; }`, header background `var(--muted-soft)`, row hover `var(--muted-soft)`, plus a `.selected` row state and `.admin-table-row-actions`/`.admin-table-action-btn` helpers.
- **`assets/css/admin-forms.css`** (lines ~251-288), under the comment *"Admin table (not in theme.css)"* — **which is incorrect, it IS in theme.css** — redefines `.admin-table` with `font-size: 0.8125rem`, `th { padding: 0.625rem 1rem; font-weight: 600; }`, header background `var(--muted)`, row hover `var(--muted)`, plus `.cell-actions` (a *different* action-row helper than theme.css's `.admin-table-row-actions`).

**Fix:** Consolidate into a single `.admin-table` definition in one file. Decide on one of `.admin-table-row-actions`/`.admin-table-action-btn` (theme.css) vs `.cell-actions` (admin-forms.css) as the canonical action-button wrapper, and update all admin list pages to use that one class consistently (currently different pages use different ones — search both class names across `admin/*.php` to find which pages need updating).

### 4.3 `.st-stat__value` / `.st-stat__label` defined twice with different visual results
- **`assets/theme.css`** (lines ~2975-2990): `font-size: var(--text-xl)`, weight 800, color `var(--foreground)`.
- **`assets/css/pages.css`** (lines ~1565-1579): `font-size: clamp(1.75rem, 3vw, 2.25rem)`, color `var(--primary)`.

Both load on public pages (`pages.css` after `theme.css`), so `pages.css` wins — meaning editing `theme.css`'s version has zero visible effect on the public homepage stats. **Fix:** remove the `theme.css` copy (or vice versa) and keep one.

### 4.4 "Stat card" has **at least 5 different implementations** across the admin panel
This was specifically called out in the request ("stat card uniform garna khojeko") — here is the concrete inventory:

| # | Implementation | Where defined | Where used | Notes |
|---|---|---|---|---|
| 1 | `.stat-card` / `.stat-card__label` / `.stat-card .stat-value` / `.stat-card .stat-label` | `assets/theme.css` (~1841-1877), marked "Common in Admin/Portal" | `admin/client-analytics.php` | The intended global standard, but barely used. |
| 2 | `.admin-stat-grid` / `.admin-stat-card` / `.admin-stat-card__top` / `.admin-stat-card__value` / `.admin-stat-card__label` (+ `__dot`) | `assets/css/admin.css` (~469-520) | `admin/index.php` (main dashboard) | A completely separate component for the dashboard's top KPI tiles. |
| 3 | `.stat-card` *redefined locally* with different padding (`padding:1rem` vs theme.css's `var(--space-6)` + `min-height:140px`) | `<style>` block inside `admin/charge-history.php` (line 66) | `admin/charge-history.php` | Same class name as #1 but visually different — page-level override shadows the global one. |
| 4 | `.crm-stat-card` (+ `.alert` / `.success` variants) | `<style>` block inside `admin/crm-dashboard.php` (~103-130) | `admin/crm-dashboard.php` | Yet another bespoke component. |
| 5 | Ad-hoc inline-styled `<div class="st-card" style="padding:1rem;border-left:3px solid var(--danger);">` + `.stat-pill` | `admin/clients.php` (~164, ~221-229) | `admin/clients.php` (renewal stat cards added per DEPLOYMENT_READY.md) | No reusable class at all — pure inline styles. |

**Fix:** Standardize on ONE stat-card component (recommend #1, `.stat-card`, since it's already named for this purpose and lives in the global `theme.css`). Add a `--accent-color` CSS variable so pages can tint individual cards (covers the `crm-stat-card.alert`/`.success` and `clients.php` border-color use cases without new classes). Migrate `admin/index.php`'s dashboard tiles (#2), `crm-dashboard.php` (#4), `charge-history.php`'s local override (#3), and `clients.php`'s inline cards (#5) to this single component. This alone will make every admin page's "top numbers row" look and behave the same.

### 4.5 `.btn` / `.btn-primary` exists in both `theme.css` and 2.8MB `daisyui.min.css` — performance + consistency concern
- `assets/theme.css` defines a full custom button system (`.btn`, `.btn-sm/md/lg/xl`, `.btn-primary`, etc.) using this project's CSS variables (`--primary`, `--radius-*`).
- `assets/css/daisyui.min.css` (**2.8MB**, loaded on every admin/portal page per `head.php`) *also* defines `.btn`, `.btn-primary`, `.card`, `.modal`, `.alert`, `.dropdown`, `.tabs`, `.table`, `.badge`, etc. — using DaisyUI's own `oklch()`/`--p`/`--pc` CSS variables, which are **separate from** this project's `--primary`/`--foreground` tokens.
- `includes/head.php` already has to add manual `!important` overrides (`.btn { border-radius: var(--radius-sm) !important; }`, `.input/.select/.textarea { border-radius: var(--radius-md) !important; border-color: var(--border) !important; ... }`) specifically to force DaisyUI components back in line with the custom theme — a sign that DaisyUI's defaults actively fight the project's design tokens.
- The dark-mode toggle script sets `data-theme="light"`/`data-theme="dark"` on `<html>` — these are also **DaisyUI's own built-in theme names**, so any DaisyUI component class used *without* a corresponding override (52 admin/portal pages use raw `modal`/`alert`/`dropdown`/`tabs` classes) will pick up DaisyUI's built-in light/dark palette rather than this project's custom palette, unless every such usage has been manually checked.

**Fix (two options, pick based on effort budget):**
- **Quick:** Run DaisyUI's official purge/build step (or PostCSS + `@tailwindcss` content-scanning) to generate a trimmed CSS containing only the classes actually used (`modal`, `dropdown`, `tabs`, `alert`, `badge`, `table`) — this alone should shrink 2.8MB down to a few KB. Also explicitly define DaisyUI's `--p`/`--pc`/etc. CSS custom properties to mirror this project's `--primary`/`--primary-fg`, so DaisyUI components automatically match the brand colors instead of needing per-property `!important` patches.
- **Thorough:** Audit the 52 pages using DaisyUI `modal`/`dropdown`/`tabs`/`alert` and replace with equivalent components already styled in `theme.css` (which has its own `.alert-*`, toast, etc. — check for existing equivalents before writing new CSS), then remove `daisyui.min.css` entirely.

### 4.6 Form-builder helpers exist but are used on only 1 of ~44 admin form pages
**File:** `includes/helpers.php` defines `formInput()`, `formTextarea()`, `formSelect()`, `formCheckbox()`, `formRow()`, `formSection()` — these produce a consistent `<div class="form-group"><label class="form-label">...<span class="text-danger-token">*</span></label><input class="form-input" ...><span class="form-hint">...</span></div>` structure (with required-asterisks, hints, validation classes all handled uniformly).

**Only `admin/page-content.php` uses these helpers.** The other 43 pages with `class="form-input"` (settings.php: 35 occurrences, client-form.php: 23, crm-lead.php: 21, products.php: 18, crm.php: 15, status.php: 12, careers.php: 12, services.php/portfolio.php/news.php/announcements.php: 9 each, etc.) hand-write the `<div>`/`<label>`/`<input>` markup each time — meaning required-field asterisks, hints, and error states are present/styled inconsistently from page to page (some forms may be missing labels-for-screen-readers, required indicators, or hint text that the helper would add automatically).

**Fix:** This is a larger refactor — recommend doing it incrementally, prioritizing the highest-traffic/most-edited admin forms first (`client-form.php`, `settings.php`, `products.php`, `services.php`). For each form field, replace the hand-written `<div class="form-group">...` block with the equivalent `formInput()`/`formSelect()`/`formTextarea()` call. This is mechanical but should be done file-by-file with testing after each, not all at once.

### 4.7 List/search/filter/pagination helper exists but is used on only 5 of 67 admin pages
**File:** `includes/admin-list-helper.php` provides `adminListHeader()`, `adminListFilters()` (search box + status-tab filters), `adminBulkFormOpen/Close()`, `adminBulkToolbar()`, `adminListPagination()` — explicitly built (per its own doc comment) to *"collapse ~600 lines of duplicated toolbar/filter/bulk/pagination markup across ~10 admin pages into 4 helper calls."*

**Only these 5 pages use it:** `admin/agreement-templates.php`, `admin/amc-auto-revision.php`, `admin/client-agreements.php`, `admin/client-documents.php`, `admin/invoices.php`.

**At least 11 other list pages implement their own search box manually** (`name="q"` input with bespoke surrounding markup): `admin/amc-auto-revision.php` (partially), `admin/audit-log.php`, `admin/charge-history.php`, `admin/client-documents.php` (partially), `admin/clients.php`, `admin/contacts.php`, `admin/crm.php`, `admin/subscribers.php`, `admin/tickets.php`, `admin/users.php`, plus `admin/search.php` (global search, intentionally custom — leave as-is).

This is why "table search" feels inconsistent across the admin panel — the search box placement, styling, and the pagination controls below each table differ page to page.

**Fix:** Same incremental approach as §4.6 — migrate `clients.php`, `users.php`, `tickets.php`, `crm.php`, `contacts.php`, `subscribers.php`, `audit-log.php`, `charge-history.php` to use `adminListHeader()` + `adminListFilters()` + `adminListPagination()`. This directly answers "table ma search uniform garna" — once these pages use the shared helper, every list page's search bar and pagination will look and behave identically.

### 4.8 Naming inconsistency: deprecated layout-closer still widely used
- `includes/admin-layout-end.php` is explicitly documented as a *"Deprecated alias — use `includes/admin-layout-close.php` directly"*, kept only for backward compatibility.
- 40 pages already use the canonical `admin-layout-close.php`, but **15 pages still use the deprecated `admin-layout-end.php`** alias.
- **Fix (low priority, easy win):** find/replace `admin-layout-end.php` → `admin-layout-close.php` in those 15 files, then delete `admin-layout-end.php`.

---

## 5. 🟡 Unused Files, Leftovers & Cleanup List

Files/folders that appear to be dev artifacts, stale docs, or genuinely orphaned, recommended for removal (after a final grep-check that nothing references them):

| Item | Why it should go |
|---|---|
| `attached_assets/Book2_1781272342069.xlsx` | Leftover dev-uploaded spreadsheet, not referenced by any code. |
| `attached_assets/Screenshot_2026-06-12_at_7.58.52_PM_1781273638843.png` | Leftover dev screenshot, not referenced by any code. |
| `diagnostic.php` (root) | Dev-only DB diagnostic tool — security risk if left in prod (see §2.3). |
| `DEPLOYMENT_READY.md` | A one-off changelog describing already-merged commits ("4 commits ahead, ready to push"). Stale once merged — either delete, or fold its content into a proper `CHANGELOG.md` and delete the original. |
| `includes/admin-layout-end.php` | Deprecated alias (see §4.8) — delete after migrating the 15 remaining references. |
| `includes/dev-config.php` | Currently tracked in the repo even though README implies it's local-only/untracked, and `.gitignore` doesn't list it (only `config-production.php` is gitignored). Either add it to `.gitignore` and stop tracking it, or — if it's meant to be a *shared template* for local dev — rename it to `dev-config.php.example` and have developers copy it locally (consistent with how `config-production.php.example` is handled). |

**Routes that exist but aren't reachable from any menu/nav (orphaned pages — decide "link it" vs "remove it"):**
- `gallery.php` (public root page) — `admin/gallery.php` (CMS for gallery items) is in the admin sidebar, but the **public-facing `/gallery.php` page that should display those items is not linked from `includes/navbar.php` or `includes/footer.php`**. Either add a nav/footer link to it, or if gallery content is meant to be embedded elsewhere (e.g., on `about.php` or `portfolio.php`), confirm that and remove the standalone page if redundant.
- `admin/api-tokens.php` — exists, fully built, but **not in the admin sidebar menu** (`includes/admin-layout.php`). The only reference to it is from `admin/search.php`'s global-search results (so it's reachable only if a search result happens to match an API token). Add it to the sidebar menu (likely under "Settings" or "CRM/Clients" group, since it shows `client_id` per token) so admins can actually manage API tokens.

---

## 6. Admin ↔ Public ↔ Portal Connection Review

Overall this is in **better shape than the CSS layer** — most "admin edits X, public page Y shows it" connections checked out fine:
- Legal pages (`privacy.php`, `terms.php`, `cookie-policy.php`) correctly pull `legal_privacy`/`legal_terms`/`legal_cookie` (+ `_updated` timestamps) from `site_settings`, edited via `admin/legal.php`. ✅
- `about.php` and `faq.php` already use `cms()`/`stCompanyName()`/`stAddress()` for company name/address (per `DEPLOYMENT_READY.md`, the old hardcoded "Butwal" references were removed). ✅
- `admin/banners.php` → `banners` table → consumed by `index.php` (homepage) and `includes/portal-layout.php`. ✅
- Team members: `admin/team.php` manages `team_members`, displayed correctly inside `about.php`'s `#team` section. ✅ (but see §7.1 — README documents this as a separate page)
- `site_settings`-driven dynamic brand colors (`__brandCss()` in `head.php`) correctly read `brand_primary`/`brand_secondary`/etc. and inject overrides for all pages with a 5-minute file cache. ✅

**Issue found:**
- §3.1 (notifications schema mismatch) is the one concrete admin↔portal data-flow break: the unread-notification bell badge in `includes/portal-layout.php` and the count on `portal/index.php` will not reliably reflect data created elsewhere, because they query different (and on one environment, non-existent) columns.

**Two parallel, inconsistent API conventions** (worth flagging even though not directly "admin/public" — relevant if portal or future frontend code calls these):
- `api/index.php` — single-file router using `?r=<route-name>` query parameter (e.g. `/api/?r=site-settings`). Its doc comment still says `Base URL: /sahakari-php/api/` — a leftover from a previous/related project name, should be corrected to this project's actual base path.
- `api/v1/*.php` — separate folder with one file per endpoint (`cbs-sync.php`, `me.php`, `status.php`, `subscriptions.php`, `tickets.php`), a totally different routing convention (`/api/v1/tickets.php` style).

**Fix:** Decide on one convention going forward (recommend the `api/v1/<resource>.php` file-per-endpoint style, since it's more RESTful and easier to secure/cache per-route) and document it. Existing `api/index.php?r=...` routes can stay for backward compatibility but should be marked deprecated in a comment, with new endpoints added under `api/v1/` only. Fix the stale `/sahakari-php/api/` comment regardless.

---

## 7. 🟡 Documentation (.md) Updates Needed

### 7.1 README.md — public pages table is inaccurate
The "🌐 Public Pages" table lists:
```
| Team | `/team.php` | Team members (board + management) |
```
There is **no `/team.php`** in the project root — team members are rendered inside `about.php`'s `#team` section (driven by `admin/team.php` → `team_members` table). **Fix:** change the README row to `/about.php#team` (or whatever the final routing decision is), and double-check the rest of the public-pages table against the actual files in the project root (cross-reference against `includes/navbar.php`'s nav array, which is the real source of truth for what's "live" in navigation).

### 7.2 `DEPLOYMENT_READY.md`
As noted in §5 — this reads as a temporary PR-description/changelog snapshot ("Commits ahead: 4 ... Remove internal documentation files"), not ongoing documentation. Recommend either deleting it or converting its useful history into a `CHANGELOG.md` with dated entries, and removing this file from the repo root going forward (new changes shouldn't create new files like this — they should append to a changelog or just be in git history/PR descriptions).

### 7.3 `README.md` "Last Updated" / version stamp
README says `Last Updated: 2026-06-08`, `Version: 1.2.0`. After completing the cleanup pass described in this note, update this line (and bump the version) so the README reflects the actual current state — e.g., note that DaisyUI was trimmed, notifications schema was fixed, stat-card/form/list helpers were consolidated, etc. This gives future maintainers (human or AI) an accurate "what changed and when" trail without needing a separate `DEPLOYMENT_READY.md`-style file.

### 7.4 `api/index.php` header comment
Update `Base URL: /sahakari-php/api/` to the correct path for this project (see §6).

---

## 8. Recommended Priority / Action Plan

Suggested order of operations for whoever (human or AI) implements fixes — grouped so each step is testable independently:

1. **Security first (§2):** remove/rotate `superadmin.php` default credentials, remove the hardcoded `APP_SECRET_KEY` default + update SETUP.md, delete `diagnostic.php`, exclude `public/error.log.php` from prod deploys, genericize `config-production.php.example`.
2. **Database schema fix (§3):** resolve the duplicate/conflicting `notifications` table (pick `seen_at` schema), fix `portal/index.php`'s query, add the 5 missing tables to `sqlite-init.php`, and make `db-migrations.php` SQLite-safe (or rely on `sqlite-init.php` for fresh installs).
3. **Cleanup pass (§5):** delete `attached_assets/`, `diagnostic.php`, `DEPLOYMENT_READY.md`, `includes/admin-layout-end.php` (after the 15-file find/replace), resolve `dev-config.php` tracking, decide on `gallery.php` and `admin/api-tokens.php` (link or remove).
4. **CSS de-duplication (§4.1-4.3):** remove duplicate `.form-input/.form-select/.form-textarea`, `.admin-table`, and `.st-stat__*` rules — keep one source of truth per component. This is low-risk (mostly deletions) and immediately reduces "why did my CSS edit do nothing" confusion.
5. **DaisyUI trim (§4.5):** purge `daisyui.min.css` to only the components actually used (modal/dropdown/tabs/alert/badge/table), and align its theme variables with this project's `--primary`/etc.
6. **Stat-card consolidation (§4.4):** pick `.stat-card` as the standard, migrate the 4 other implementations to it.
7. **Form helper migration (§4.6)** and **list/search helper migration (§4.7):** incremental, page-by-page, starting with the highest-traffic admin pages (`clients.php`, `users.php`, `tickets.php`, `settings.php`, `client-form.php`, `products.php`, `services.php`).
8. **Docs refresh (§7):** fix README's public-pages table and version stamp, fix the API base-URL comment, decide on the two API conventions (§6).

Each numbered group above can be handed to an AI as a separate task/session with this note as context — they don't depend on each other except that #1-#3 should happen before #4-#8 (so cleanup doesn't get re-done after CSS/form refactors touch the same files).
