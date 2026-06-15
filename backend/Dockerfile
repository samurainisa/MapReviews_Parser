FROM php:8.4-fpm-bookworm

# Системные зависимости + PHP-расширения + Node.js (для browser fallback парсера).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip ca-certificates curl \
        libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath exif pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    # Runtime-библиотеки Chromium для Playwright (browser fallback).
    && apt-get install -y --no-install-recommends \
        libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 \
        libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2 \
        libpango-1.0-0 libcairo2 fonts-liberation \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Код монтируется через volume в docker-compose; зависимости ставятся
# командой `docker compose run --rm app composer install` (см. README).

CMD ["php-fpm"]
