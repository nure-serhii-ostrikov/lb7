FROM php:7.4-apache
RUN apt-get update && apt-get install -y sqlite3 libsqlite3-dev
RUN docker-php-ext-install pdo pdo_mysql pdo_sqlite

COPY . /var/www/html/

RUN sqlite3 /var/www/html/logs.db "CREATE TABLE IF NOT EXISTS logs (id INTEGER PRIMARY KEY AUTOINCREMENT, action TEXT NOT NULL, parameters TEXT, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP);"
RUN chmod +w /var/www/html/logs.db
RUN chown -R www-data:www-data /var/www/html
