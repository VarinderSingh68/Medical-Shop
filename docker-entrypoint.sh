#!/bin/sh
set -e

# Support dynamic PORT injection (Koyeb, Railway, Cloud Run, Fly.io)
PORT="${PORT:-80}"

echo "Starting HomeMed Pharmacy server on port ${PORT}..."

# Configure Apache listening port dynamically
sed -i "s/Listen [0-9]*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Re-apply runtime write permissions for SQLite and file uploads
mkdir -p /var/www/html/storage/sessions /var/www/html/storage/logs /var/www/html/storage/backups /var/www/html/storage/prescriptions /var/www/html/database
chown -R www-data:www-data /var/www/html/storage /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/database
if [ -f /var/www/html/database/pharmacy.sqlite ]; then
    chmod 664 /var/www/html/database/pharmacy.sqlite
fi

exec apache2-foreground
