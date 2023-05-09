FROM php:8.1.18-alpine

# Composer setup
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN composer require knplabs/github-api:^3.0 guzzlehttp/guzzle:^7.0.1 http-interop/http-factory-guzzle:^1.0

COPY action.php /action.php

CMD ["php", "action.php"]