# Site Passports — Project Summary

## Что это

Внутренняя платформа управления сайтами и миграциями для SEO-команды.
Laravel + MySQL + Redis (планируется). Стейдж: `passport-stage.narniapanel.top`

## Репозиторий

`git@github-site-passports:sergeys-commits/site-passports.git`
Текущая ветка: `task-001-v33-live-impl`

## Инфраструктура

- Сервер: VPS + ISPManager
- PHP 8.3, пользователь сайтов: `www-root`
- MySQL: root без пароля через socket, пароль `1tkI5YbOpK` через TCP
- WP-CLI: установлен, кэш в `/var/www/www-root/data/.wp-cli/cache/`
- Сайты живут в: `/var/www/www-root/data/www/`
- Проект: `/var/www/www-root/data/www/passport-stage.narniapanel.top/`

## SSH ключи на сервере

```
/root/.ssh/config:
  Host github.com          → /root/.ssh/deploy_theme        (deploy key для wp-theme-core)
  Host github-site-passports → /root/.ssh/id_ed25519_github_account (полный доступ к аккаунту)

/var/www/.ssh/deploy_theme  → deploy key для wp-theme-core (читается от www-root)
```

## Что сделано (Phase 1)

### Модель данных
- `sites` — паспорта сайтов (добавлены поля `admin_url`, `stage_admin_url`, `wp_admin_password`)
- `site_events` — история событий
- `deployment_runs` — запуски деплоя (audit)
- `deployment_logs` — построчные логи bash-скриптов

### Сервисный слой
- `StageProvisionService` — оркестрирует деплой, соблюдает dry_run инвариант
- `DeploymentRunGuardService` — локи для предотвращения параллельных деплоев
- После `live success` автоматически создаёт/обновляет Site + пишет SiteEvent

### Bash-скрипты
```
scripts/
  provision_stage_dry_run.sh  — проверяет окружение, ничего не меняет
  provision_stage_live.sh     — полный деплой WordPress
```

### Что делает provision_stage_live.sh
1. Проверяет что папка домена существует (домен создаётся вручную в ISPManager)
2. Создаёт MySQL БД + пользователя (для localhost и 127.0.0.1)
3. Скачивает WordPress через WP-CLI (кэшируется)
4. Создаёт wp-config
5. Устанавливает WordPress
6. Устанавливает плагины из `/wp-assets/plugins/*.zip` (ACF Pro и др.)
7. Устанавливает плагины с wordpress.org (список в `WP_DEFAULT_PLUGINS`)
8. Клонирует тему из git (`THEME_REPO`) в `wp-content/themes/wp-theme-core`
9. Активирует тему
10. Выставляет права (`www-root:www-root`)
11. Возвращает финальный JSON: `{"status":"success","stage_domain":"...","admin_password":"..."}`

### .env ключевые переменные
```
STAGE_PROVISION_DRY_RUN_SCRIPT=.../scripts/provision_stage_dry_run.sh
STAGE_PROVISION_LIVE_SCRIPT=.../scripts/provision_stage_live.sh
WP_ASSETS_PATH=.../wp-assets
WP_SITES_ROOT=/var/www/www-root/data/www
WP_DB_HOST=127.0.0.1:3306
WP_DB_ROOT_USER=root
WP_DB_ROOT_PASSWORD=1tkI5YbOpK
WP_SITE_USER=www-root
WP_DEFAULT_PLUGINS=contact-form-7,wordpress-seo
THEME_REPO=git@github.com:sergeys-commits/wp-theme-core.git
```

### wp-assets структура
```
wp-assets/
  plugins/
    advanced-custom-fields-pro.zip   ← premium плагины (local)
  themes/                            ← пока пусто (тема из git)
```

### UI
- `/deployments/stage-provision/new` — форма создания stage сайта
- `/deployments/runs/{id}` — логи деплоя
- `/sites/{id}` — паспорт сайта (domain, stage, status, wp-admin link, password, events)

### Время деплоя
~35 секунд (WordPress из кэша + 3 плагина + тема из git)

---

## Что НЕ сделано (следующие фазы)

### Критично (сделать до реального использования)
- **Queue** — сейчас деплой синхронный (блокирует HTTP). Нужен Laravel Queue + Redis
- **Polling UI** — показывать прогресс деплоя в реальном времени

### Phase 2 — Infrastructure
- Реестр серверов (`servers` таблица)
- Реестр шаблонов (`templates` таблица)
- Deployment profiles (`deployment_profiles` таблица)
- Выбор темы в форме деплоя

### Phase 3 — Promotion Engine
- stage → prod flow
- Preflight checks
- Confirm phrase
- Rollback

### Phase 4 — SEO
- GSC API интеграция
- Keyword tracking
- Migration impact

### Phase 5 — Admin Gateway
- Быстрый доступ к wp-admin, серверу, stage

### Будущее
- Уникализация CSS (цвета, классы) — отдельный скрипт
- MODX поддержка (сейчас только WordPress)
- Multi-server (SSH executor)
- RBAC по group (сейчас заглушка в Policy)

---

## Рабочий процесс с Claude

- Новый чат → прикрепить этот файл + `SITE_PASSPORTS_PLATFORM.md`
- Я (Claude) — архитектура и код
- Ты — руки на сервере (копируешь команды, скидываешь вывод)
- Для правок файлов — Python/PHP скрипты через терминал (не sed)
- Для сложных правок — nano напрямую
