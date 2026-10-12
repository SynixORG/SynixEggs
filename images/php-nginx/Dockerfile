# syntax=docker/dockerfile:1
# ----------------------------------
# Environment: nginx (stable) + PHP-FPM on Alpine
# ----------------------------------
ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-fpm-alpine

# nginx stable branch from nginx.org. Patch releases are picked up on every
# rebuild; bump this when nginx.org promotes a new stable branch.
ARG NGINX_BRANCH=1.30
ARG PHPREDIS_VERSION=6.3.0
# SHA-512 of https://nginx.org/keys/nginx_signing.rsa.pub (same check as the official nginx image)
ARG NGINX_KEY_SHA512=e09fa32f0a0eab2b879ccbbc4d0e4fb9751486eedda75e35fac65802cc9faa266425edf83e261137a2f4d16281ce2c1a5f4502930fe75154723da014214f0655

# Runtime tools + nginx from the signed nginx.org repository
RUN set -eux; \
    apk upgrade --no-cache; \
    apk add --no-cache bash; \
    curl -fsSL -o /tmp/nginx_signing.rsa.pub https://nginx.org/keys/nginx_signing.rsa.pub; \
    echo "${NGINX_KEY_SHA512} */tmp/nginx_signing.rsa.pub" | sha512sum -c -; \
    mv /tmp/nginx_signing.rsa.pub /etc/apk/keys/; \
    ALPINE_BRANCH="$(grep -oE '^[0-9]+\.[0-9]+' /etc/alpine-release)"; \
    # nginx.org's package needs these libraries from the Alpine repos ...
    apk add --no-cache so:libpcre2-8.so.0 so:libssl.so.3 so:libcrypto.so.3 so:libz.so.1; \
    # ... while nginx itself must come from nginx.org only (same as the official image)
    apk add --no-cache --repositories-file /dev/null \
        -X "https://nginx.org/packages/alpine/v${ALPINE_BRANCH}/main" \
        "nginx~${NGINX_BRANCH}"; \
    rm -rf /etc/nginx/conf.d /etc/nginx/nginx.conf.default; \
    nginx -v

# PHP extensions: a minimal set covering WordPress, Laravel and most CMSes
RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS linux-headers \
        freetype-dev icu-dev libjpeg-turbo-dev libpng-dev libwebp-dev libzip-dev postgresql-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" bcmath exif gd intl mysqli pdo_mysql pdo_pgsql zip; \
    php -m | grep -qi '^Zend OPcache$' || docker-php-ext-install opcache; \
    pecl install -D 'enable-redis-igbinary="no" enable-redis-lzf="no" enable-redis-zstd="no" enable-redis-msgpack="no" enable-redis-lz4="no"' \
        "redis-${PHPREDIS_VERSION}"; \
    docker-php-ext-enable redis; \
    runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
        | tr ',' '\n' | sort -u \
        | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )"; \
    apk add --no-cache $runDeps; \
    apk del --no-network .build-deps; \
    rm -rf /tmp/pear ~/.pearrc; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    rm -f /usr/local/etc/php-fpm.d/*.conf /usr/local/etc/php-fpm.conf.default; \
    php -m

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Defaults for the panel variables, overridden by the egg
ENV PHP_MEMORY_LIMIT=256M \
    UPLOAD_MAX_SIZE=64M \
    PHP_MAX_EXECUTION_TIME=60 \
    PHP_FPM_MAX_CHILDREN=auto \
    PHP_DISABLE_FUNCTIONS= \
    WEBROOT=public \
    ACCESS_LOG=1 \
    COMPOSER_AUTO_INSTALL=0 \
    TZ=UTC \
    PHP_INI_SCAN_DIR=/usr/local/etc/php/conf.d:/home/container/php/conf.d

COPY rootfs/ /
RUN set -eux; \
    chmod 0755 /entrypoint.sh /usr/local/bin/start-web; \
    adduser -D -h /home/container -s /bin/bash container; \
    install -d -o container -g container /tmp/nginx /home/container/.cache /home/container/.cache/nginx

USER        container
ENV         USER=container HOME=/home/container

# Self-test as the unprivileged user (php-fpm refuses to run as root), one check
# per step so a failure names the check. Output is piped through cat because the
# build's stderr belongs to root and php-fpm re-opens its error_log path.
RUN set -euxo pipefail; \
    FPM_MAX_CHILDREN=4 php-fpm -t 2>&1 | cat
RUN set -eux; \
    nginx -t -e stderr
RUN --mount=type=bind,source=selftest.php,target=/tmp/selftest.php \
    php /tmp/selftest.php
RUN set -eux; \
    for ext in pdo_mysql mbstring json filter hash openssl date random standard 'Zend OPcache'; do \
        php-fpm -m | grep -qixF "$ext" || { echo "php-fpm is missing $ext"; exit 1; }; \
    done
# No cleanup needed: Wings mounts the server files over /home/container and a
# fresh tmpfs over /tmp, so the test leftovers are never visible at runtime.

WORKDIR     /home/container
STOPSIGNAL  SIGINT

CMD ["/bin/bash", "/entrypoint.sh"]
