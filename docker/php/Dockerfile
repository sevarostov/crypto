FROM php:8.4-fpm AS php

RUN apt update \
    && docker-php-ext-install pdo pdo_mysql \
    && docker-php-ext-install bcmath

#dev env
#RUN pecl install xdebug-2.9.2 && docker-php-ext-enable xdebug

WORKDIR /var/www/html
ENTRYPOINT ["php-fpm","-F"]
