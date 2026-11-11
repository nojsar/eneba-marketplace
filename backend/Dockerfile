# Render has no built-in PHP runtime, so the API ships as a Docker image.
FROM php:8.3-apache

# pdo_pgsql is compiled here and needs the Postgres client headers (libpq-dev).
# unzip lets Composer unpack downloaded packages.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Production php.ini: errors are logged, never printed into an API response.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Render tells the app which port to use through $PORT (10000 by default).
# Single quotes keep ${PORT} literal so Apache reads it when it starts.
ENV PORT=10000
RUN echo 'Listen ${PORT}' > /etc/apache2/ports.conf
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Dependencies first: this layer is reused from cache when only app code changes.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize
