#!/usr/bin/env bash
set -euo pipefail

MODE=""
STAGE_DOMAIN=""
DOMAIN=""
DB_HOST=""
DB_ROOT_PASS=""
SITES_ROOT=""
WP_SITE_USER=""

for arg in "$@"; do
  case "$arg" in
    --mode=*) MODE="${arg#*=}" ;;
    --stage-domain=*) STAGE_DOMAIN="${arg#*=}" ;;
    --domain=*) DOMAIN="${arg#*=}" ;;
    --db-host=*) DB_HOST="${arg#*=}" ;;
    --db-root-password=*) DB_ROOT_PASS="${arg#*=}" ;;
    --wp-sites-root=*) SITES_ROOT="${arg#*=}" ;;
    --wp-site-user=*) WP_SITE_USER="${arg#*=}" ;;
  esac
done

log() { echo "[$(date '+%H:%M:%S')] $*"; }
fail() { echo "[ERROR] $*" >&2; exit 1; }

[[ -n "$STAGE_DOMAIN" ]] || fail "--stage-domain is required"
[[ -n "$DOMAIN" ]] || fail "--domain (production) is required"
[[ -n "$SITES_ROOT" ]] || fail "--wp-sites-root is required"
[[ -n "$WP_SITE_USER" ]] || fail "--wp-site-user is required"

DB_ROOT_USER="${WP_DB_ROOT_USER:-root}"
DB_HOST_ONLY="${DB_HOST%%:*}"
DB_PORT="${DB_HOST##*:}"
[[ "$DB_PORT" == "$DB_HOST" ]] && DB_PORT="3306"

STAGE_DIR="${SITES_ROOT}/${STAGE_DOMAIN}"
PROD_DIR="${SITES_ROOT}/${DOMAIN}"
STAGE_DB="wp_$(echo "${STAGE_DOMAIN}" | tr '.-' '_' | cut -c1-50)"
PROD_DB="wp_$(echo "${DOMAIN}" | tr '.-' '_' | cut -c1-50)"
PROD_USER="$(echo "${PROD_DB}" | cut -c1-32)"
PROD_PASS="$(openssl rand -base64 18 | tr -dc 'a-zA-Z0-9' | head -c 24)"

mysql_exec() {
  mysql -u"${DB_ROOT_USER}" ${DB_ROOT_PASS:+-p"${DB_ROOT_PASS}"} -h"${DB_HOST_ONLY}" -P"${DB_PORT}" "$@"
}

log "=== Promote to production: ${STAGE_DOMAIN} → ${DOMAIN} ==="

log "Preflight: stage dir ${STAGE_DIR}"
[[ -d "$STAGE_DIR" ]] || fail "Stage directory not found: ${STAGE_DIR}"

log "Preflight: prod dir ${PROD_DIR}"
[[ -d "$PROD_DIR" ]] || fail "Production directory not found: ${PROD_DIR}. Create domain in ISPManager first."

log "Preflight: stage database ${STAGE_DB}"
if ! mysql_exec -e "USE \`${STAGE_DB}\`" >/dev/null 2>&1; then
  fail "Stage database not accessible or missing: ${STAGE_DB}"
fi

log "Step 2: rsync stage → prod (excluding wp-config.php)"
rsync -a --delete --exclude='wp-config.php' "${STAGE_DIR}/" "${PROD_DIR}/"

log "Step 3: create production database and user"
mysql_exec <<SQL
CREATE DATABASE IF NOT EXISTS \`${PROD_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${PROD_USER}'@'localhost' IDENTIFIED BY '${PROD_PASS}';
GRANT ALL PRIVILEGES ON \`${PROD_DB}\`.* TO '${PROD_USER}'@'localhost';
CREATE USER IF NOT EXISTS '${PROD_USER}'@'127.0.0.1' IDENTIFIED BY '${PROD_PASS}';
GRANT ALL PRIVILEGES ON \`${PROD_DB}\`.* TO '${PROD_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

DUMP="$(mktemp)"
trap 'rm -f "${DUMP}"' EXIT

log "Step 4: dump stage DB → import prod DB"
mysqldump -u"${DB_ROOT_USER}" ${DB_ROOT_PASS:+-p"${DB_ROOT_PASS}"} -h"${DB_HOST_ONLY}" -P"${DB_PORT}" \
  "${STAGE_DB}" > "${DUMP}"
mysql -u"${DB_ROOT_USER}" ${DB_ROOT_PASS:+-p"${DB_ROOT_PASS}"} -h"${DB_HOST_ONLY}" -P"${DB_PORT}" \
  "${PROD_DB}" < "${DUMP}"
rm -f "${DUMP}"
trap - EXIT

log "Step 5: wp-config.php for production"
wp config create \
  --path="${PROD_DIR}" \
  --dbname="${PROD_DB}" \
  --dbuser="${PROD_USER}" \
  --dbpass="${PROD_PASS}" \
  --dbhost="${DB_HOST_ONLY}:${DB_PORT}" \
  --allow-root \
  --force 2>&1

wp config set WP_HOME "https://${DOMAIN}" --type=constant --raw --path="${PROD_DIR}" --allow-root 2>&1
wp config set WP_SITEURL "https://${DOMAIN}" --type=constant --raw --path="${PROD_DIR}" --allow-root 2>&1

log "Step 6: search-replace URLs"
wp search-replace "https://${STAGE_DOMAIN}" "https://${DOMAIN}" \
  --path="${PROD_DIR}" --allow-root --all-tables --skip-columns=guid 2>&1
wp search-replace "http://${STAGE_DOMAIN}" "https://${DOMAIN}" \
  --path="${PROD_DIR}" --allow-root --all-tables --skip-columns=guid 2>&1

log "Step 7: ownership ${WP_SITE_USER}"
chown -R "${WP_SITE_USER}:${WP_SITE_USER}" "${PROD_DIR}"

log "=== Promote complete ==="
echo "{\"status\":\"success\",\"domain\":\"${DOMAIN}\"}"
