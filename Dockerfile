FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libsqlite3-dev \
    unzip \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application code
COPY . .

# Run post-install scripts
RUN composer run-script post-autoload-dump

# Create SQLite database
RUN touch database/database.sqlite

# Generate app key and run migrations
RUN php artisan key:generate --force
RUN php artisan migrate --force

# Set permissions
RUN chmod -R 775 storage bootstrap/cache database

# Expose port
EXPOSE 8000

# Health check
HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

# Start the application
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
