FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    curl \
    libssl-dev \
    pkg-config \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install mongodb && docker-php-ext-enable mongodb

RUN docker-php-ext-install opcache

WORKDIR /app
COPY . .

RUN curl -sS https://getcomposer.org/installer | php && \
    php composer.phar install --no-interaction --optimize-autoloader

RUN mkdir -p storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs

CMD php -S 0.0.0.0:$PORT -t public /app/router.php