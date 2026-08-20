# SPEC: Автоматизация деплоя и обновления темы wp-theme-core (Laravel panel)

Документ для реализации/доработки Laravel-панели.
Тема: `wp-theme-core` (WordPress + Vite + site profiles).
Цель: одна кодовая база темы, уникальный build-артефакт на каждый сайт, управляемые обновления и два сценария жизненного цикла.

---

## 1. Контекст и проблема

### Как было

- На домене: `git pull` / checkout последнего или указанного тега.
- В репозитории уже лежал «готовый» `dist`.
- Один и тот же frontend-артефакт раскатывался на много доменов → общие футпринты (байты бандлов, хеши файлов).

### Как должно быть

- Git хранит **source** темы.
- Laravel хранит **pins сайта** (salt, profile, token, slug).
- Обновление = `git_ref + pins сайта → npm build → upload готовой папки темы`.
- `wp-config` pins при обычном update **не меняются**.

### Инвариант

```text
site_id (бренд/сайт)
  ├── pins (стабильные)
  └── targets[] (staging / production / prod+basic_auth)
```

Домен ≠ отдельная тема. Домен = target у `site_id`.

---

## 2. Модель данных (Laravel)

### 2.1. Entity: Site

Минимальные поля:

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | uuid/int | PK |
| `brand_key` | string | человекочитаемый ключ бренда |
| `site_salt` | string | генерируется **один раз** при создании, не менять при update |
| `profile_id` | string | `p01`…`p08`, sticky |
| `profile_revision` | int | сейчас обычно `1` |
| `public_token` | string | короткий публичный токен для build/JS |
| `theme_slug` | string | имя папки темы на сервере, напр. `factory-7k2f9d` |
| `theme_git_ref` | string\|null | последний успешно задеплоенный tag/SHA |
| `last_build_meta` | json\|null | снимок `dist/build-meta.json` |
| `lifecycle` | enum | `staging` \| `production` \| `archived` |
| `scenario` | enum | `stage_then_prod` \| `prod_basic_auth` |
| `created_at` / `updated_at` | timestamps | |

Правила:

- `site_salt` / `profile_id` / `profile_revision` / `public_token` **immutable** при theme update.
- Смена salt/profile — отдельная операция миграции (редко).

### 2.2. Entity: SiteTarget

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | | PK |
| `site_id` | FK | |
| `kind` | enum | `staging` \| `production` |
| `domain` | string | FQDN |
| `server_id` | FK | куда деплоить |
| `docroot` / `wp_path` | string | путь к WP |
| `basic_auth` | bool | закрыт ли basic auth |
| `is_active` | bool | |
| `wp_config_pins_written` | bool | уже прописаны `FACTORY_SITE_*` |

### 2.3. Entity: ThemeArtifact (кэш, рекомендуется)

| Поле | Тип | Описание |
|------|-----|----------|
| `cache_key` | string unique | см. ниже |
| `git_ref` | string | |
| `git_sha` | string | resolved SHA |
| `profile_id` | string | |
| `profile_revision` | int | |
| `salt_fingerprint` | string | sha256(salt)[:16] как в теме |
| `storage_path` | string | zip/s3 path |
| `build_meta` | json | |
| `built_at` | datetime | |

`cache_key` =

```text
{git_sha}__{profile_id}@{profile_revision}__{salt_fingerprint}
```

---

## 3. Два сценария жизненного цикла сайта

### 3.1. Сценарий A: `stage_then_prod` (~200 сайтов)

1. Создать `Site` + pins.
2. Создать target `staging` (домен стейджа).
3. Provision WP + plugins + DeployTheme на staging.
4. Наполнение контентом на стейдже.
5. Создать target `production` (финальный домен).
6. Promote: **тот же** `theme_git_ref` / artifact + **те же pins** на production.
7. Миграция БД/медиа/URL — существующим механизмом панели (вне темы).
8. Staging target деактивировать по желанию.

**Запрещено:** генерировать новый salt при переезде на прод.

### 3.2. Сценарий B: `prod_basic_auth` (массовый)

1. Создать `Site` + pins.
2. Создать target `production` с `basic_auth=true`.
3. Provision + DeployTheme на production.
4. Наполнение за basic auth.
5. Снять basic auth отдельной server-операцией (не theme update).

Для темы сценарий B = один active target.

---

## 4. Jobs / операции панели

### 4.1. `CreateSite`

