# Site Passports — Current State
_Обновляется после каждой завершённой задачи_

---

## Последнее обновление
Date: 2026-03-24
Task: Theme Update

---

## Сделано

### Stage Provision (DONE)
- Скрипт: `scripts/provision_stage_live.sh` + `provision_stage_dry_run.sh`
- Laravel: `StageProvisionService`, `DeploymentRunGuardService`
- После live success: автоматически создаёт/обновляет Site + SiteEvent
- UI: `/deployments/stage-provision/new`
- Время деплоя: ~35 сек
- Статус: работает в production

### Promote Stage → Production (DONE)
- Скрипт: `scripts/promote_to_prod.sh` + `promote_to_prod_dry_run.sh`
- Laravel: `PromoteToProductionService`, `PromoteToProductionController`
- DTO: `PromoteToProductionData`
- Actions: `UpsertSiteFromDeploymentAction`, `EmitSiteEventAction`
- dry_run: проверяет stage dir, prod dir, stage DB — без side effects
- live: rsync файлов, dump/import БД, wp-config, search-replace, permissions
- После live success: site.status → active, site.domain, site.admin_url обновляются + SiteEvent
- Confirm phrase: пользователь вводит prod domain для подтверждения
- UI: `/deployments/promote/new`
- Время promote: ~10 сек
- Статус: работает в production

### Theme Update (DONE)
- Скрипт: `scripts/update_theme_live.sh` + `update_theme_dry_run.sh`
- Laravel: `ThemeUpdateService`, `ThemeUpdateController`, `ThemeUpdateRequest`
- DTO: `ThemeUpdateData`
- Action: `UpdateSiteThemeVersionAction`
- dry_run: проверяет директорию сайта, тему, git repo, тег в remote — без side effects
- live: git fetch --tags, checkout тега или pull origin main (для latest), chown
- После live success: `sites.theme_version` обновляется + SiteEvent `theme_updated`
- UI: `/deployments/theme-update/new` — чекбоксы сайтов, выбор окружения, поле версии
- Результаты: таблица site | status | version | message | log
- Время обновления: ~4 сек
- Статус: работает в production

---

## В процессе

_ничего_

---

## Не начато

- GitHub API интеграция для списка тегов (GitHubService заготовлен, GITHUB_TOKEN не добавлен)
- Версионирование темы через git tags + автоматическое создание тегов при коммите
- Паспорт — ручное создание сайта
- Queue / async деплой
- Реестр серверов
- Deployment profiles
- SEO интеграция
- Admin gateway

---

## Схема БД — текущее состояние

### Таблицы
- `sites` — паспорта (domain nullable, admin_url, stage_admin_url, wp_admin_password, theme_version)
- `site_events` — история событий
- `deployment_runs` — аудит запусков (action_type: stage_provision | promote_to_prod | theme_update)
- `deployment_logs` — построчные логи
- `deployment_run_guards` — локи параллельных деплоев
- `site_groups` — группы сайтов (+ theme_name, default: wp-theme-core)

### Важные детали схемы
- `sites.status` ENUM: `active`, `stage`, `archived` (active = prod)
- `sites.domain` — nullable, заполняется после promote
- `sites.theme_version` — VARCHAR(100), nullable, commit hash или git tag
- `site_groups.theme_name` — VARCHAR(100), nullable, default `wp-theme-core`
- `deployment_runs.action_type` — VARCHAR(50), не ENUM

---

## .env переменные (актуальные)
```
STAGE_PROVISION_DRY_RUN_SCRIPT=.../scripts/provision_stage_dry_run.sh
STAGE_PROVISION_LIVE_SCRIPT=.../scripts/provision_stage_live.sh
PROMOTE_TO_PROD_DRY_RUN_SCRIPT=.../scripts/promote_to_prod_dry_run.sh
PROMOTE_TO_PROD_LIVE_SCRIPT=.../scripts/promote_to_prod.sh
THEME_UPDATE_DRY_RUN_SCRIPT=/var/www/www-root/data/www/passport-stage.narniapanel.top/scripts/update_theme_dry_run.sh
THEME_UPDATE_LIVE_SCRIPT=/var/www/www-root/data/www/passport-stage.narniapanel.top/scripts/update_theme_live.sh
THEME_UPDATE_SERVER_HOST=127.0.0.1
WP_ASSETS_PATH=.../wp-assets
WP_SITES_ROOT=/var/www/www-root/data/www
WP_DB_HOST=127.0.0.1:3306
WP_DB_ROOT_USER=root
WP_DB_ROOT_PASSWORD=1tkI5YbOpK
WP_SITE_USER=www-root
WP_DEFAULT_PLUGINS=contact-form-7,wordpress-seo
THEME_REPO=git@github.com:sergeys-commits/wp-theme-core.git
```

---

## Инфраструктура — важные детали

### SSH для www-root (GitHub доступ)
- Ключ: `/var/www/www-root/data/.ssh/id_ed25519_github_account` (полный доступ к аккаунту)
- Config: `/var/www/www-root/data/.ssh/config` → `Host github.com` → этот ключ
- HOME у www-root: `/var/www/www-root/data/` (не `/var/www/`)
- Старый ключ `/var/www/.ssh/deploy_theme` — занят другим репо, для theme update не используется

---

## Известные проблемы
- Деплой синхронный (блокирует HTTP) — Queue не реализован
- Polling UI отсутствует
- `env()` вместо `config()` в PromoteToProductionService — сломается при config:cache

---

## Контрольные точки (чекпоинты)

### Promote to prod (DONE ✅)
- [x] `promote_to_prod.sh` написан и протестирован
- [x] `PromoteToProductionService` реализован
- [x] Migration применена (domain nullable)
- [x] UI форма работает (dry_run)
- [x] UI форма работает (live)
- [x] SiteEvent пишется после success

### Theme update (DONE ✅)
- [x] `update_theme_dry_run.sh` написан и протестирован
- [x] `update_theme_live.sh` написан и протестирован
- [x] `theme_version` поле добавлено в sites
- [x] `theme_name` поле добавлено в site_groups
- [x] `ThemeUpdateService` реализован
- [x] UI форма работает (dry_run)
- [x] UI форма работает (live)
- [x] `sites.theme_version` обновляется после live success
- [x] SiteEvent пишется после success

### Паспорт
- [ ] Форма ручного создания с новыми полями

### GitHub API (следующая итерация theme update)
- [ ] GITHUB_TOKEN добавлен в .env
- [ ] GitHubService возвращает список тегов
- [ ] UI показывает дропдаун тегов вместо текстового поля