FROM php:8.4-apache

WORKDIR /var/www

COPY . .

RUN chown -R www-data:www-data /var/www && \
    chmod 600 bbs.cnt bbs.log && \
    chmod 700 log count && \
    chmod 644 *.php *.js && \
    a2enmod rewrite

# Set DocumentRoot to public/
ENV APACHE_DOCUMENT_ROOT /var/www/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

EXPOSE 80
