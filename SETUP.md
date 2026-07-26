# Site Setup Guide

## Step 1 — Clone

```bash
git clone https://github.com/adakash26434/akashdigital-wp.git
cd akashdigital-wp
```

## Step 2 — Production config

Preferred path (same folder as `includes/config.php`):

```bash
cp includes/config-production.php.example includes/config-production.php
```

Legacy root path also works (`includes/config.php` loads either):

```bash
cp config-production.php.example config-production.php
```

Edit the file and set:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `SITE_URL`
- `APP_SECRET_KEY` (keep existing live key; changing it resets sessions)

Generate a new key only for a fresh install:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

## Step 3 — Database

1. Create MySQL database + user in cPanel  
2. Import `database.sql` via phpMyAdmin  

Schema upgrades also run automatically on admin / homepage load (`includes/db-migrations.php`).

## Step 4 — Permissions

```bash
chmod 755 uploads/
find uploads/ -type f -exec chmod 644 {} \;
```

## Step 5 — Superadmin (optional)

cPanel PHP environment variables:

| Variable | Value |
|---|---|
| `SUPERADMIN_EMAIL` | admin@yourdomain.com |
| `SUPERADMIN_PASS_HASH` | bcrypt hash |

```bash
php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT, ['cost'=>12]).PHP_EOL;"
```

## Done

- Site: `https://yourdomain.com`
- Admin: `https://yourdomain.com/admin/`
