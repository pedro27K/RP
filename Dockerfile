FROM php:8.2-apache

# Extensiones PHP
#  - pcntl: necesaria para el worker multiproceso (tools/recordatorios.php)
RUN docker-php-ext-install mysqli pdo pdo_mysql pcntl

# msmtp + certificados TLS para conectar a smtp.gmail.com
RUN apt-get update && apt-get install -y --no-install-recommends msmtp ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# PHP mail() → msmtp
RUN echo 'sendmail_path = "/usr/bin/msmtp -t"' \
    > /usr/local/etc/php/conf.d/mail.ini

# Apache — desactivar MPMs extra para evitar conflicto AH00534
RUN sed -i 's/^LoadModule mpm_event_module/#LoadModule mpm_event_module/' /etc/apache2/mods-enabled/mpm_event.load 2>/dev/null || true \
    && sed -i 's/^LoadModule mpm_worker_module/#LoadModule mpm_worker_module/' /etc/apache2/mods-enabled/mpm_worker.load 2>/dev/null || true
RUN a2enmod rewrite headers
RUN echo 'AddType video/mp4 .mp4' >> /etc/apache2/mime.types

# Entrypoint: genera msmtprc con las credenciales de entorno y arranca Apache
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

ENTRYPOINT ["/entrypoint.sh"]