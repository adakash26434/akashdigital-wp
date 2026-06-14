---
name: DB migrations SQLite safety
description: db-migrations.php must use driver-aware helpers, not MySQL SHOW TABLES/SHOW COLUMNS
---

The project runs on both MySQL (production) and SQLite (local dev via sqlite-init.php).

**Rule:** Never use `SHOW TABLES LIKE '...'` or `SHOW COLUMNS FROM ... LIKE '...'` directly in db-migrations.php. These are MySQL-only and throw on SQLite, silently failing every migration.

**How to apply:** Use the `dbTableExists(string $table)` and `dbColumnExists(string $table, string $col)` helper functions defined at the top of db-migrations.php. They branch on `DB_DRIVER === 'sqlite'` and use `sqlite_master` / `PRAGMA table_info()` for SQLite, and `SHOW TABLES`/`SHOW COLUMNS` for MySQL.

**Why:** sqlite-init.php handles all table creation for fresh SQLite installs. db-migrations.php is only for adding new columns/tables to *existing* installs (both MySQL prod and SQLite dev upgrades). Prior to the fix, every migration check threw a PDO exception on SQLite, meaning no migration ever ran in dev.
