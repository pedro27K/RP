FROM php:8.2-apache

# Extensiones PHP
#  - pcntl: necesaria para el worker multiproceso (tools/recordatorios.php)
RUN docker-php-ext-install mysqli pdo pdo_mysql pcntl

# Composer + dependencias del sistema
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl unzip git \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Apache — desactivar MPMs extra para evitar conflicto AH00534
RUN sed -i 's/^LoadModule mpm_event_module/#LoadModule mpm_event_module/' /etc/apache2/mods-enabled/mpm_event.load 2>/dev/null || true \
    && sed -i 's/^LoadModule mpm_worker_module/#LoadModule mpm_worker_module/' /etc/apache2/mods-enabled/mpm_worker.load 2>/dev/null || true
RUN a2enmod rewrite headers
RUN echo 'AddType video/mp4 .mp4' >> /etc/apache2/mime.types

# Copiar composer.json y composer.lock e instalar dependencias PHP
COPY composer.json composer.lock /var/www/html/
RUN cd /var/www/html && composer install --no-dev --no-interaction --optimize-autoloader

# Copiar el resto de la aplicación
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Entrypoint: arranca Apache
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
