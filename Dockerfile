FROM php:8.2-cli

# Installer les dépendances système nécessaires
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    nodejs \
    npm \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copier les fichiers de dépendances d'abord (meilleur cache Docker)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs

COPY package.json package-lock.json* ./
RUN npm install

# Copier le reste du code
COPY . .

# Finaliser l'installation composer + build des assets front (Vite)
RUN composer dump-autoload --optimize
RUN npm run build

# Permissions Laravel
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 10000

# Render injecte la variable $PORT, Laravel doit écouter dessus
CMD php artisan config:cache && \
    php artisan route:cache && \
    php artisan migrate --force && \
    php artisan serve --host 0.0.0.0 --port $PORT