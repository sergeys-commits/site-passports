#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/www-root/data/www/passport-stage.narniapanel.top"
APP_URL="https://passport-stage.narniapanel.top"

DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_DATABASE="stage_pass"
DB_USERNAME="stage_pass"
DB_PASSWORD="gN3tG2sP1c"

ADMIN_NAME="Sergey"
ADMIN_EMAIL="admin@narniapanel.top"
ADMIN_PASSWORD="ChangeMe_Strong_123!"

cd "$APP_DIR"

echo "== 1) Ensure env =="
cp -n .env.example .env || true

sed -i "s/^APP_ENV=.*/APP_ENV=production/" .env
sed -i "s/^APP_DEBUG=.*/APP_DEBUG=false/" .env
sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env

sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
sed -i "s/^DB_HOST=.*/DB_HOST=${DB_HOST}/" .env
sed -i "s/^DB_PORT=.*/DB_PORT=${DB_PORT}/" .env
sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${DB_DATABASE}/" .env
sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME}/" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env

php artisan key:generate --force

echo "== 2) Migrations =="
php artisan migrate --force

echo "== 3) Login-only hardening (disable registration routes) =="
if grep -q "Route::get('register'" routes/auth.php; then
sed -i "/Route::get('register'/ s/^/\/\/ /" routes/auth.php
fi
if grep -q "Route::post('register'" routes/auth.php; then
sed -i "/Route::post('register'/ s/^/\/\/ /" routes/auth.php
fi

echo "== 4) Cache reset =="
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "== 5) Ensure admin user =="
php artisan tinker --execute="
\$u = \App\Models\User::where('email', '${ADMIN_EMAIL}')->first();
if (!\$u) {
\App\Models\User::create([
'name' => '${ADMIN_NAME}',
'email' => '${ADMIN_EMAIL}',
'password' => bcrypt('${ADMIN_PASSWORD}')
]);
echo 'Admin created';
} else {
echo 'Admin exists';
}
"

echo "== 6) Runtime perms =="
chown -R www-root:www-root storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

echo "== 7) Smoke checks =="
php artisan route:list | grep -E "login|register" || true
echo "APP_URL=$(grep '^APP_URL=' .env)"
echo "Done."
