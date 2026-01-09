FROM php:8.2-fpm

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip

# Installation des extensions PHP
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd

# Installation de Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Installation de Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/cena-api

# Copier tout le code Laravel d'abord
COPY cena-api/ .

# Installer les dépendances PHP (sans scripts)
RUN composer install --no-scripts --no-autoloader --no-dev

# Générer l'autoload
RUN composer dump-autoload --optimize

# Installer les dépendances Node
RUN npm install

# Permissions
RUN chown -R www-data:www-data /var/www/cena-api

EXPOSE 9000 5173

# Commande de démarrage en mode DEV
CMD php artisan serve --host=0.0.0.0 --port=9000 & npm run dev -- --host