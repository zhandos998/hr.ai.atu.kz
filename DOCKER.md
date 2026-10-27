# Docker

Production-like запуск состоит из 4 сервисов:

- `nginx` - HTTP вход и статические файлы.
- `app` - Laravel PHP-FPM.
- `queue` - Laravel queue worker.
- `db` - MySQL 8.4.

## Первый запуск

1. Создайте env-файл для Docker:

```bash
cp docker.env.example docker.env
```

2. Заполните минимум:

```env
APP_KEY=base64:...
APP_URL=https://vacancy.atu.kz
DOCKER_DB_PASSWORD=...
DOCKER_DB_ROOT_PASSWORD=...
OPENAI_API_KEY=...
TEACHER_AUDIT_API_KEY=...
```

Если ключа нет, сгенерируйте локально:

```bash
php artisan key:generate --show
```

3. Соберите и запустите:

```bash
docker compose --env-file docker.env up -d --build
```

4. Примените миграции:

```bash
docker compose --env-file docker.env exec app php artisan migrate --force
```

5. Если нужны сидеры:

```bash
docker compose --env-file docker.env exec app php artisan db:seed --force
```

Приложение будет доступно на `APP_PORT`, по умолчанию `http://localhost:8000`.

## Команды

```bash
docker compose --env-file docker.env ps
docker compose --env-file docker.env logs -f app
docker compose --env-file docker.env logs -f queue
docker compose --env-file docker.env down
```

## Примечания

- `RUN_MIGRATIONS=false` по умолчанию. Для контролируемого прода лучше запускать миграции отдельной командой.
- `queue` обрабатывает фоновые задания, если приложение помещает их в очередь `database`.
- Загруженные файлы хранятся в volume `storage_data`.
- База хранится в volume `mysql_data`.

## Обновление PHP по рекомендации безопасности

Проверено по рекомендациям PHP от 21 и 23 октября 2026 года:

- Образ приложения закреплён на `php:8.3.36-fpm-bookworm`; обновление действует и для `queue`.
- `session.upload_progress.enabled=0` задан в стартовом `php.ini` и заблокирован через `php_admin_flag` в FPM.
- `memory_limit=512M` сохранён. Временная мера `memory_limit=-1` не используется, поскольку образ содержит исправления PHP.
- Ограничения загрузки сохранены: nginx и `post_max_size` — 60M, один файл — 50M. Валидация приложения может быть строже.
- Ошибки PHP направляются в stderr контейнера, но не выводятся посетителям.
- Сборка и запуск контейнера проверяют версию PHP и базовые настройки. Неподходящий runtime останавливается до запуска приложения.

Источники:
[обновление PHP](https://www.php.net/releases/8_3_36.php),
[дополнительная рекомендация по upload progress](https://www.php.net/archive/2026.php#2026-10-23-1).

### Применение На Сервере

После коммита, отправки кода и резервного копирования базы и файлов:

```bash
cd /opt/apps/vacancy
git pull --ff-only origin master

dc() { docker compose --env-file docker.env "$@"; }

# --pull исключает использование старого базового образа из локального кэша.
dc build --pull app nginx queue

dc exec -T app php artisan down
dc stop queue
dc up -d --no-deps --force-recreate app nginx

dc exec -T app php /usr/local/bin/check-php-security.php
dc exec -T app php-fpm --test
dc exec -T app php artisan optimize:clear
dc exec -T app php artisan optimize

dc up -d --no-deps --force-recreate queue
dc exec -T queue php /usr/local/bin/check-php-security.php
dc exec -T app php artisan up
dc ps
```

При любой ошибке остановитесь: не продолжайте развёртывание и не возвращайте трафик до проверки причины.
Для изменения только PHP/конфигурации миграции и сидеры не требуются. Если одновременно обновляете код приложения
с новыми миграциями, примените их по обычной процедуре до запуска очереди и снятия режима обслуживания.
Обычный `restart` не обновляет образ: нужны пересборка и пересоздание `app` и `queue`.
Конфигурация обоих Compose-файлов использует один Dockerfile; для тестового сайта указывайте
`hr-test.env` и `-f docker-compose.hr-test.yml`, не подменяя production volumes.

### Инвентаризация И Журналы

```bash
docker compose --env-file docker.env exec -T app php -v
docker compose --env-file docker.env exec -T queue php -v
docker compose --env-file docker.env exec -T app php /usr/local/bin/check-php-security.php
docker compose --env-file docker.env exec -T queue php /usr/local/bin/check-php-security.php
docker compose --env-file docker.env logs --since=24h app nginx queue
```

Для проверки заблокированных значений FPM (CLI проверяет только стартовый INI):

```bash
docker compose --env-file docker.env exec -T app php-fpm -tt 2>&1 \
  | grep -E 'php_admin_(flag|value)\[(session.upload_progress.enabled|memory_limit)\]'
```

Проверка выполняется через CLI/FPM без публичной страницы `phpinfo()`.
Нужно отдельно проверить все остальные PHP-приложения и phpMyAdmin на сервере:
обновление нашего Dockerfile не обновляет их интерпретаторы и не доказывает отсутствие компрометации.
При признаках эксплуатации необходимы отдельное расследование и смена затронутых секретов.
