---
name: SQLite init unclosed exec string
description: sqlite-init.php had a PHP parse error from an unclosed $pdo->exec() string that prevented all table creation silently
---

# SQLite Init Unclosed exec() String

## The rule
Any `$pdo->exec("` block in sqlite-init.php that spans multiple lines must end with `");` on its own line before the closing `}` of the containing function.

## Why
The `client_agreements/client_documents/invoices/invoice_items` batch (starting ~line 915) was missing the closing `");`. PHP parsed everything after it — including `_sqliteInitSeedData()` — as part of the string. This produced `Parse error: syntax error, unexpected identifier "INSERT", expecting ")"` at the `$pdo->prepare("INSERT OR IGNORE INTO users...` line. The parse error prevented the entire `sqlite-init.php` file from loading, so `sqliteInit()` was never defined, and the DB was never seeded.

## How to apply
When adding new `$pdo->exec("... SQL ...");` blocks to sqlite-init.php: always verify with `php -l includes/sqlite-init.php` after editing. Each block must be self-contained: opening `$pdo->exec("`, SQL body, closing `");` — no split across functions.
