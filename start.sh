#!/bin/sh

echo "Starting OM-PAY-API application..."

# Attendre que la base de données soit prête
echo "Waiting for database..."
while ! pg_isready -h $DB_HOST -p $DB_PORT -U $DB_USERNAME; do
    sleep 2
done
echo "Database is ready!"

# Exécuter les migrations
echo "Running migrations..."
php artisan migrate --force

# Exécuter les seeders en production
if [ "$APP_ENV" = "production" ]; then
    echo "Running seeders..."
    php artisan db:seed --force
fi

# Générer la documentation Swagger
echo "Generating Swagger docs..."
php artisan l5-swagger:generate

# Cacher la configuration
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Application ready!"
