FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    default-mysql-client \
    netcat-openbsd \
    && docker-php-ext-install mysqli

WORKDIR /var/www

COPY ../../.. /var/www

COPY .ops/script/mysql/await_uptime.sh /usr/local/bin/await_uptime.sh
RUN chmod +x /usr/local/bin/await_uptime.sh

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN composer install

CMD ["await_uptime.sh", "host.docker.internal", "user", "password", "--", "php", "./public/index.php"]
