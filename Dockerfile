FROM php:8.3-fpm-alpine

# Installer les dépendances système et extensions PHP
RUN apk add --no-cache \
   nginx \
   libpng-dev \
   libjpeg-turbo-dev \
   freetype-dev \
   libzip-dev \
   oniguruma-dev \
   curl-dev \
   postgresql-dev \
   && docker-php-ext-configure gd --with-freetype --with-jpeg \
   && docker-php-ext-install pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip curl

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Créer les répertoires nécessaires
RUN mkdir -p /var/www/html /run/nginx

# Configuration Nginx de base
RUN echo $'server {\n\
   listen 80;\n\
   server_name localhost;\n\
   root /var/www/html/public;\n\
   index index.php index.html;\n\
   \n\
   location / {\n\
       try_files $uri $uri/ /index.php?$query_string;\n\
   }\n\
   \n\
   location ~ \\.php$ {\n\
       fastcgi_pass 127.0.0.1:9000;\n\
       fastcgi_index index.php;\n\
       fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\
       include fastcgi_params;\n\
   }\n\
   \n\
   location ~ /\\.ht {\n\
       deny all;\n\
   }\n\
}' > /etc/nginx/http.d/default.conf

# Configuration PHP-FPM
RUN echo $'[www]\n\
user = www-data\n\
group = www-data\n\
listen = 127.0.0.1:9000\n\
pm = dynamic\n\
pm.max_children = 5\n\
pm.start_servers = 2\n\
pm.min_spare_servers = 1\n\
pm.max_spare_servers = 3' > /usr/local/etc/php-fpm.d/www.conf

# Copier l'application
COPY . /var/www/html
WORKDIR /var/www/html

# Permissions
RUN if ! getent group www-data > /dev/null 2>&1; then addgroup -g 1000 www-data; fi && \
   if ! getent passwd www-data > /dev/null 2>&1; then adduser -D -s /bin/sh -u 1000 -G www-data www-data; fi && \
   chown -R www-data:www-data /var/www/html /run /var/lib/nginx /var/log/nginx

# Installer les dépendances PHP
USER www-data
RUN composer install --optimize-autoloader --no-dev --prefer-dist

# Revenir à root pour la configuration
USER root

# Générer la documentation Swagger
RUN php artisan l5-swagger:generate

# Copier la configuration de production
COPY .env.production .env

# Permissions finales
RUN chown -R www-data:www-data /var/www/html && \
   chmod -R 755 storage && \
   chmod -R 775 storage/logs storage/framework storage/app

EXPOSE 80

# Script de démarrage
RUN echo $'#!/bin/sh\n\
php artisan config:cache && \\\n\
php artisan route:cache && \\\n\
php artisan view:cache && \\\n\
nginx && \\\n\
php-fpm -F' > /start.sh && chmod +x /start.sh

CMD ["/start.sh"]
