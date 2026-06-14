---
name: SQLite migration patterns
description: How to write idempotent SQLite migrations without log spam
---

## Rules

1. **No AFTER keyword** — SQLite ALTER TABLE does not support `AFTER column_name`. MySQL-only. Columns always append to end.

2. **Suppress expected errors** — When running idempotent `ALTER TABLE ... ADD COLUMN` migrations, always check for "duplicate column" and "already exists" before calling error_log:
   ```php
   } catch (\Throwable $e) {
       $msg = $e->getMessage();
       if (strpos($msg, 'duplicate column') === false && strpos($msg, 'already exists') === false) {
           error_log('[' . basename(__FILE__) . ']' . $msg);
       }
   }
   ```

**Why:** Without these guards, every page request generates 15+ log lines of "duplicate column name: X" once the schema is initialised, which drowns out real errors.

**How to apply:** Check every catch block in sqlite-init.php's ALTER TABLE loops.
