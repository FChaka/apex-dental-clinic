# ============================================================
# Stage 1: Node — build frontend assets with Vite
# ============================================================
FROM node:22-alpine AS node-builder

WORKDIR /app

# Copy package manifests and install dependencies
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

# Copy source files needed for the Vite build
COPY vite.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/

# Build production assets
RUN npm run build

# ============================================================
# Stage 2: PHP — production image
# ============================================================
FROM php:8.4-fpm AS php-base

# ── System packages ──────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Core utilities
    curl \
    unzip \
    git \
    # GD dependencies (intervention/image + dompdf image processing)
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libavif-dev \
    # ZIP support (composer, package extraction)
    libzip-dev \
    # XML / DOM (dompdf, Laravel)
    libxml2-dev \
    # oniguruma for mbstring
    libonig-dev \
    # OpenSSL
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ────────────────────────────────────────────
# Configure GD with full format support
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
        --with-avif

RUN docker-php-ext-install -j"$(nproc)" \
    # Laravel core
    ctype \
    dom \
    fileinfo \
    filter \
    mbstring \
    opcache \
    pcre \
    pdo \
    session \
    tokenizer \
    xml \
    xmlwriter \
    # Database
    pdo_mysql \
    # Image processing (intervention/image, dompdf)
    gd \
    # Archive support (composer, zip extraction)
    zip \
    # Process control (queue workers)
    pcntl \
    # POSIX (queue workers)
    posix

# ── Composer ─────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── PHP configuration ─────────────────────────────────────────
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=256"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=20000"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.save_comments=1"; \
    echo "opcache.fast_shutdown=1"; \
} > /usr/local/etc/php/conf.d/opcache.ini

RUN { \
    echo "upload_max_filesize=64M"; \
    echo "post_max_size=64M"; \
    echo "memory_limit=256M"; \
    echo "max_execution_time=120"; \
} > /usr/local/etc/php/conf.d/app.ini

# ============================================================
# Stage 3: Application
# ============================================================
FROM php-base AS app

WORKDIR /var/www/html

# ── Composer dependencies ─────────────────────────────────────
# Copy manifests first for layer caching
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

# ── Application source ────────────────────────────────────────
COPY . .

# ── Pre-built frontend assets from Stage 1 ───────────────────
COPY --from=node-builder /app/public/build ./public/build

# ── Run composer post-install scripts now that full app is present ──
RUN composer run-script post-autoload-dump --no-interaction || true

# ── Storage & cache directories ───────────────────────────────
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# ── Entrypoint script ─────────────────────────────────────────
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
