#!/bin/bash
# Live server update — run: bash deploy.sh
cd "$(dirname "$0")"

echo ">>> Backup secrets..."
[ -f includes/config-production.php ] && cp includes/config-production.php ~/config-production.backup.php

echo ">>> Pull latest code..."
git checkout origin/main -- includes/config.php 2>/dev/null || true
git pull origin main

echo ">>> Restore secrets..."
[ -f ~/config-production.backup.php ] && cp ~/config-production.backup.php includes/config-production.php

echo ">>> Setup uploads..."
mkdir -p uploads/applications && chmod 755 uploads/applications

echo ">>> Check..."
git log -1 --oneline
php -l careers.php

echo ""
echo "Done! Test: https://aakashdigital.com.np/careers.php"
echo "First time? Create includes/config-production.php from includes/config-production.php.example"
