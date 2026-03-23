#!/usr/bin/env bash
set -euo pipefail

SITE_DOMAIN=""
THEME_NAME=""
TARGET_VERSION=""

for arg in "$@"; do
  case "$arg" in
    --site-domain=*) SITE_DOMAIN="${arg#*=}" ;;
    --theme-name=*) THEME_NAME="${arg#*=}" ;;
    --target-version=*) TARGET_VERSION="${arg#*=}" ;;
  esac
done

SITES_ROOT="${WP_SITES_ROOT:-/var/www/www-root/data/www}"
SITE_USER="${WP_SITE_USER:-www-root}"

log() { echo "[$(date '+%H:%M:%S')] $*"; }

emit_json_error() {
  local msg="$1"
  printf '%s\n' "{\"status\":\"error\",\"message\":\"${msg//\"/\\\"}\"}"
  exit 1
}

[[ -n "$SITE_DOMAIN" ]] || emit_json_error "site-domain is required"
[[ -n "$THEME_NAME" ]] || emit_json_error "theme-name is required"
[[ -n "$TARGET_VERSION" ]] || emit_json_error "target-version is required"

SITE_DIR="${SITES_ROOT}/${SITE_DOMAIN}"
THEME_PATH="${SITE_DIR}/wp-content/themes/${THEME_NAME}"

log "=== Theme update live: ${SITE_DOMAIN} / ${THEME_NAME} → ${TARGET_VERSION} ==="
log "site_dir=${SITE_DIR}"
log "theme_path=${THEME_PATH}"

[[ -d "$SITE_DIR" ]] || emit_json_error "site directory not found"
log "OK: site directory exists"

[[ -d "$THEME_PATH" ]] || emit_json_error "theme directory not found"
log "OK: theme directory exists"

git -C "$THEME_PATH" rev-parse --git-dir >/dev/null 2>&1 || emit_json_error "theme path is not a git repository"
log "OK: git repository"

previous_version="$(git -C "$THEME_PATH" describe --tags --exact-match 2>/dev/null || git -C "$THEME_PATH" rev-parse --short HEAD)"
log "previous_version=${previous_version}"

log "git fetch --tags origin"
git -C "$THEME_PATH" fetch --tags origin

if [[ "$TARGET_VERSION" == "latest" ]]; then
  log "git pull origin main"
  git -C "$THEME_PATH" pull origin main
else
  log "git checkout ${TARGET_VERSION}"
  git -C "$THEME_PATH" checkout "$TARGET_VERSION"
fi

deployed_version="$(git -C "$THEME_PATH" describe --tags --exact-match 2>/dev/null || git -C "$THEME_PATH" rev-parse --short HEAD)"
log "deployed_version=${deployed_version}"

log "chown -R ${SITE_USER}:${SITE_USER} ${THEME_PATH}"
chown -R "${SITE_USER}:${SITE_USER}" "$THEME_PATH"

log "theme update completed"
printf '%s\n' "{\"status\":\"success\",\"version\":\"${deployed_version}\",\"previous_version\":\"${previous_version}\"}"
exit 0
