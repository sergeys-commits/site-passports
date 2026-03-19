#!/usr/bin/env bash
set -euo pipefail

# ─── Аргументы ───────────────────────────────────────────────
MODE=""
SITE_NAME=""
DOMAIN=""
STAGE_DOMAIN=""
CMS="wordpress"
TEMPLATE="default"
SERVER_HOST="local"

for arg in "$@"; do
  case "$arg" in
    --mode=*)         MODE="${arg#*=}" ;;
    --site-name=*)    SITE_NAME="${arg#*=}" ;;
    --domain=*)       DOMAIN="${arg#*=}" ;;
    --stage-domain=*) STAGE_DOMAIN="${arg#*=}" ;;
    --cms=*)          CMS="${arg#*=}" ;;
    --template=*)     TEMPLATE="${arg#*=}" ;;
    --server-host=*)  SERVER_HOST="${arg#*=}" ;;
  esac
done

# ─── Конфиг ──────────────────────────────────────────────────
SITES_ROOT="${WP_SITES_ROOT:-/var/www/www-root/data/www}"
ASSETS_PATH="${WP_ASSETS_PATH:-/var/www/www-root/data/www/passport-stage.narniapanel.top/wp-assets}"
DB_HOST="${WP_DB_HOST:-127.0.0.1:3306}"
DB_HOST_ONLY="${DB_HOST%%:*}"
DB_PORT="${DB_HOST##*:}"
DB_ROOT_USER="${WP_DB_ROOT_USER:-root}"
DB_ROOT_PASS="${WP_DB_ROOT_PASSWORD:-}"
SITE_USER="${WP_SITE_USER:-www-root}"
THEME_REPO="${THEME_REPO:-}"

# ─── Валидация аргументов ────────────────────────────────────
log() { echo "[$(date '+%H:%M:%S')] $*"; }
fail() { echo "[ERROR] $*" >&2; exit 1; }

[[ -z "$STAGE_DOMAIN" ]] && fail "--stage-domain is required"
[[ -z "$SITE_NAME" ]]    && fail "--site-name is required"

# ─── Пути ────────────────────────────────────────────────────
SITE_DIR="${SITES_ROOT}/${STAGE_DOMAIN}"
DB_NAME="wp_$(echo "${STAGE_DOMAIN}" | tr '.-' '_' | cut -c1-50)"
DB_USER="$(echo "${DB_NAME}" | cut -c1-32)"
DB_PASS="$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20)"

log "=== Stage Provision: ${STAGE_DOMAIN} ==="
log "site_dir=${SITE_DIR}"
log "db_name=${DB_NAME}"
log "mode=${MODE}"

# ─── DRY RUN ─────────────────────────────────────────────────
if [[ "$MODE" == "dry_run" ]]; then
  log "[dry-run] Checking site directory..."
  [[ -d "$SITE_DIR" ]] && log "[dry-run] OK: site dir exists" || log "[dry-run] WARN: site dir missing — create domain in ISPManager first"
  log "[dry-run] Checking WP-CLI..."
  wp --info --allow-root > /dev/null 2>&1 && log "[dry-run] OK: wp-cli found" || fail "wp-cli not found"
  log "[dry-run] Checking MySQL..."
  mysql -u"${DB_ROOT_USER}" ${DB_ROOT_PASS:+-p"${DB_ROOT_PASS}"} -e "SELECT 1;" > /dev/null 2>&1 && log "[dry-run] OK: mysql access" || fail "mysql access failed"
  log "[dry-run] All checks passed"
  echo '{"status":"success","mode":"dry_run","stage_domain":"'"${STAGE_DOMAIN}"'"}'
  exit 0
fi

# ─── LIVE ────────────────────────────────────────────────────

# 1. Проверяем что домен создан в ISPManager
log "Checking site directory..."
[[ -d "$SITE_DIR" ]] || fail "Site directory ${SITE_DIR} not found. Create domain in ISPManager first."
log "OK: ${SITE_DIR}"

# 2. Создаём БД и пользователя
log "Creating database ${DB_NAME}..."
mysql -u"${DB_ROOT_USER}" -p"${DB_ROOT_PASS}" 2>/dev/null << SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
log "OK: database created"

