#!/usr/bin/env bash
set -euo pipefail

NAME=""
STAGE_DOMAIN=""

for arg in "$@"; do
case "$arg" in
--name=*) NAME="${arg#*=}" ;;
--site-name=*) NAME="${arg#*=}" ;; # совместимость с controller
--stage-domain=*) STAGE_DOMAIN="${arg#*=}" ;;
esac
done

echo "[dry-run] stage provision start"
echo "name=${NAME}"
echo "stage_domain=${STAGE_DOMAIN}"
echo "[dry-run] validate pipeline paths..."

# Проверяем путь относительно самого скрипта (без хардкода чужих директорий)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
test -d "$PROJECT_ROOT" || { echo "project root missing"; exit 2; }

echo "[dry-run] OK"
echo "[dry-run] done"
