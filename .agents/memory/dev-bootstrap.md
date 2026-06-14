---
name: Dev environment bootstrap
description: The Replit dev environment requires specific env vars set before the app will boot
---

# Dev Environment Bootstrap Requirements

## Required env vars (Replit development environment)
| Key | Where | Value |
|-----|-------|-------|
| `APP_ENV` | Replit dev env var | `development` |
| `SUPERADMIN_EMAIL` | Replit dev env var | `dev@localhost.dev` (or any email) |
| `SUPERADMIN_PASS_PLAIN` | Replit dev env var | a dev password |

## Why
- `dev-config.php` is only loaded when `APP_ENV=development` is already set as an OS env var (checked via `getenv('APP_ENV')` before the file is included). Without it, `APP_SECRET_KEY` never gets defined and the app shows a fatal setup screen.
- `includes/superadmin.php` requires `SUPERADMIN_EMAIL` + either `SUPERADMIN_PASS_HASH` or `SUPERADMIN_PASS_PLAIN`; missing either causes a 500 fatal on every page.
- `dev-config.php` defines `APP_SECRET_KEY` as a static dev hex string — safe for dev, never use in production.

## How to apply
These are already set in the Replit `development` env vars scope via `setEnvVars()`. If the DB is deleted or the env is reset, re-set these three vars before restarting the workflow.
