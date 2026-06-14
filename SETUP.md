# 🛠️ Site Setup Guide

## Step 1 — Copy the production config template

```bash
cp includes/config-production.php.example includes/config-production.php
```

Then open `includes/config-production.php` and fill in your values.

---

## Step 2 — Generate a unique APP_SECRET_KEY

Run this command to generate a strong, unique key for your deployment:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Paste the output into `config-production.php`:

```php
define('APP_SECRET_KEY', 'paste_your_generated_key_here');
```

> **Important:** Generate a fresh key for each deployment. Never copy a key from another site or from documentation.

---

## Step 3 — Set superadmin credentials

Set these as **cPanel PHP Environment Variables** (never hardcode them in a file):

| Variable | Example value |
|---|---|
| `SUPERADMIN_EMAIL` | `admin@yourdomain.com` |
| `SUPERADMIN_PASS_HASH` | *(bcrypt hash — see below)* |

Generate a bcrypt hash:

```bash
php -r "echo password_hash('YourStrongPassword', PASSWORD_BCRYPT, ['cost'=>12]).PHP_EOL;"
```

Set `SUPERADMIN_PASS_HASH` to the output.  
For local development only, you may use `SUPERADMIN_PASS_PLAIN` instead.

---

## Step 4 — Database Settings

In `includes/config-production.php`, update:

```php
define('DB_NAME', 'your_cpanel_db_name');
define('DB_USER', 'your_cpanel_db_user');
define('DB_PASS', 'your_database_password');
```

---

## Step 5 — Site URL

```php
define('SITE_URL', 'https://yourdomain.com');   // no trailing slash
```

---

## Step 6 — Upload the Files

Upload everything via **cPanel File Manager** or FTP to your `public_html` folder.

Import `database.sql` via phpMyAdmin.

---

## Done! ✅

Visit your site. If you see errors, check cPanel → Error Logs.