# 3. Скачиваем WordPress
log "Downloading WordPress..."
wp core download \
  --path="${SITE_DIR}" \
  --locale=ru_RU \
  --allow-root \
  --force 2>&1
log "OK: WordPress downloaded"

# 4. Создаём wp-config
log "Creating wp-config..."
wp config create \
  --path="${SITE_DIR}" \
  --dbname="${DB_NAME}" \
  --dbuser="${DB_USER}" \
  --dbpass="${DB_PASS}" \
  --dbhost="${DB_HOST_ONLY}:${DB_PORT}" \
  --allow-root \
  --force 2>&1
log "OK: wp-config created"

# 5. Устанавливаем WordPress
log "Installing WordPress..."
WP_ADMIN_PASS="$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9' | head -c 16)"
wp core install \
  --path="${SITE_DIR}" \
  --url="https://${STAGE_DOMAIN}" \
  --title="${SITE_NAME}" \
  --admin_user="admin" \
  --admin_password="${WP_ADMIN_PASS}" \
  --admin_email="admin@${STAGE_DOMAIN}" \
  --skip-email \
  --allow-root 2>&1
log "OK: WordPress installed"
log "admin_password=${WP_ADMIN_PASS}"

# 6. Устанавливаем плагины
log "Installing plugins..."
PLUGINS_DIR="${ASSETS_PATH}/plugins"

if [[ -d "$PLUGINS_DIR" ]]; then
  for zip_file in "${PLUGINS_DIR}"/*.zip; do
    [[ -f "$zip_file" ]] || continue
    log "Installing plugin from zip: $(basename ${zip_file})..."
    wp plugin install "${zip_file}" --activate --allow-root --force --path="${SITE_DIR}" 2>&1
    log "OK: $(basename ${zip_file})"
  done
fi

# Плагины с wordpress.org — список из env или дефолт
WP_PLUGINS="${WP_DEFAULT_PLUGINS:-contact-form-7,yoast-seo}"
if [[ -n "$WP_PLUGINS" ]]; then
  IFS=',' read -ra PLUGIN_LIST <<< "$WP_PLUGINS"
  for plugin in "${PLUGIN_LIST[@]}"; do
    plugin="$(echo $plugin | tr -d ' ')"
    [[ -z "$plugin" ]] && continue
    log "Installing plugin: ${plugin}..."
    wp plugin install "${plugin}" --activate --allow-root --path="${SITE_DIR}" 2>&1
    log "OK: ${plugin}"
  done
fi

# 7. Устанавливаем тему
log "Installing theme..."
if [[ -n "$THEME_REPO" ]]; then
  THEME_DIR="${SITE_DIR}/wp-content/themes/wp-theme-core"
  rm -rf "${THEME_DIR}"
  GIT_SSH_COMMAND="ssh -i /var/www/.ssh/deploy_theme -o StrictHostKeyChecking=no" git clone "${THEME_REPO}" "${THEME_DIR}" 2>&1
  THEME_SLUG="$(basename ${THEME_DIR})"
  wp theme activate "${THEME_SLUG}" --allow-root --path="${SITE_DIR}" 2>&1
  log "OK: theme from git activated"
elif [[ -d "${ASSETS_PATH}/themes" ]] && [[ -n "$(ls -A ${ASSETS_PATH}/themes 2>/dev/null)" ]]; then
  for theme_zip in "${ASSETS_PATH}/themes"/*.zip; do
    [[ -f "$theme_zip" ]] || continue
    wp theme install "${theme_zip}" --activate --allow-root --path="${SITE_DIR}" 2>&1
    log "OK: theme from zip activated"
    break
  done
else
  log "WARN: no theme configured, using default"
fi

# 8. Выставляем права
log "Setting permissions..."
chown -R "${SITE_USER}:${SITE_USER}" "${SITE_DIR}"
find "${SITE_DIR}" -type d -exec chmod 755 {} \;
find "${SITE_DIR}" -type f -exec chmod 644 {} \;
log "OK: permissions set"

log "=== Provision complete ==="

# Финальный JSON — обязательная последняя строка
echo '{"status":"success","stage_domain":"'"${STAGE_DOMAIN}"'","db_name":"'"${DB_NAME}"'","admin_password":"'"${WP_ADMIN_PASS}"'"}'
