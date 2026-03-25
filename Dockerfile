# =============================================================
# Sophishticated — Multi-stage Dockerfile
# =============================================================
# Targets:
#   - frontend-build : Node.js build of the Vue.js SPA
#   - php-base       : Shared PHP layer (extensions + composer)
#   - app            : Apache + PHP + cron  (default)
#   - worker         : Queue worker (php worker/worker.php)
#   - websocket      : Ratchet WebSocket server
# =============================================================

# ── Stage 1: Build frontend ─────────────────────────────────
FROM node:20-alpine AS frontend-build

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci

COPY frontend/ frontend/
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build


# ── Stage 2: Shared PHP base ────────────────────────────────
FROM php:8.2-apache AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
        libzip-dev \
        cron \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        pcntl \
        sockets \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/local/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Copy application source
COPY backend/       backend/
COPY cron/          cron/
COPY database/      database/
COPY public/        public/
COPY scripts/       scripts/
COPY templates/     templates/
COPY websocket/     websocket/
COPY worker/        worker/
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Copy built frontend assets from stage 1
COPY --from=frontend-build /build/public/dist/ public/dist/
# Copy the built index.html (produced by Vite)
COPY --from=frontend-build /build/public/dist/index.html public/dist/index.html

# Create storage directories
RUN mkdir -p storage/logs storage/pids \
    && chown -R www-data:www-data storage templates


# ── Stage 3: App (Apache + cron) ── DEFAULT ─────────────────
FROM php-base AS app

# Enable Apache modules
RUN a2enmod rewrite proxy proxy_wstunnel proxy_http headers

# Apache virtual host
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Crontab for check-read-status (every minute)
COPY docker/crontab /etc/cron.d/sophishticated
RUN chmod 0644 /etc/cron.d/sophishticated \
    && crontab /etc/cron.d/sophishticated

# Entrypoint: starts cron daemon then Apache
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]


# ── Stage 4: Worker ─────────────────────────────────────────
FROM php:8.2-cli AS worker

RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        pcntl \
        sockets \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/local/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY backend/       backend/
COPY templates/     templates/
COPY worker/        worker/
COPY composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --optimize-autoloader
RUN mkdir -p storage/logs storage/pids

COPY docker/wait-for-db.sh /wait-for-db.sh
RUN chmod +x /wait-for-db.sh

CMD ["/wait-for-db.sh", "php", "worker/worker.php"]


# ── Stage 5: WebSocket ──────────────────────────────────────
FROM php:8.2-cli AS websocket

RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        pcntl \
        sockets \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/local/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY backend/       backend/
COPY websocket/     websocket/
COPY composer.json composer.lock ./

RUN composer install --no-dev --no-interaction --optimize-autoloader
RUN mkdir -p storage/logs

COPY docker/wait-for-db.sh /wait-for-db.sh
RUN chmod +x /wait-for-db.sh

EXPOSE 8081

CMD ["/wait-for-db.sh", "php", "websocket/server.php"]
