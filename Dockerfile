FROM php:7.3-cli-alpine

RUN apk add --no-cache imap-dev openssl-dev

RUN docker-php-ext-configure imap --with-imap-ssl

RUN docker-php-ext-install imap

WORKDIR "/app"

CMD ["php", "index.php"]
