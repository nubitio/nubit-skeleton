FROM dunglas/frankenphp:latest

RUN install-php-extensions pdo_pgsql intl zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker/entrypoint.sh /usr/local/bin/nubit-entrypoint.sh
RUN chmod +x /usr/local/bin/nubit-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/nubit-entrypoint.sh"]
# Setting a new ENTRYPOINT clears any CMD inherited from the base image —
# restated here so the container still boots FrankenPHP the same way it did
# before this entrypoint wrapper existed.
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

ENV SERVER_NAME=:80
