# Site Passports — Current State
_Обновляется после каждой завершённой задачи_

---

## Последнее обновление
Date: 2026-03-23
Task: Promote Stage → Production

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

---

## В процессе

_ничего_

---

## Не начато

- Theme update (массовое обновление по тегу)
- Паспорт — ручное создание сайта
- Queue / async деплой
- Реестр серверов
- Deployment profiles
- SEO интеграция
- Admin gateway

---

## Схема БД — текущее состояние

### Таблицы
- `sites` — паспорта (domain nullable, admin_url, stage_admin_url, wp_admin_password)
- `site_events` — история событий
- `deployment_runs` — аудит запусков (type: stage_provision | promote_to_prod)
- `deployment_logs` — построчные логи
- `deployment_run_guards` — локи параллельных деплоев

### Важные детали схемы
- `sites.status` ENUM: `active`, `stage`, `archived` (active = prod)
- `sites.domain` — nullable, заполняется после promote
- `deployment_runs.action_type` (не `type` — уточнить в коде)

---

## .env переменные (актуальные)
```
STAGE_PROVISION_DRY_RUN_SCRIPT=.../scripts/provision_stage_dry_run.sh
STAGE_PROVISION_LIVE_SCRIPT=.../scripts/provision_stage_live.sh
PROMOTE_TO_PROD_DRY_RUN_SCRIPT=.../scripts/promote_to_prod_dry_run.sh
PROMOTE_TO_PROD_LIVE_SCRIPT=.../scripts/promote_to_prod.sh
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

### Theme update
- [ ] `update_theme.sh` написан
- [ ] `theme_version` поле добавлено в sites
- [ ] UI массового обновления работает

### Паспорт
- [ ] Форма ручного создания с новыми полями