Вход: scenario, domains/targets, server(s), optional `profile_id` (default assign sticky).

Действия:

1. Сгенерировать `site_salt` (криптостойкая случайная строка).
2. Назначить `profile_id` (из пула p01–p08; зафиксировать; не `hash%N` без sticky-записи).
3. Задать `public_token` (явный или derived; хранить явно).
4. Задать `theme_slug` (уникальный, не шаблон `wp-theme-*`).
5. Создать Site + SiteTarget(s).

### 4.2. `ProvisionWordPress` (как сейчас + pins)

После установки WP:

- записать в `wp-config.php` (или эквивалент) **один раз**:

```php
define('FACTORY_SITE_SALT', '...');
define('FACTORY_SITE_PROFILE_ID', 'p01');
define('FACTORY_SITE_PROFILE_REVISION', 1);
define('FACTORY_SITE_PUBLIC_TOKEN', '...');
define('FACTORY_SITE_THEME_SLUG', 'factory-xxxx');
```

- отметить `wp_config_pins_written=true`.

### 4.3. `DeployTheme` (главный job обновления)

Вход:

```text
site_id
git_ref            # tag | branch | sha | "latest_tag"
targets            # staging | production | all_active (default)
force_rebuild      # bool, default false
```

Алгоритм:

1. Resolve `git_ref` → `git_sha` (если `latest_tag` — последний semver-тег theme repo).
2. Load pins сайта из БД.
3. Compute `cache_key`.
4. Если artifact есть и `!force_rebuild` → взять zip.
5. Иначе на build-worker:
   - `git fetch && git checkout <git_sha>`
   - env:
     - `SITE_SALT`
     - `SITE_PROFILE_ID`
     - `SITE_PROFILE_REVISION`
     - `SITE_PUBLIC_TOKEN`
   - `npm ci`
   - `npm run build`
   - упаковать тему (PHP + `config/profiles` + `dist` + `style.css` …; **без** `node_modules`)
   - сохранить artifact + `build-meta`
6. Validate build-meta vs pins:
   - `profile_id`, `profile_revision`, `salt_fingerprint`, `public_token`
   - mismatch → fail job, **не деплоить**
7. Upload на выбранные targets в:
   `wp-content/themes/{theme_slug}/`
8. Убедиться, что тема активирована (или активировать).
9. Smoke checks (см. §6).
10. Обновить `site.theme_git_ref`, `site.last_build_meta`.

**Не делать на WP-сервере как основной путь:** `git pull` темы без rebuild под pins.

### 4.4. `PromoteSite` (только scenario A)

Вход: `site_id`, optional `git_ref` (default = текущий `theme_git_ref` стейджа).

Действия:

1. Убедиться, что staging target успешен на этом ref.
2. Deploy **тот же artifact** на production target (без смены pins).
3. Отдельно: content/DB/URL migration (существующий пайплайн).

### 4.5. `BulkDeployTheme`

Вход: filter sites + `git_ref` + target policy.

Очередь `DeployTheme` с concurrency (рекомендуется 5–10).
Кэш артефактов обязателен для скорости.

### 4.6. `RollbackTheme`

Вход: `site_id`, `git_ref` (предыдущий) или `artifact_id`.

Залить предыдущий artifact с **теми же pins**. Не менять salt.

---

## 5. Build environment

- Node 20+ (лучше 22), npm.
- Theme repo доступен build-worker’у.
- Build выполняется **централизованно** (Laravel queue worker / CI), не на каждом WP-хосте (предпочтительно).
- Альтернатива (хуже): Node на каждом сервере + post-pull build с pins из панели — допустимо, но усложняет ops.

Переменные окружения build (обязательные вместе):

```text
SITE_SALT
SITE_PROFILE_ID
SITE_PROFILE_REVISION
SITE_PUBLIC_TOKEN
```

Тема fail-closed: без pins `npm run build` падает.
Runtime fail-closed: pins ≠ `dist/build-meta.json` → фронт 500.

---

## 6. Smoke checks после деплоя

Минимум:

1. HTTP статус фронта (с basic auth credentials, если `basic_auth=true`).
2. Нет fatal/profile mismatch (страница не отдаёт «Site configuration error»).
3. В HTML/ассетах путь содержит `/themes/{theme_slug}/dist/assets/{file_prefix}-`.
4. Опционально: скачать `dist/build-meta.json` с сервера и сверить с БД.

