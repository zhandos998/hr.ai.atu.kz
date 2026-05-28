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
- `queue` обязателен, потому что проект использует `QUEUE_CONNECTION=database`.
- Загруженные файлы хранятся в volume `storage_data`.
- База хранится в volume `mysql_data`.
