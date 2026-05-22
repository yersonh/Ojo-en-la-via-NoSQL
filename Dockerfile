FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    $PHPIZE_DEPS \
    curl \
    libcurl4-openssl-dev \
    libsasl2-dev \
    libssl-dev \
    pkg-config \
    unzip \
    zlib1g-dev \
    git \
    && rm -rf /var/lib/apt/lists/*

RUN MAKEFLAGS="-j1" pecl install channel://pecl.php.net/mongodb-2.3.2 && docker-php-ext-enable mongodb

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
