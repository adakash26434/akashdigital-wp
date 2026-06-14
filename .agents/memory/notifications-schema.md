---
name: Notifications table canonical schema
description: notifications table uses seen_at (NULL = unread); old is_read/read_at columns removed
---

**Canonical column:** `seen_at DATETIME DEFAULT NULL`
- `seen_at IS NULL` = unread
- `seen_at = <timestamp>` = read

**All query sites use `seen_at IS NULL`:**
- `includes/notify.php` (unread count + mark-read)
- `includes/portal-layout.php` (bell badge)
- `portal/notifications.php` (list view)
- `portal/index.php` (dashboard count — was `is_read=0`, now fixed)

**Why:** database.sql had two conflicting `notifications` CREATE TABLE blocks — line ~483 used `is_read/read_at/priority`, line ~700 used `seen_at`. MySQL only created the first (IF NOT EXISTS), SQLite created the second. The duplicate was removed; only the `seen_at` version remains (matches 3 of 4 call sites, simpler schema).
