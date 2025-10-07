# Stage 1: Build dependencies with dev packages
FROM php:8.3-fpm AS builder

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    graphviz \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    zlib1g-dev

# Configure GD extension
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files
COPY composer.json composer.lock ./

# Install all dependencies including dev
RUN composer install --prefer-dist --no-scripts --no-autoloader

# Copy application code
COPY . .

# Generate optimized autoload files
RUN composer dump-autoload

# Stage 2: Final production image
FROM php:8.3-fpm

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    supervisor \
    graphviz \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    zlib1g-dev

# Configure GD extension
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHPDocumentor
RUN curl -L https://github.com/phpDocumentor/phpDocumentor/releases/download/v3.4.3/phpDocumentor.phar -o /usr/local/bin/phpdoc \
    && chmod +x /usr/local/bin/phpdoc

# Copy existing application directory contents
COPY . /var/www

# Copy vendor directory from builder stage
COPY --from=builder /var/www/vendor /var/www/vendor

# Copy existing application directory permissions
COPY --chown=www-data:www-data . /var/www



# Create required directories
RUN mkdir -p /var/www/storage/logs \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/bootstrap/cache

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor

# Copy scripts
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/init.sh /usr/local/bin/init.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/init.sh

# Expose port 9000 and start php-fpm server
EXPOSE 9000

# Use entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
