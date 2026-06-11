FROM dunglas/frankenphp:latest

RUN install-php-extensions pdo_pgsql intl zip opcache

WORKDIR /app

ENV SERVER_NAME=:80
