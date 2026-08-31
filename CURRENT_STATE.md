# Site Passports — Current State
_Обновляется после каждой завершённой задачи_

---

## Последнее обновление
Date: 2026-08-31
Task: Themes registry + server onboarding UX (select theme/ref, split servers, promote server)

---

## Сделано

### Themes registry (DONE)
- Table `themes` + `sites.theme_id` FK
- Model/CRUD UI `/themes` (green CTAs), nav link
- Seed: `wp-theme-core` from `THEME_REPO` / config (`ThemeSeeder`)
- `ThemeRefResolver` multi-repo (per-theme clone path + `listSemverTags`)
- DeployTheme / ThemeBuild: resolve site theme, cache_key includes `theme_id`+slug
- Create pipeline: theme select + git ref datalist (tags)
- Theme update: filter by theme + tags datalist

### Servers UX (DONE)
- Green `+ Add server` / `Create` / `Save` buttons
- Onboarding checklist on create/show
- Create pipeline: staging + production server (scenario A) or single server (B)
- Remote SSH: provision_now soft-disabled + warning
- Promote: production `server_id` select (defaults to staging server)

### Servers registry (DONE — earlier)
- Model/table `servers` (local|ssh, panel isp|hestia|none, wp_sites_root, SSH key path)
- UI CRUD `/servers` + Check connection
- Seed: Local (current host) via `ServerSeeder`

### Theme pipeline v2 (DONE — earlier)
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
- GitHub tags API (local git tags via ThemeRefResolver used instead)
- Auto-delete old `wp-theme-core` after migration
- Basic auth remove as first-class server op

---

## Схема БД — текущее состояние

### Новые таблицы
- `servers`
- `site_targets`
- `theme_artifacts`
- `themes`

### sites — новые поля
- pins: `brand_key`, `site_salt`, `profile_id`, `profile_revision`, `public_token`, `theme_slug`
- `theme_id` (FK themes, nullable for legacy)
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

`THEME_REPO` остаётся fallback / seed default; SoT для пайплайна — запись в `themes`.

---

## Инфраструктура — важные детали

### Queue
- v2 provision / DeployTheme: systemd unit `passport-queue.service` (см. `deploy/QUEUE_WORKER.md`)
- Node 20+ on panel for `npm ci && npm run build`

### SSH для www-root (GitHub доступ)
- Ключ: `/var/www/www-root/data/.ssh/id_ed25519_github_account`
- Config: `/var/www/www-root/data/.ssh/config` → `Host github.com`
- HOME у www-root: `/var/www/www-root/data/`

### Docroot
- ISP (current) / Hestia (new servers) — manual create before provision

### Добавление remote-сервера
1. Ключ на панели → `authorized_keys` на remote → `wp_sites_root`
2. Servers → Create → Check connection
3. Docroot домена вручную в панели хостинга
4. Pipeline site с выбором сервера(ов)
5. Remote: WP вручную, затем DeployTheme (provision WP на SSH ещё не автоматизирован)

---

## Acceptance checklist (manual)
- [ ] Theme CRUD + seed default theme
- [ ] Create pipeline: choose theme + git ref tags + staging/prod servers
- [ ] Theme update: filter by theme, tags from selected theme
- [ ] Server green CTAs visible; checklist on create/show
- [ ] Promote: choose production server
- [ ] Remote SSH: provision_now disabled with warning
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
