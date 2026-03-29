FROM php:8.4-cli-alpine3.21

RUN apk update && \
    apk add --no-cache \
        git \
        libzip-dev \
        unzip \
        zip && \
    docker-php-ext-install -j$(nproc) zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/.composer
ENV PATH=/var/app/bin:/var/app/vendor/bin:$PATH

WORKDIR /var/app
