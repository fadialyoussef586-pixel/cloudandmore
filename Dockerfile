FROM php:8.2-apache

# SQLite — بدون MySQL (يتوافق مع titan_production.sqlite)
RUN apt-get update \
    && apt-get install -y --no-install-recommends sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

# مجلد قاعدة البيانات المحلية — يجب أن يكون قابل للكتابة
RUN mkdir -p /var/www/html/erp/database \
    && chown -R www-data:www-data /var/www/html/erp/database \
    && chmod -R 775 /var/www/html/erp/database

WORKDIR /var/www/html
