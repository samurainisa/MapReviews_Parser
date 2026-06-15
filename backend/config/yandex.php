<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Yandex Maps hosts
    |--------------------------------------------------------------------------
    |
    | Хосты, которые считаются валидными источниками карточек организаций.
    | Проверка выполняется через разбор URL (host точно совпадает или является
    | поддоменом), а не через str_contains, чтобы исключить подделки вида
    | "yandex.ru.fake-domain.com".
    |
    */
    'maps_hosts' => [
        'yandex.ru',
        'yandex.com',
        'yandex.kz',
        'yandex.by',
        'yandex.uz',
        'yandex.com.tr',
    ],

    /*
    |--------------------------------------------------------------------------
    | Parser settings
    |--------------------------------------------------------------------------
    */
    'parser' => [
        // Максимальное время на сетевой запрос, сек.
        'timeout' => (int) env('YANDEX_PARSER_TIMEOUT', 60),

        // Верхняя граница количества отзывов, которые мы пытаемся загрузить.
        'max_reviews' => (int) env('YANDEX_PARSER_MAX_REVIEWS', 700),

        // Пауза между запросами к внешнему источнику, мс.
        'request_delay_ms' => (int) env('YANDEX_PARSER_REQUEST_DELAY_MS', 500),

        // Защита job от бесконечного цикла при изменении структуры источника.
        'max_iterations' => (int) env('YANDEX_PARSER_MAX_ITERATIONS', 60),

        // Сколько отзывов запрашивается за одну итерацию (страница API).
        'page_size' => (int) env('YANDEX_PARSER_PAGE_SIZE', 50),

        // User-Agent для HTTP-запросов.
        'user_agent' => env(
            'YANDEX_PARSER_USER_AGENT',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
            .'(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
        ),

        // Включён ли headless-browser fallback.
        'browser_fallback_enabled' => filter_var(
            env('YANDEX_PARSER_BROWSER_FALLBACK', true),
            FILTER_VALIDATE_BOOL
        ),

        // Путь до node-скрипта Playwright (browser fallback).
        'browser_script' => env(
            'YANDEX_PARSER_BROWSER_SCRIPT',
            base_path('parser/yandex-reviews.cjs')
        ),

        // Бинарь node для запуска browser-скрипта.
        'node_binary' => env('YANDEX_PARSER_NODE_BINARY', 'node'),

        // Каталог с fixtures для локальной отладки/тестов парсера.
        'fixtures_path' => base_path('parser/fixtures'),

        // Если true — парсер читает данные из fixtures вместо сети.
        // Удобно для демо без доступа к Яндексу и для тестов.
        'use_fixtures' => filter_var(
            env('YANDEX_PARSER_USE_FIXTURES', false),
            FILTER_VALIDATE_BOOL
        ),
    ],

    // Максимальная допустимая длина ссылки.
    'max_url_length' => 2048,
];
