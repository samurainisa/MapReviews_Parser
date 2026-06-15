# Yandex Maps Reviews Connector (MapReviews Parser)

SPA на **Vue 3** + API на **Laravel 13**, которое позволяет авторизованному
пользователю сохранить ссылку на карточку организации в Яндекс.Картах, получить
её данные (рейтинг, количество оценок, количество отзывов и сами отзывы) через
парсинг и просмотреть отзывы с пагинацией по 50 штук.

---

## 1. Описание проекта

После входа пользователь вставляет ссылку на карточку организации в
Яндекс.Картах. Backend валидирует ссылку, нормализует её, сохраняет в БД и
ставит в очередь фоновую задачу парсинга. Frontend опрашивает статус (polling) и,
как только данные готовы, показывает карточку организации и список отзывов.
Отзывы хранятся в собственной БД, поэтому пагинация работает быстро и не
обращается к Яндексу при каждом переключении страницы.

## 2. Стек

| Слой        | Технологии                                                        |
| ----------- | ----------------------------------------------------------------- |
| Backend     | PHP 8.3, Laravel 13, Laravel Sanctum (cookie-based SPA auth)      |
| Очередь     | Redis + `php artisan queue:work`                                  |
| БД          | MySQL 8                                                           |
| Frontend    | Vue 3 (Composition API), TypeScript, Vite, Pinia, Vue Router, Axios |
| Парсер      | HTTP-клиент Laravel (1-й уровень) + Playwright/Chromium (fallback) |
| Инфра       | Docker Compose (app, queue, nginx, mysql, redis, frontend)        |

## 3. Что реализовано

- Авторизация login/password через Sanctum (cookie/сессия), seed-пользователь.
- Защищённая SPA-зона, guard на роутах, редирект неавторизованных на `/login`.
- Страница настроек: форма ссылки, статус парсинга, карточка организации, список отзывов.
- Backend-валидация ссылки (домен Яндекс.Карт через разбор URL, карточка организации, длина).
- Нормализация ссылки и извлечение внешнего id организации.
- Парсинг через изолированный сервисный слой (стратегия HTTP-first + browser fallback).
- Очередь: `ParseOrganizationReviewsJob` с защитой от параллельного запуска одной организации.
- Логирование попыток парсинга в таблицу `parse_runs`.
- Сохранение отзывов через `upsert` (без дублей при повторном парсинге).
- API организации и отзывов с пагинацией по 50.
- Состояния загрузки / ошибки / пустого результата и частичного результата на фронтенде.
- Обработка доменных ошибок парсера с понятными пользователю сообщениями.
- Docker Compose для локального запуска.
- Fixtures-режим для гарантированного демо без доступа к Яндексу.
- Тесты на валидацию ссылки, дедупликацию отзывов и обработку ошибок (Pest/PHPUnit).

## 4. Что не реализовано (вне MVP)

- Регистрация, восстановление пароля, роли.
- Подключение нескольких организаций одним пользователем (модель к этому готова — у `organizations` есть `user_id`).
- Экспорт отзывов, аналитика динамики рейтинга, многоязычность, виджет.
- Pixel-perfect вёрстка.

## 5. Требования к окружению

- Docker + Docker Compose (рекомендуемый путь).
- Либо локально: PHP 8.3+, Composer, Node 20+, MySQL 8, Redis.

## 6. Запуск через docker-compose

```bash
# 1. Подготовить .env бэкенда
cp backend/.env.example backend/.env

# 2. Поднять контейнеры (app, queue, nginx, mysql, redis, frontend)
docker compose up -d --build

# 3. Установить зависимости backend
docker compose run --rm app composer install

# 4. Сгенерировать ключ приложения
docker compose run --rm app php artisan key:generate

# 5. Миграции + seed (тестовый пользователь)
docker compose run --rm app php artisan migrate --seed
```

После запуска:

- Frontend (SPA): <http://localhost:5173>
- Backend API: <http://localhost:8080>

Очередь обрабатывается отдельным контейнером `queue` автоматически.

> Если порт 3307/6379/8080/5173 занят — поменяйте маппинг в `docker-compose.yml`.

## 7. Запуск миграций и seed

```bash
docker compose run --rm app php artisan migrate:fresh --seed
```

## 8. Данные тестового пользователя

```
email:    test@example.com
password: password
```

Пароль хранится только в виде hash. Форма входа предзаполнена этими данными.

## 9. Переменные окружения

Backend (`backend/.env`) — ключевые:

