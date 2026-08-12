# ================================================================
# DentiSoft 1.0 — Imagen de producción (PHP 8.2 + Apache)
# ================================================================
FROM php:8.2-apache

# --- Dependencias del sistema y extensiones de PHP ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        unzip \
        git \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring gd zip exif \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# --- Composer ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Configuración de Apache ---
RUN printf '\nServerName localhost\n' >> /etc/apache2/apache2.conf

# Bloquear acceso web a directorios/archivos sensibles (config, storage, dumps, etc.)
RUN { \
      echo '<DirectoryMatch "^/var/www/html/(config|storage|database|app|helpers|includes)/">'; \
      echo '    Require all denied'; \
      echo '</DirectoryMatch>'; \
      echo '<FilesMatch "\.(env|sql|md|lock)$">'; \
      echo '    Require all denied'; \
      echo '</FilesMatch>'; \
    } > /etc/apache2/conf-available/dentisoft-security.conf \
    && a2enconf dentisoft-security

WORKDIR /var/www/html

# --- Código de la aplicación ---
COPY . .

# Dependencias PHP de producción + carpetas de escritura y permisos
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p storage/logs storage/cache storage/sessions \
                assets/uploads/fotos assets/uploads/radiografias \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage assets/uploads

EXPOSE 80
