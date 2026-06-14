---
name: M15 index schema mismatch
description: Migration 15 CREATE INDEX statements used wrong column names for tickets, client_subscriptions, and users tables
---

# Migration 15 Index Schema Mismatch

## The rule
The SQLite schema (sqlite-init.php) for key tables differs from MySQL conventions assumed in db-migrations.php:
- `tickets` — uses `user_id`, **not** `client_id`
- `client_subscriptions` — uses `user_id`, **not** `client_id`
- `users` — uses `active` (INTEGER 0/1), **not** `status`

## Why
M15 originally tried to create `idx_tickets_client_id ON tickets(client_id)` etc., referencing columns that don't exist in the SQLite schema. This caused the entire try/catch block to fail on the first bad index, leaving all subsequent indexes uncreated.

## How to apply
M15 was rewritten to loop over indexes individually (each in its own inner try/catch) so one failure doesn't abort the rest. When adding new indexes in future migrations, always verify column names against the sqlite-init.php schema with `PRAGMA table_info(tablename)` before committing.
