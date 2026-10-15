# Vacancy Test

Тестовый проект https://vacancy-test.atu.kz отделён от vacancy.atu.kz.
Внутренние имена каталогов, образов и Compose сохранены как hr-test:

- Каталог: /opt/apps/hr-test.
- Compose: docker-compose.hr-test.yml, имя проекта hr-test.
- Образы: hr-test-atu-app и hr-test-atu-nginx, тег из HR_TEST_RELEASE.
- Volumes: hr-test_mysql_data и hr-test_storage_data.
- База: hr_test, пользователь hr_test, отдельные случайные пароли.
- Новый APP_KEY, отдельная cookie vacancy_test_session, отдельные очереди и кэш.
- nginx контейнера доступен только на 127.0.0.1:8142.
- Почта записывается в лог, ключи OpenAI и КМК не передаются.
- Индексация запрещена через X-Robots-Tag.
- У тестовой MySQL отключён native AIO: на общем хосте лимит AIO уже занят другими сервисами. Системные лимиты не менялись.

Первичная установка проверена 15 октября 2026 года: четыре контейнера запущены,
MySQL healthy, HTTPS и вход администратора работают на 206.62.55.202.
Перенесена копия локальной базы: 424 заявки и 464 пользователя, включая их
существующие хэши паролей. Добавлен отдельный тестовый администратор.
Все 1954 связанных документа (1 571 190 640 байт) скопированы из рабочего
хранилища, подключённого только для чтения. Контрольные суммы проверены.
Копии находятся в закрытом тестовом хранилище app/private/documents.
Рабочая база, файлы и контейнеры не изменялись.
Доступ сохранён отдельно от Git, в storage/.tools/vacancy-test-access.txt
на локальном компьютере и /opt/apps/hr-test-artifacts/vacancy-test-access.txt
на сервере. Манифест импорта и отчёт копирования находятся в тестовом
storage/app/private; резервная копия состояния перед импортом — в hr-test-artifacts.

Секреты находятся в /opt/apps/hr-test/hr-test.env, права 600.
Не копировать docker.env рабочего проекта и не использовать production volumes.

## Команды

```bash
cd /opt/apps/hr-test
docker compose --env-file hr-test.env -f docker-compose.hr-test.yml ps
docker compose --env-file hr-test.env -f docker-compose.hr-test.yml logs --tail=100 app queue nginx
docker compose --env-file hr-test.env -f docker-compose.hr-test.yml exec -T app php artisan migrate --force
docker compose --env-file hr-test.env -f docker-compose.hr-test.yml exec -T app php artisan optimize
```

Для следующей версии сначала загрузить актуальный код именно в /opt/apps/hr-test,
изменить HR_TEST_RELEASE в hr-test.env, затем:

```bash
docker compose --env-file hr-test.env -f docker-compose.hr-test.yml build app nginx
docker compose --env-file hr-test.env -f docker-compose.hr-test.yml up -d --no-build
docker compose --env-file hr-test.env -f docker-compose.hr-test.yml exec -T app php artisan migrate --force
docker compose --env-file hr-test.env -f docker-compose.hr-test.yml exec -T app php artisan optimize
```

Не использовать здесь команды из /opt/apps/vacancy. Не выполнять down -v,
если тестовые данные ещё нужны. Копии существующих пользовательских токенов,
очередей, сессий и запросов сброса пароля переносить нельзя.

## DNS

Для vacancy-test.atu.kz настроена A -> 206.62.55.202.
Wildcard TLS-сертификат *.atu.kz на основном сервере покрывает этот домен.
Host-конфигурация: docker/nginx/vacancy-test.host.conf.
Ранее созданный на этом сервере vhost hr-test.atu.kz отключён; DNS и приложение
hr-test.atu.kz на другом сервере не менялись.
