# HomeMed - Enterprise Pharmacy Cloud Container
# Multi-platform compatible: Koyeb, Fly.io, Railway, Hugging Face Spaces, VPS

FROM php:8.3-apache

# Install SQLite3 system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    sqlite3 \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite and headers modules for .htaccess routing & security
RUN a2enmod rewrite headers

# Enable .htaccess overrides in Apache document root
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Ensure runtime directories exist with appropriate www-data permissions
RUN mkdir -p /var/www/html/storage/sessions \
             /var/www/html/storage/logs \
             /var/www/html/storage/backups \
             /var/www/html/storage/prescriptions \
             /var/www/html/database \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/database \
    && chmod -R 775 /var/www/html/storage /var/www/html/database \
    && if [ -f /var/www/html/database/pharmacy.sqlite ]; then chmod 664 /var/www/html/database/pharmacy.sqlite; fi

# Copy and setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose default port (dynamic $PORT handled by entrypoint)
EXPOSE 80 8000 8080

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
