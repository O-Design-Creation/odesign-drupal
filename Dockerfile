# ──────────────────────────────────────────────────────────────────────────────
# odesign-drupal — Image de production / préprod
# Base : PHP 8.3 FPM + nginx + supervisord (conteneur unique)
# Architecture : ADR-013 — image immuable taggée, promue de preprod → prod
# ──────────────────────────────────────────────────────────────────────────────

FROM php:8.3-fpm-bookworm AS base

# ── Dépendances système ────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libicu-dev \
    libonig-dev \
    libsodium-dev \
    mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# ── Extensions PHP ─────────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) \
    gd \
    pdo \
    pdo_mysql \
    intl \
    zip \
    mbstring \
    xml \
    bcmath \
    sodium \
    opcache

# ── PHP config ─────────────────────────────────────────────────────────────────
COPY .docker/php.ini /usr/local/etc/php/conf.d/odesign.ini

# ── Composer ───────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Build stage : installation des dépendances Composer ───────────────────────
FROM base AS builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

COPY . .
RUN composer run-script post-install-cmd --no-interaction 2>/dev/null || true

# ── Image finale ───────────────────────────────────────────────────────────────
FROM base AS final

# ── Version applicative (injectée par GitHub Actions via --build-arg) ──────────
# Permet au back-office d'afficher la version déployée (TCK-133).
ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION}

WORKDIR /var/www/html

# Copier le code compilé depuis le builder
COPY --from=builder /app /var/www/html

# ── Nginx config ───────────────────────────────────────────────────────────────
COPY .docker/nginx.conf /etc/nginx/sites-available/default
RUN ln -sf /dev/stdout /var/log/nginx/access.log \
 && ln -sf /dev/stderr /var/log/nginx/error.log

# ── Supervisord ────────────────────────────────────────────────────────────────
COPY .docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ── Entrypoint ─────────────────────────────────────────────────────────────────
COPY .docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# ── Seeds : fichiers initiaux séparés du volume persistent ────────────────────
# Les images de seeding (produits, blog) sont copiées dans /var/www/html/seeds/
# L'entrypoint les copie dans sites/default/files/ si le volume est vide.
# Ainsi, les uploads du gestionnaire ne sont jamais écrasés par un redéploiement.
RUN mkdir -p /var/www/html/seeds \
 && if [ -d /var/www/html/web/sites/default/files/produits ]; then \
      cp -r /var/www/html/web/sites/default/files/produits /var/www/html/seeds/; \
    fi \
 && if [ -d /var/www/html/web/sites/default/files/shopify-blog ]; then \
      cp -r /var/www/html/web/sites/default/files/shopify-blog /var/www/html/seeds/; \
    fi

# ── Permissions Drupal ─────────────────────────────────────────────────────────
RUN mkdir -p /var/www/html/web/sites/default/files \
 && chown -R www-data:www-data /var/www/html \
 && chmod 755 /var/www/html/web/sites/default/files

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
