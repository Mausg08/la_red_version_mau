FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql mysqli curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers proxy proxy_http proxy_wstunnel

WORKDIR /var/www/html

COPY docker/apache.conf /etc/apache2/sites-enabled/000-default.conf
