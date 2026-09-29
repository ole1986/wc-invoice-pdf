FROM wordpress:php8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev msmtp-mta \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*

RUN { \
    echo 'xdebug.mode=develop,debug'; \
    echo 'xdebug.start_with_request=yes'; \
    echo 'xdebug.client_host=host.docker.internal'; \
    echo 'xdebug.client_port=9003'; \
    echo 'xdebug.log_level=0'; \
    } > /usr/local/etc/php/conf.d/xdebug-settings.ini

RUN echo 'memory_limit=256M' > /usr/local/etc/php/conf.d/docker-php-memlimit.ini
