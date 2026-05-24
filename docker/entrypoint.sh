#!/bin/bash
set -e

MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-titan_root_pass}"
DB_NAME="${DB_NAME:-titan_db}"
PORT="${PORT:-80}"

# تهيئة MariaDB عند أول تشغيل
if [ ! -d "/var/lib/mysql/mysql" ]; then
    echo ">> Initializing MariaDB..."
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1

    mysqld --user=mysql --datadir=/var/lib/mysql &
    for _ in $(seq 1 60); do
        mysqladmin ping -h127.0.0.1 --silent 2>/dev/null && break
        sleep 1
    done

    mysql -h127.0.0.1 -uroot <<EOSQL
ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
FLUSH PRIVILEGES;
EOSQL

    mysqladmin -h127.0.0.1 -uroot shutdown
    sleep 2
fi

# Render يحدد المنفذ عبر متغير PORT
sed -i "s/^Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
if [ -f /etc/apache2/sites-available/000-default.conf ]; then
    sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
fi

echo ">> Starting MariaDB + Apache on port ${PORT}..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
