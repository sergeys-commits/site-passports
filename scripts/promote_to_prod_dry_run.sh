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

echo "[dry_run] Checking stage dir: ${STAGE_DIR}"
[ -d "${STAGE_DIR}" ] || { echo "[ERROR] Stage directory not found: ${STAGE_DIR}" >&2; exit 1; }

echo "[dry_run] Checking prod dir: ${PROD_DIR}"
[ -d "${PROD_DIR}" ] || { echo "[ERROR] Prod directory not found: ${PROD_DIR} — create it in ISPManager first" >&2; exit 1; }

echo "[dry_run] Checking stage DB: ${STAGE_DB}"
mysql -h"${DB_HOST_IP}" -P"${DB_HOST_PORT}" -uroot -p"${DB_ROOT_PASS}" \
  -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${STAGE_DB}';" 2>/dev/null \
  | grep -q "${STAGE_DB}" \
  || { echo "[ERROR] Stage DB not found: ${STAGE_DB}" >&2; exit 1; }

echo "[dry_run] All checks passed"
echo '{"status":"success","domain":"'"${PROD_DOMAIN}"'","mode":"dry_run"}'
