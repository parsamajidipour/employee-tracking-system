FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        unzip \
        git \
    # pcntl: laravel/reverb's server process (php artisan reverb:start,
    # started alongside `serve` in docker-compose.yml) needs it for signal
    # handling (SIGINT/SIGTERM) — undefined-constant fatal error without it.
    && docker-php-ext-install pdo pdo_pgsql pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize && php artisan package:discover --ansi

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
