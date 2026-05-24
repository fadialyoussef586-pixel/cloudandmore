FROM php:8.2-apache

ENV MYSQL_ROOT_PASSWORD=titan_root_pass
ENV DB_NAME=titan_db
ENV PORT=80

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-server \
        supervisor \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/log/supervisor

COPY docker/mariadb.cnf /etc/mysql/mariadb.conf.d/99-docker.cnf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY . /var/www/html/

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
