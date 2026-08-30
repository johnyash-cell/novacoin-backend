#!/usr/bin/env bash
# One-time / reference checklist for api.novacoinsholdings.com on cPanel.
# Do not put secrets in this file.

set -euo pipefail

PHP83=/opt/alt/php83/usr/bin/php
APP=/home/novamdrw/api.novacoinsholdings.com

echo "1) SSH key authorized (done)"
echo "2) Domains → api.novacoinsholdings.com → Document Root = api.novacoinsholdings.com/public"
echo "3) Domains → Force HTTPS Redirect = On"
echo "4) Select PHP Version / MultiPHP → 8.3 for api.novacoinsholdings.com (web)"
echo "5) Create MySQL DB; on server: cp .env.example .env then edit"
echo "   APP_URL=https://api.novacoinsholdings.com"
echo "6) Once: ${PHP83} ${APP}/artisan key:generate && ${PHP83} ${APP}/artisan jwt:secret"
echo "7) You run: ${PHP83} ${APP}/artisan migrate --force"
echo "8) Cron every minute:"
echo "   cd ${APP} && ${PHP83} artisan schedule:run >> /dev/null 2>&1"
echo "9) Cron every minute (queue/mail):"
echo "   cd ${APP} && ${PHP83} artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1"
echo "10) GitHub Actions secrets: CPANEL_SSH_* (see chat)"
