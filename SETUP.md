# 🛠️ Site Setup Guide

## Step 1 — Clone from GitHub

```bash
git clone https://github.com/adakash26434/akashdigital-wp-main-2zip.git
cd akashdigital-wp-main-2zip
```

## Step 2 — Create Production Config

Create `config-production.php` in project root:

```bash
cp config-production.php.example config-production.php
```

Edit `config-production.php` and fill in:
- DB credentials
- SESSION_SECRET
- APP_SECRET

## Step 3 — Generate Security Keys

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Run twice for two keys:
- First output → `SESSION_SECRET`
- Second output → `APP_SECRET`

## Step 4 — Database Setup

### cPanel MySQL:
1. Create MySQL Database
2. Create Database User
3. Import `database.sql` via phpMyAdmin

```php
define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_password');
```

### Local Dev (SQLite):
No setup needed - auto-creates on first run.

## Step 5 — Set Permissions

```bash
chmod 755 uploads/ storage/
find uploads/ storage/ -type f -exec chmod 644 {} \;
```

## Step 6 — Superadmin (Optional)

Set as cPanel PHP Environment Variables:

| Variable | Value |
|---|---|
| `SUPERADMIN_EMAIL` | admin@yourdomain.com |
| `SUPERADMIN_PASS_HASH` | bcrypt hash |

Generate hash:
```bash
php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT, ['cost'=>12]).PHP_EOL;"
```

## Done! ✅

- Website: `https://yourdomain.com`
- Admin: `https://yourdomain.com/admin/`
