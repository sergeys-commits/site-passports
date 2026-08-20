# Site Passports — Current State
_Обновляется после каждой завершённой задачи_

---

## Последнее обновление
Date: 2026-08-20
Task: Servers + DeployTheme v2 + scenarios A/B

---

## Сделано

### Servers registry (DONE)
- Model/table `servers` (local|ssh, panel isp|hestia|none, wp_sites_root, SSH key path)
- UI CRUD `/servers` + Check connection
- Seed: Local (current host) via `ServerSeeder`

### Theme pipeline v2 (DONE)
- Site pins fields + `site_targets` + `theme_artifacts`
- `CreateSiteService` scenarios A (`stage_then_prod`) / B (`prod_basic_auth`)
- UI: `/sites/create-pipeline`
- `DeployThemeService` + `DeployThemeJob` (queue): resolve ref → npm build with pins → artifact cache → upload → activate → smoke
- Theme update router: tags `1.*` → legacy `update_theme_*.sh`; `2+` → DeployTheme (first run migrates pins)
- `ProvisionWordPressService` + job: WP install with `--skip-theme=1` then DeployTheme (local Server)
- Promote v2: after content promote, ensure production target + DeployTheme same `theme_git_ref`
- Legacy scripts retained

### Stage Provision / Promote / Theme Update (legacy) — still DONE
- Previous bash + Laravel flows unchanged for non-pipeline / 1.x

---

## В процессе

_ничего_

---

## Не начато

- Remote SSH full WP provision (upload/DeployTheme OK; WP core install on remote TBD)
- BulkDeployTheme / Rollback UI
- Auto docroot in ISP/Hestia
- Polling UI / GitHub tags API
- Auto-delete old `wp-theme-core` after migration
- Basic auth remove as first-class server op

---

## Схема БД — текущее состояние

### Новые таблицы
- `servers`
- `site_targets`
- `theme_artifacts`

### sites — новые поля
- pins: `brand_key`, `site_salt`, `profile_id`, `profile_revision`, `public_token`, `theme_slug`
- `theme_git_ref`, `last_build_meta`, `lifecycle`, `scenario`, `profile_pipeline_enabled`

---

## .env переменные (актуальные)
```
THEME_REPO=git@github.com:sergeys-commits/wp-theme-core.git
THEME_SRC_PATH=storage/app/theme-src
THEME_ARTIFACTS_PATH=storage/app/theme-artifacts
QUEUE_CONNECTION=database
WP_SITES_ROOT=/var/www/www-root/data/www
(+ legacy STAGE_PROVISION_*, PROMOTE_*, THEME_UPDATE_* scripts)
```

---

## Инфраструктура — важные детали

### Queue
- v2 provision / DeployTheme: `php artisan queue:work`
- Node 20+ on panel for `npm ci && npm run build`

### SSH для www-root (GitHub доступ)
- Ключ: `/var/www/www-root/data/.ssh/id_ed25519_github_account`
- Config: `/var/www/www-root/data/.ssh/config` → `Host github.com`
- HOME у www-root: `/var/www/www-root/data/`

### Docroot
- ISP (current) / Hestia (new servers) — manual create before provision

---

## Acceptance checklist (manual)
- [ ] Server CRUD + local check OK
- [ ] Create scenario A site + pins/targets
- [ ] Create scenario B site + basic_auth target
- [ ] Theme update `1.x` still uses legacy scripts
- [ ] Theme update `2.x` queues DeployTheme; migrates pins on first run
- [ ] Provision pipeline with skip-theme + DeployTheme on local
- [ ] Promote pipeline site deploys same artifact to production

---

## Известные проблемы
- Деплой sync для legacy; v2 async via queue
- `env()` в части Promote paths — предпочтительно config()
- Remote provision WP not automated yet
