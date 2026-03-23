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

DB_ROOT_USER="${WP_DB_ROOT_USER:-root}"
DB_HOST_ONLY="${DB_HOST%%:*}"
DB_PORT="${DB_HOST##*:}"
[[ "$DB_PORT" == "$DB_HOST" ]] && DB_PORT="3306"

STAGE_DIR="${SITES_ROOT}/${STAGE_DOMAIN}"
PROD_DIR="${SITES_ROOT}/${DOMAIN}"
STAGE_DB="wp_$(echo "${STAGE_DOMAIN}" | tr '.-' '_' | cut -c1-50)"

mysql_exec() {
  mysql -u"${DB_ROOT_USER}" ${DB_ROOT_PASS:+-p"${DB_ROOT_PASS}"} -h"${DB_HOST_ONLY}" -P"${DB_PORT}" "$@"
}

log "Preflight: stage dir ${STAGE_DIR}"
[[ -d "$STAGE_DIR" ]] || fail "Stage directory not found: ${STAGE_DIR}"

log "Preflight: prod dir ${PROD_DIR}"
[[ -d "$PROD_DIR" ]] || fail "Production directory not found: ${PROD_DIR}. Create domain in ISPManager first."

log "Preflight: stage database ${STAGE_DB}"
if ! mysql_exec -e "USE \`${STAGE_DB}\`" >/dev/null 2>&1; then
  fail "Stage database not accessible or missing: ${STAGE_DB}"
fi

log "[dry_run] All checks passed"
echo "{\"status\":\"success\",\"domain\":\"${DOMAIN}\",\"mode\":\"dry_run\"}"