| Переменная                      | Назначение                                          |
| ------------------------------- | --------------------------------------------------- |
| `APP_URL`                       | URL API (`http://localhost:8080`)                   |
| `FRONTEND_URL`                  | Origin SPA для CORS (`http://localhost:5173`)       |
| `SANCTUM_STATEFUL_DOMAINS`      | Домены SPA для cookie-based Sanctum                 |
| `SESSION_DOMAIN`                | Домен сессионной cookie (`localhost`)               |
| `DB_*`                          | Подключение к MySQL                                 |
| `QUEUE_CONNECTION=redis`        | Драйвер очереди                                     |
| `REDIS_*`                       | Подключение к Redis                                 |
| `YANDEX_PARSER_TIMEOUT`         | Таймаут сетевого запроса, сек                       |
| `YANDEX_PARSER_MAX_REVIEWS`     | Верхняя граница количества отзывов (≈ до 600–700)   |
| `YANDEX_PARSER_REQUEST_DELAY_MS`| Пауза между запросами к источнику, мс               |
| `YANDEX_PARSER_MAX_ITERATIONS`  | Защита от бесконечного цикла                        |
| `YANDEX_PARSER_BROWSER_FALLBACK`| Включить headless-browser fallback                  |
| `YANDEX_PARSER_USE_FIXTURES`    | Брать данные из локальных fixtures (демо без Яндекса)|

Frontend (`frontend/.env`):

```env
VITE_API_BASE_URL=http://localhost:8080
```

## 10. Описание API

Все защищённые методы требуют авторизации (`auth:sanctum`).

| Метод | Маршрут                          | Назначение                                   |
| ----- | -------------------------------- | -------------------------------------------- |
| GET   | `/sanctum/csrf-cookie`           | CSRF-cookie перед изменяющими запросами      |
| POST  | `/api/login`                     | Вход (email/password)                        |
| GET   | `/api/me`                        | Текущий пользователь                         |
| POST  | `/api/logout`                    | Выход                                        |
| GET   | `/api/organization`              | Данные организации пользователя (или `null`) |
| POST  | `/api/organization/settings`     | Сохранить ссылку и запустить парсинг         |
| POST  | `/api/organization/refresh`      | Повторный запуск парсинга                    |
| GET   | `/api/organization/reviews?page=`| Отзывы из БД, по 50 на страницу              |

Пример ответа `GET /api/organization/reviews`:

```json
{
  "data": [
    { "id": 1, "author_name": "Иван Иванов", "review_date": "2026-05-10", "text": "…", "rating": 5 }
  ],
  "meta": { "current_page": 1, "per_page": 50, "total": 600, "last_page": 12 }
}
```

## 11. Описание подхода к парсингу

Парсер вынесен из контроллеров в отдельный слой `app/Services/Parser`, скрыт за
интерфейсом `YandexMapsParserInterface` и вызывается только из фоновой задачи
`ParseOrganizationReviewsJob`. Контроллер лишь валидирует запрос, дёргает
action-класс и возвращает JSON.

Выбрана **стратегия HTTP-first + browser fallback** (класс `YandexMapsParser`):

1. **HTTP / internal-data (`HttpYandexMapsParser`)** — первый уровень.
   Загружает страницу отзывов карточки, проверяет признаки капчи/блокировки,
   извлекает рейтинг и счётчики из серверной разметки (schema.org / встроенный
   JSON), а отзывы пытается получить постранично через внутренний JSON-эндпоинт
   Яндекса (`fetchReviews`) с обходом до `YANDEX_PARSER_MAX_REVIEWS`.
   Способ быстрый, дешёвый и легко тестируется на fixtures.

2. **Headless browser (`BrowserYandexMapsParser`)** — fallback.
   Если HTTP-уровень бросил доменную ошибку или не смог получить отзывы при
   ненулевом счётчике, запускается Playwright-скрипт
   (`backend/parser/yandex-reviews.cjs`): он открывает страницу, прокручивает
   ленту отзывов и собирает данные после выполнения JavaScript. Тяжёлый
   browser-процесс запускается только внутри job/parser service, никогда в контроллере.

3. **Fixtures (`FixtureYandexMapsParser`)** — для демо/тестов.
   При `YANDEX_PARSER_USE_FIXTURES=true` данные берутся из
   `backend/parser/fixtures/<externalId>.json` (или `default.json`). Это даёт
   гарантированно работающий сквозной сценарий без доступа к Яндексу.

**Почему HTTP-first:** он быстрее браузера, меньше нагружает сервер, проще
тестируется и отлаживается на фикстурах. Браузер подключается только когда без
выполнения JS данные получить нельзя.

**Кэширование результата:** отзывы один раз сохраняются в БД, а SPA получает их
из собственного API. Пагинация работает по данным из БД — Яндекс при
переключении страниц не дёргается.

**Повторный парсинг без дублей:** для каждого отзыва считается `hash`
(`organization_id + author + date + rating + text`), запись идёт через `upsert`
по уникальному индексу `(organization_id, hash)` и/или внешнему `source_review_id`.

