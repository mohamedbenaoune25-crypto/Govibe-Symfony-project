FROM php:8.3-alpine

# Install system dependencies including PostgreSQL and build tools
RUN apk add --no-cache \
    postgresql-client \
    libpq-dev \
    git \
    curl \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apk del libpq-dev

# Set working directory
WORKDIR /app

# Copy files (excluding vendor and node_modules via .dockerignore)
COPY . .

# Expose port for PHP built-in server
EXPOSE 8000

# Default command: run PHP built-in server on public directory
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "-r", "index.php"]
