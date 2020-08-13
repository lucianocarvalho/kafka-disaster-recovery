FROM php:7.4-cli

RUN apt-get update && apt-get install -y librdkafka-dev \
    git \
    iputils-ping \
    zip \
    unzip \
    telnet

RUN cd /tmp && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

RUN pecl install rdkafka-3.1.3 && docker-php-ext-enable rdkafka

WORKDIR /var/www/html

COPY ./src/composer.* ./

RUN composer install

COPY ./src .