### Где лежит код парсера

```
backend/app/Services/Parser/
  YandexMapsParserInterface.php   # контракт
  YandexMapsParser.php            # стратегия (HTTP-first + fallback) — биндится в контейнере
  HttpYandexMapsParser.php        # 1-й уровень
  BrowserYandexMapsParser.php     # 2-й уровень (Playwright)
  FixtureYandexMapsParser.php     # fixtures
  YandexMapsUrl.php               # разбор/нормализация/валидация ссылки
backend/parser/yandex-reviews.cjs # Playwright-скрипт (селекторы Яндекса собраны здесь)
backend/parser/fixtures/          # demo-fixtures
```

### Как локально проверить парсинг

Гарантированный путь без Яндекса (fixtures):

```bash
docker compose run --rm -e YANDEX_PARSER_USE_FIXTURES=true app \
  php artisan tinker --execute='
    $u = App\Models\User::first();
    $org = app(App\Actions\Organization\SaveOrganizationSourceAction::class)
        ->execute($u, "https://yandex.ru/maps/org/demo/1234567890/");
    dump($org->fresh()->only(["parse_status","rating","reviews_count","loaded_reviews_count"]));
  '
```

Включить browser fallback (реальная карточка):

```bash
docker compose exec app sh -c "cd parser && npm install"   # ставит Playwright + Chromium
# затем сохраните реальную ссылку через UI или /api/organization/settings
```

Браузеры Playwright ставятся в общий volume `playwright-cache`
(`PLAYWRIGHT_BROWSERS_PATH=/ms-playwright`), поэтому одной установки достаточно
и для контейнера `app`, и для воркера `queue`, и она переживает пересборку.

> Отзывы в Яндекс.Картах подгружаются через JavaScript, поэтому HTTP-уровень
> отдаёт рейтинг и счётчики (они в серверной разметке), а сами отзывы получает
> browser fallback. Длинные тексты Яндекс обрезает кнопкой «Ещё» — сохраняется
> видимый фрагмент. При большом числе отзывов часть может не подгрузиться за
> отведённое число прокруток — тогда результат помечается частичным
> («Загружено N из M»), что является штатным поведением.

## 12. Ограничения парсинга

- Яндекс.Карты не имеют официального API; внутренние форматы/селекторы могут
  меняться — поэтому селекторы вынесены в один скрипт, есть fixtures и логирование типа ошибки.
- Внешний источник может ограничивать автоматические запросы (капча, 429/403).
  Парсер это распознаёт и сообщает понятным текстом, не делая агрессивных запросов
  (таймауты, паузы, лимит итераций, запрет параллельного парсинга одной организации).
- При частичном результате сохраняются доступные данные и показывается
  предупреждение «Загружено N отзывов из M», вместо полного отказа.
- Серверный IP хостинга может быть заблокирован Яндексом — для надёжного демо
  предусмотрен fixtures-режим.

## 13. Обработка ошибок

Доменные ошибки парсера типизированы (`app/Exceptions/Parser`) и несут отдельно
технический и пользовательский текст:

| Тип                               | Сообщение пользователю                                                       |
| --------------------------------- | ---------------------------------------------------------------------------- |
| `InvalidOrganizationUrlException` | Не удалось определить карточку организации по этой ссылке.                    |
| `SourceUnavailableException`      | Карточка организации временно недоступна. Попробуйте позже.                   |
| `EmptySourceResponseException`    | Карточка открылась, но отзывы или рейтинг не найдены.                         |
| `ParserMarkupChangedException`    | Не удалось разобрать данные карточки. Возможно, изменилась структура страницы.|
| `ParserBlockedException`          | Внешний источник временно ограничил получение данных. Попробуйте позже.      |

Технические детали уходят в лог и `parse_runs.error_message`, но не показываются
пользователю. Организация никогда не «зависает» в статусе `processing`: при
таймауте job метод `failed()` переводит её в `failed`.

## 14. Что бы я доделал при наличии времени

- Поддержку нескольких подключённых организаций на пользователя.
- Расписание автоматического обновления отзывов.
- Отдельный parser-worker контейнер и мониторинг job.
- Более устойчивые селекторы и снимки структуры страницы для алертов при их изменении.
- e2e-тесты на Playwright и расширенный набор fixtures для разных форматов карточек.
- Экспорт отзывов (CSV/XLSX) и аналитику динамики рейтинга.

---

## Тесты

```bash
docker compose run --rm app php artisan test
```

## Структура репозитория

```
backend/    # Laravel API + parser service + очередь
frontend/   # Vue 3 SPA
docker/     # конфиг nginx
docker-compose.yml
```
