# Matches Vercel's official FrankenPHP PHP 8.4 Docker guide.
FROM dunglas/frankenphp:1-php8.4-alpine
WORKDIR /app
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && install-php-extensions pdo_pgsql mbstring
COPY config/php-production.ini "$PHP_INI_DIR/conf.d/99-gridlock.ini"
COPY Caddyfile /etc/caddy/Caddyfile
COPY includes ./includes
COPY public ./public
ENV PORT=80
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
