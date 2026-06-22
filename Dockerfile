FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libonig-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    postgresql-client \
    redis-tools \
    cron \
    libwebp-dev \
    libxpm-dev \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions one by one to identify issues
RUN docker-php-ext-install pdo
RUN docker-php-ext-install pdo_pgsql
RUN docker-php-ext-install mbstring
RUN docker-php-ext-install zip
RUN docker-php-ext-install exif
RUN docker-php-ext-install pcntl
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install gd
RUN docker-php-ext-install bcmath
RUN docker-php-ext-install curl
RUN docker-php-ext-install soap

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install MinIO server and client
RUN curl -fsSL https://dl.min.io/server/minio/release/linux-amd64/minio -o /usr/local/bin/minio \
    && curl -fsSL https://dl.min.io/client/mc/release/linux-amd64/mc -o /usr/local/bin/mc \
    && chmod +x /usr/local/bin/minio /usr/local/bin/mc

# Enable Apache modules
RUN a2enmod rewrite && a2enmod headers && a2enmod proxy && a2enmod proxy_http

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/uploads \
    && mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/html/storage/minio \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/storage

# Configure Apache
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Install PHP dependencies (vendor committed; refresh autoloader only)
RUN composer install --no-dev --optimize-autoloader --no-scripts --ignore-platform-reqs || \
    composer dump-autoload --optimize --no-dev

# Create .env file from template if it doesn't exist
RUN if [ ! -f .env ]; then cp env.example .env; fi

# Copy and make startup scripts executable
COPY docker/start.sh /usr/local/bin/start.sh
COPY docker/start-production.sh /usr/local/bin/start-production.sh
COPY docker/minio-start.sh /usr/local/bin/minio-start.sh
COPY docker/minio-setup.sh /usr/local/bin/minio-setup.sh
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/start.sh && \
    chmod +x /usr/local/bin/start-production.sh && \
    chmod +x /usr/local/bin/minio-start.sh && \
    chmod +x /usr/local/bin/minio-setup.sh && \
    chmod +x /usr/local/bin/entrypoint.sh && \
    sed -i 's/\r$//' /usr/local/bin/start.sh && \
    sed -i 's/\r$//' /usr/local/bin/start-production.sh && \
    sed -i 's/\r$//' /usr/local/bin/minio-start.sh && \
    sed -i 's/\r$//' /usr/local/bin/minio-setup.sh && \
    sed -i 's/\r$//' /usr/local/bin/entrypoint.sh

EXPOSE 80

CMD ["/bin/bash", "/usr/local/bin/entrypoint.sh"]
