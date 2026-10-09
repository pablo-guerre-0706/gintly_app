# =========================
# 1. Dependencias de Node/Vite
# =========================
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package*.json ./

RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

RUN npm run build


# =========================
# 2. Dependencias de PHP/Composer
# =========================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts

COPY . .

RUN composer dump-autoload \
    --optimize \
    --no-dev \
    --no-scripts


# =========================
# 3. Aplicación Laravel
# =========================
FROM php:8.3-apache

WORKDIR /var/www/html

ARG GIT_SHA=unknown
LABEL org.opencontainers.image.revision=$GIT_SHA

# Dependencias del sistema
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    unzip \
    git \
    openssh-server \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        intl \
        zip \
        gd \
        bcmath \
        exif \
    && rm -rf /var/lib/apt/lists/*

# App Service's authenticated WebSSH tunnel requires this platform credential.
# Port 2222 is internal; never publish it as an application/Internet port.
RUN printf '%s\n' 'root:Docker!' | chpasswd

# Activar mod_rewrite
RUN a2enmod rewrite

RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
# Configurar Apache para Laravel
RUN sed -ri \
    -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf

RUN sed -ri \
    -e 's!/var/www/!/var/www/html/public!g' \
    /etc/apache2/apache2.conf

# Copiar Laravel
COPY --from=vendor /app /var/www/html

RUN rm -f bootstrap/cache/*.php

# Copiar archivos compilados de Vite
COPY --from=frontend /app/public/build /var/www/html/public/build

COPY sshd_config /etc/ssh/sshd_config
COPY entrypoint.sh /usr/local/bin/gintly-entrypoint

# Windows checkouts may use CRLF. Validate SSH with disposable build-time keys;
# each running container generates its own keys, never inheriting image keys.
RUN sed -i 's/\r$//' /etc/ssh/sshd_config /usr/local/bin/gintly-entrypoint \
    && chmod 755 /usr/local/bin/gintly-entrypoint \
    && bash -n /usr/local/bin/gintly-entrypoint \
    && mkdir -p /run/sshd \
    && ssh-keygen -A \
    && /usr/sbin/sshd -t \
    && rm -f /etc/ssh/ssh_host_*

# Remove development pointers from the final image, never from the local workspace.
RUN rm -f public/hot public/hot.*

# Fail the build before publishing a broken PHP application or a local Vite pointer.
# This does not boot Laravel, access a database or run migrations.
RUN find app bootstrap config routes -type f -name '*.php' -print0 \
    | xargs -0 -n 1 php -l \
    && test ! -e public/hot \
    && test -s public/build/manifest.json

# Permisos de Laravel
RUN chown -R www-data:www-data /var/www/html/storage \
    /var/www/html/bootstrap/cache

RUN chmod -R 775 /var/www/html/storage \
    /var/www/html/bootstrap/cache

# HTTP and App Service's internal SSH tunnel. HTTP routing remains on port 80.
EXPOSE 80 2222

ENTRYPOINT ["/usr/local/bin/gintly-entrypoint"]
CMD ["apache2-foreground"]
