#!/bin/bash
# Run on cPanel SSH: bash scripts/cpanel-update.sh
# Updates code from GitHub main while keeping production secrets safe.

set -e
cd "$(dirname "$0")/.."

echo "=== 1. Backup production config (if exists) ==="
if [ -f includes/config-production.php ]; then
  cp includes/config-production.php ~/config-production.backup.php
  echo "Backed up to ~/config-production.backup.php"
fi

echo "=== 2. Reset config.php to git version (fixes pull conflicts) ==="
git checkout origin/main -- includes/config.php 2>/dev/null || true

echo "=== 3. Pull latest main ==="
git pull origin main

echo "=== 4. Restore production config ==="
if [ -f ~/config-production.backup.php ]; then
  cp ~/config-production.backup.php includes/config-production.php
  echo "Restored includes/config-production.php"
else
  echo "WARNING: No includes/config-production.php found!"
  echo "Create it from: cp includes/config-production.php.example includes/config-production.php"
fi

echo "=== 5. Verify careers page ==="
php -l careers.php
php -l includes/helpers.php

echo "=== 6. Git status ==="
git log -1 --oneline
echo "Done. Test: https://aakashdigital.com.np/careers.php"
