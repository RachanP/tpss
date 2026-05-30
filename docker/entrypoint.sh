#!/bin/sh
set -e
cd /var/www/html

# --- รอ MySQL พร้อม (mysqladmin ping ตอบ 'alive' แม้ยัง auth ไม่ผ่าน) ---
echo "Waiting for database ${DB_HOST}:${DB_PORT:-3306} ..."
i=0
until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT:-3306}" --silent 2>/dev/null; do
  i=$((i+1))
  if [ "$i" -ge 60 ]; then
    echo "Database not reachable after 120s — aborting." >&2
    exit 1
  fi
  sleep 2
done
echo "Database is up."

# --- .env + APP_KEY (สร้างถ้ายังไม่มี; ค่า DB/APP_URL จริงมาจาก env ของ compose) ---
[ -f .env ] || cp .env.example .env
if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

# --- schema + ข้อมูล ---
php artisan migrate --force
if [ "${DB_SEED:-false}" = "true" ]; then
  echo "Seeding database (DB_SEED=true) ..."
  php artisan db:seed --force
fi

php artisan storage:link 2>/dev/null || true

# --- cache (staging) — เคลียร์ก่อนกัน config เก่าค้าง ---
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