При ошибке — job failed, rollback optional.

---

## 7. UI панели (минимальный)

### Сайт

- Создать сайт: выбор scenario A/B, домен(ы), сервер, profile (или auto).
- Карточка сайта: pins (salt скрыт/masked), profile, theme_slug, current git_ref, targets.

### Тема

- Кнопка «Обновить тему»:
  - ref: latest tag / выбрать tag / SHA
  - targets: staging only / production only / all active
- Кнопка «Promote to production» (scenario A).
- История деплоев: site, ref, sha, status, artifact key, timestamp.
- Bulk update: multi-select / filter by lifecycle.

### Ограничения UX

- Не давать «перегенерировать salt» в обычном update.
- Warn при deploy на production без успешного staging deploy того же ref (scenario A) — можно soft-block.

---

## 8. Соответствие текущей теме (wp-theme-core)

Уже реализовано в теме:

- `config/profiles/p01`…`p08`
- `config/sites.registry.json` (временный файл; **SoT должен быть Laravel**)
- Vite build с pins → prefix файлов, define token, minify/cssCodeSplit
- `dist/build-meta.json`
- PHP `inc/profile.php` fail-closed
- README описывает pins / build / wp-config

Не реализовывать в Laravel:

- общий один `dist` на все сайты;
- git pull на домене как единственный update;
- новый salt на каждый update;
- разный profile у staging и production одного `site_id`.

---

## 9. Миграция существующих сайтов

Для каждого живого домена:

1. Создать `Site` в панели.
2. Сгенерировать pins один раз.
3. `DeployTheme` текущим нужным ref.
4. Прописать `FACTORY_SITE_*` в wp-config.
5. Проверить фронт.
6. Дальше только обычные `DeployTheme`.

Флаг `profile_pipeline_enabled` — катить постепенно.

---

## 10. Acceptance criteria

- [ ] Создание сайта scenario A создаёт staging target + pins + успешный DeployTheme.
- [ ] Создание сайта scenario B создаёт production+auth target + DeployTheme.
- [ ] Update темы на сайт меняет файлы/`dist`, но не pins в БД и wp-config.
- [ ] Один и тот же tag на двух сайтах с разными salt даёт разные artifact `cache_key` и разные file prefix в dist.
- [ ] Promote staging→prod использует те же pins и предпочтительно тот же artifact.
- [ ] Bulk update N сайтов работает через очередь без общего dist.
- [ ] Mismatch build-meta/pins блокирует деплой или ловится smoke-check’ом.
- [ ] Rollback возвращает предыдущий artifact без смены salt.

---

## 11. Псевдокод DeployTheme

```text
function DeployTheme(site_id, git_ref, targets = all_active, force_rebuild = false):
  site = Site.lockForUpdate(site_id)
  sha = resolveGitSha(THEME_REPO, git_ref)
  pins = site.pins
  key = cacheKey(sha, pins)

  artifact = Artifact.find(key)
  if artifact is null or force_rebuild:
    workdir = checkout(THEME_REPO, sha)
    run(workdir, env=pinsToEnv(pins), "npm ci && npm run build")
    assertBuildMetaMatches(workdir/dist/build-meta.json, pins)
    artifact = storeZip(workdir, key, build-meta)

  for target in site.targets.where(targets):
    assert target.wp_config_pins_written
    upload(artifact, target, remote_path = themes/{site.theme_slug})
    smokeCheck(target)

  site.theme_git_ref = git_ref_or_sha
  site.last_build_meta = artifact.build_meta
  site.save()
```

---

## 12. Вне скоупа этого документа

- Контент-миграция БД/медиа/rewrites (использовать текущий механизм панели).
- DNS/TLS/basic auth implementation details (только флаги/хуки).
- CSS class HMAC / SVGO / HTML profiles (следующие волны темы; Laravel API pins не менять).

---

## 13. Краткая формула для промпта

Реализуй в Laravel-панели деплой темы wp-theme-core по модели:
`site_id` + immutable pins (`salt`, `profile_id`, `revision`, `public_token`, `theme_slug`);
targets `staging` | `production` (+ `basic_auth`);
`DeployTheme(git_ref)` = checkout on worker → npm build with pins → cache artifact by `sha+profile+salt_fp` → upload theme dir → smoke;
не использовать git pull на WP-сервере как основной update;
stage→prod promote без смены pins;
bulk update через очередь.
