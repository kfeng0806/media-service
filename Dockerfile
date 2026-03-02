FROM nfrastack/nginx-php-fpm:8.5-alpine

ENV APPROOT=/app \
    # ── Nginx ──
    NGINX_WEBROOT=/app/public \
    NGINX_INDEX_FILE=index.php \
    NGINX_ENABLE_CREATE_SAMPLE_HTML=FALSE \
    NGINX_ENABLE_LOG_FAVICON=FALSE \
    NGINX_ENABLE_LOG_ROBOTS=FALSE \
    NGINX_UPLOAD_MAX_SIZE=30M \
    # ── PHP ──
    PHP_MEMORY_LIMIT=512M \
    PHP_UPLOAD_MAX_SIZE=30M \
    PHP_POST_MAX_SIZE=30M \
    PHP_TIMEOUT=60 \
    PHP_CREATE_SAMPLE_PHP=FALSE \
    # ── PHP-FPM Pool ──
    PHPFPM_POOL_DEFAULT_MAX_CHILDREN=30 \
    PHPFPM_POOL_DEFAULT_START_SERVERS=5 \
    PHPFPM_POOL_DEFAULT_MIN_SPARE_SERVERS=5 \
    PHPFPM_POOL_DEFAULT_MAX_SPARE_SERVERS=25 \
    PHPFPM_POOL_DEFAULT_MAX_REQUESTS=100 \
    PHPFPM_POOL_DEFAULT_MAX_INPUT_VARS=20000 \
    # ── PHP Modules ──
    PHP_MODULE_ENABLE_PCNTL=TRUE \
    PHP_MODULE_ENABLE_POSIX=TRUE \
    PHP_MODULE_ENABLE_ZIP=TRUE \
    PHP_MODULE_ENABLE_REDIS=TRUE \
    PHP_MODULE_ENABLE_FFI=TRUE \
    PHP_MODULE_ENABLE_OPCACHE=FALSE \
    # ── Container Scheduling ──
    CONTAINER_ENABLE_SCHEDULING=TRUE \
    NGINX_USER=nginx \
    NGINX_GROUP=www-data

RUN apk add --no-cache \
    ffmpeg \
    vips

RUN php-ext enable openssl \
    && php-ext enable phar \
    && php-ext enable iconv \
    && php-ext enable mbstring \
    && php-ext enable session \
    && php-ext enable msgpack \
    && php-ext enable redis \
    && php-ext enable zip \
    && php-ext enable pcntl \
    && php-ext enable posix \
    && php-ext enable ffi \
    && php-ext enable fileinfo \
    && php-ext enable tokenizer \
    && php-ext enable xml \
    && php-ext enable dom \
    && php-ext enable simplexml \
    && php-ext enable ctype

COPY deployment/php/opcache.ini /etc/php85/conf.d/10-opcache.ini

RUN mkdir -p ${APPROOT}

WORKDIR ${APPROOT}

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts --no-interaction --no-ansi

COPY . .

RUN mv .env.deployment .env \
    && composer dump-autoload --optimize --no-dev

RUN chown -R nginx:www-data ${APPROOT}/storage ${APPROOT}/bootstrap/cache

RUN mkdir -p /etc/nginx/sites.available/default/location
COPY deployment/nginx/locations/laravel.conf /etc/nginx/sites.available/default/location/01-laravel.conf
COPY deployment/nginx/locations/data-storage.conf /etc/nginx/sites.available/default/location/02-data-storage.conf

COPY deployment/s6/horizon/run /container/run/available/horizon/run
RUN chmod +x /container/run/available/horizon/run

COPY deployment/init/30-laravel /container/init/init.d/30-laravel
RUN chmod +x /container/init/init.d/30-laravel

COPY deployment/shaka-packager/packager-linux-x64-3-4-2 /usr/local/bin/shaka-packager
RUN chmod +x /usr/local/bin/shaka-packager
