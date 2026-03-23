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

log "=== Theme update dry run: ${SITE_DOMAIN} / ${THEME_NAME} → ${TARGET_VERSION} ==="
log "site_dir=${SITE_DIR}"
log "theme_path=${THEME_PATH}"
log "wp_site_user=${SITE_USER} (not used in dry run)"

[[ -d "$SITE_DIR" ]] || emit_json_error "site directory not found"
log "OK: site directory exists"

[[ -d "$THEME_PATH" ]] || emit_json_error "theme directory not found"
log "OK: theme directory exists"

git -C "$THEME_PATH" rev-parse --git-dir >/dev/null 2>&1 || emit_json_error "theme path is not a git repository"
log "OK: git repository"

current_version="$(git -C "$THEME_PATH" describe --tags --exact-match 2>/dev/null || git -C "$THEME_PATH" rev-parse --short HEAD)"
log "current_version=${current_version}"

if [[ "$TARGET_VERSION" != "latest" ]]; then
  log "checking remote tag refs/tags/${TARGET_VERSION}..."
  if ! git -C "$THEME_PATH" ls-remote --tags origin "refs/tags/${TARGET_VERSION}" | grep -q .; then
    emit_json_error "tag not found on remote: ${TARGET_VERSION}"
  fi
  log "OK: tag exists on remote"
else
  log "target is latest (no remote tag check in dry run)"
fi

log "dry run checks completed"
printf '%s\n' "{\"status\":\"success\",\"current_version\":\"${current_version}\",\"target_version\":\"${TARGET_VERSION}\"}"
exit 0
