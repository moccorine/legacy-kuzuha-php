FROM php:8.3-apache

WORKDIR /var/www/html

COPY . .

RUN chown -R www-data:www-data /var/www/html && \
    chmod 600 bbs.cnt bbs.log && \
    chmod 700 log count && \
    chmod 644 *.php *.js && \
    a2enmod rewrite

EXPOSE 80
