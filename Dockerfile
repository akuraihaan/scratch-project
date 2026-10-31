FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

FROM php:8.3-cli-alpine

WORKDIR /app

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        libxml2-dev \
        oniguruma-dev \
        sqlite-dev \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        pdo_sqlite \
        xml \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --optimize-autoloader \
        --prefer-dist

COPY . ./
COPY --from=frontend /app/public/build ./public/build

RUN chmod +x railway/start.sh \
    && mkdir -p /data/uploads \
    && rm -rf storage/app/public \
    && ln -s /data/uploads storage/app/public

EXPOSE 8080

CMD ["sh", "railway/start.sh"]
