FROM php:8.2-apache

ENV PORT=80

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pgsql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-entrypoint.sh /usr/local/bin/apache-entrypoint.sh
RUN chmod +x /usr/local/bin/apache-entrypoint.sh

COPY . /var/www/html/

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/apache-entrypoint.sh"]
