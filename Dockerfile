FROM php:8.3-alpine

# Install system dependencies including PostgreSQL, GD, and build tools
RUN apk add --no-cache \
    postgresql-client \
    libpq-dev \
    git \
    curl \
    freetype \
    libjpeg-turbo \
    libpng \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apk del libpq-dev freetype-dev libjpeg-turbo-dev libpng-dev

# Set working directory
WORKDIR /app

# Copy files (excluding vendor and node_modules via .dockerignore)
COPY . .

# Expose port for PHP built-in server
EXPOSE 8000

# Default command: run PHP built-in server on public directory
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "-r", "index.php"]
