FROM php:8.0.3-fpm AS php

# Set user and group
ARG UID=1000
ARG GID=1000

RUN usermod --uid ${UID} www-data && \
    groupmod --gid ${GID} www-data

# Install packages
RUN apt-get update && apt-get install -y \
    wget \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpq-dev \
    gosu \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Install PHP extensions
RUN pecl install xdebug-3.0.4 \
    && docker-php-ext-enable  xdebug

RUN docker-php-ext-configure \
    intl

RUN docker-php-ext-install -j$(nproc) \
    intl \
    pdo \
    mysqli \
    pdo_mysql \
    opcache \
    zip

## Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

## Custom Ini file
COPY ini/docker-php.ini /usr/local/etc/php/conf.d/docker-php.ini

COPY entrypoint-dev.sh /usr/local/bin/entrypoint-dev.sh
RUN chmod +x /usr/local/bin/entrypoint-dev.sh
ENTRYPOINT ["entrypoint-dev.sh"]
CMD ["php-fpm"]