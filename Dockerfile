FROM php:8.2-apache
COPY . /var/www/html/
RUN docker-php-ext-install mysqli pdo pdo_mysql
FROM php:8.2-apache

# تثبيت إضافات الـ PHP للداتابيز
RUN docker-php-ext-install mysqli pdo pdo_mysql

# تشغيل الـ SQLite كقاعدة بيانات محلية سريعة جوات السيرفر بدون وجع راس ومواقع تانية
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev

COPY . /var/www/html/
