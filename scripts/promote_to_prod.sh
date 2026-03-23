#!/usr/bin/env bash
set -euo pipefail

for arg in "$@"; do
  case $arg in
    --mode=*)             MODE="${arg#*=}" ;;
    --stage-domain=*)     STAGE_DOMAIN="${arg#*=}" ;;
    --domain=*)           PROD_DOMAIN="${arg#*=}" ;;
    --db-host=*)          DB_HOST="${arg#*=}" ;;
    --db-root-password=*) DB_ROOT_PASS="${arg#*=}" ;;
    --wp-sites-root=*)    WP_SITES_ROOT="${arg#*=}" ;;
    --wp-site-user=*)     WP_SITE_USER="${arg#*=}" ;;
  esac
done

DB_HOST_IP="${DB_HOST%:*}"
DB_HOST_PORT="${DB_HOST#*:}"
STAGE_DIR="${WP_SITES_ROOT}/${STAGE_DOMAIN}"
PROD_DIR="${WP_SITES_ROOT}/${PROD_DOMAIN}"
STAGE_DB=$(echo "${STAGE_DOMAIN}" | tr '.-' '_' | cut -c1-64)
PROD_DB=$(echo "${PROD_DOMAIN}"   | tr '.-' '_' | cut -c1-64)
PROD_DB_USER=$(echo "${PROD_DOMAIN}" | tr '.-' '_' | cut -c1-32)
PROD_DB_PASS=$(openssl rand -base64 18 | tr -dc 'a-zA-Z0-9' | head -c24)

MYSQL="mysql -h${DB_HOST_IP} -P${DB_HOST_PORT} -uroot -p${DB_ROOT_PASS}"

# ── Step 1: Preflight ────────────────────────────────────────
echo "[1/7] Preflight checks"
[ -d "${STAGE_DIR}" ] || { echo "[ERROR] Stage dir not found: ${STAGE_DIR}" >&2; exit 1; }
[ -d "${PROD_DIR}" ]  || { echo "[ERROR] Prod dir not found: ${PROD_DIR} — create in ISPManager" >&2; exit 1; }

$MYSQL -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${STAGE_DB}';" 2>/dev/null \
  | grep -q "${STAGE_DB}" \
  || { echo "[ERROR] Stage DB not found: ${STAGE_DB}" >&2; exit 1; }

# ── Step 2: rsync ────────────────────────────────────────────
echo "[2/7] Syncing files stage → prod"
rsync -a --delete \
  --exclude='wp-config.php' \
  "${STAGE_DIR}/" "${PROD_DIR}/"

# ── Step 3: Create prod DB ───────────────────────────────────
echo "[3/7] Creating prod database: ${PROD_DB}"
$MYSQL -e "CREATE DATABASE IF NOT EXISTS \`${PROD_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
$MYSQL -e "CREATE USER IF NOT EXISTS '${PROD_DB_USER}'@'localhost' IDENTIFIED BY '${PROD_DB_PASS}';" 2>/dev/null
$MYSQL -e "CREATE USER IF NOT EXISTS '${PROD_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${PROD_DB_PASS}';" 2>/dev/null
$MYSQL -e "GRANT ALL PRIVILEGES ON \`${PROD_DB}\`.* TO '${PROD_DB_USER}'@'localhost';" 2>/dev/null
$MYSQL -e "GRANT ALL PRIVILEGES ON \`${PROD_DB}\`.* TO '${PROD_DB_USER}'@'127.0.0.1';" 2>/dev/null
$MYSQL -e "FLUSH PRIVILEGES;" 2>/dev/null

# ── Step 4: Dump & import ────────────────────────────────────
echo "[4/7] Dumping stage DB and importing to prod"
TMP_DUMP="/tmp/promote_${STAGE_DB}_$(date +%s).sql"
mysqldump -h"${DB_HOST_IP}" -P"${DB_HOST_PORT}" -uroot -p"${DB_ROOT_PASS}" \
  "${STAGE_DB}" > "${TMP_DUMP}" 2>/dev/null
$MYSQL "${PROD_DB}" < "${TMP_DUMP}" 2>/dev/null
rm -f "${TMP_DUMP}"

# ── Step 5: wp-config ────────────────────────────────────────
echo "[5/7] Writing wp-config.php"
cat > "${PROD_DIR}/wp-config.php" <<EOF
<?php
define('DB_NAME',     '${PROD_DB}');
define('DB_USER',     '${PROD_DB_USER}');
define('DB_PASSWORD', '${PROD_DB_PASS}');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET',  'utf8mb4');
define('DB_COLLATE',  '');
\$table_prefix = 'wp_';
define('WP_DEBUG', false);
define('WP_HOME',   'https://${PROD_DOMAIN}');
define('WP_SITEURL','https://${PROD_DOMAIN}');
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
EOF

# ── Step 6: search-replace ───────────────────────────────────
echo "[6/7] WP search-replace"
wp --path="${PROD_DIR}" --allow-root \
  search-replace "https://${STAGE_DOMAIN}" "https://${PROD_DOMAIN}" \
  --all-tables --skip-columns=guid 2>/dev/null
wp --path="${PROD_DIR}" --allow-root \
  search-replace "http://${STAGE_DOMAIN}" "https://${PROD_DOMAIN}" \
  --all-tables --skip-columns=guid 2>/dev/null

# ── Step 7: Permissions ──────────────────────────────────────
echo "[7/7] Setting permissions"
chown -R "${WP_SITE_USER}:${WP_SITE_USER}" "${PROD_DIR}"

echo "[done] Promotion complete"
echo '{"status":"success","domain":"'"${PROD_DOMAIN}"'"}'
