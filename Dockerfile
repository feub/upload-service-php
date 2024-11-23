FROM php:8.2-fpm

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN usermod -u ${USER_ID} www-data && groupmod -g ${GROUP_ID} www-data

RUN apt-get update && apt-get install -y \
    libzip-dev unzip \
    && docker-php-ext-install zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

# RUN mkdir -p /var/www/html/upload
# RUN chown -R www-data:www-data /var/www/html
# RUN chmod -R 755 /var/www/html
# RUN chmod -R 777 /var/www/html/upload

EXPOSE 9000

USER www-data

CMD ["php-fpm"]
