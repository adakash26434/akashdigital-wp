# Akash Digital — Corporate Website & Admin Panel

## Project Overview
A production-ready PHP application providing a corporate website, admin panel, client portal, and REST API for cooperatives and tech companies.

- **Stack:** PHP 8.2, SQLite (dev) / MySQL (production), Tailwind CSS, Alpine.js
- **Version:** 1.4.0
- **Status:** Production Ready

## Key URLs (when running)
| Path | Description |
|------|-------------|
| `/` | Public homepage |
| `/admin/` | Admin panel |
| `/portal/` | Client portal |
| `/api/` | REST API |

## Running Locally on Replit
```bash
# Create dev config (SQLite mode, no MySQL needed)
# Then start the built-in PHP server:
php -S 0.0.0.0:5000 router.php
```

The SQLite database auto-initializes on first run at `data/app.db`.

## Environment / Secrets
- `SESSION_SECRET` — already set in Replit secrets
- For MySQL (production): `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` via `includes/config-production.php`

## Project Structure
```
admin/          Admin panel pages
api/            REST API endpoints
assets/         CSS, JS, images
includes/       Shared PHP includes (config, db, auth, helpers)
portal/         Client portal pages
uploads/        User-uploaded files
router.php      PHP built-in server router (Replit dev)
database.sql    MySQL schema for production
```

## User Preferences
- User will provide a list of small improvements to work on incrementally.
