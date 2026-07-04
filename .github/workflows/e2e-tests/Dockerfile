ARG PHP_VERSION
FROM php:${PHP_VERSION}-apache

ENV MAGENTO_HOST="<will be defined>" \
DB_SERVER="<will be defined>" \
DB_PORT=3306 \
DB_NAME=magento \
DB_USER=magento \
DB_PASSWORD=magento \
DB_PREFIX=m2_ \
OPENSEARCH_SERVER="<will be defined>" \
OPENSEARCH_PORT=9200 \
OPENSEARCH_INDEX_PREFIX=magento2 \
OPENSEARCH_TIMEOUT=15 \
ADMIN_NAME=admin \
ADMIN_LASTNAME=admin \
ADMIN_EMAIL=admin@example.com \
ADMIN_USERNAME=admin \
ADMIN_PASSWORD=admin123 \
ADMIN_URLEXT=admin \
MAGENTO_LANGUAGE=pt_BR \
MAGENTO_CURRENCY=BRL \
MAGENTO_TZ=America/Sao_Paulo \
DEPLOY_SAMPLEDATA=0 \
USE_SSL=0

RUN apt-get update \
    && apt-get install -y libjpeg62-turbo-dev \
        libpng-dev \
        libfreetype6-dev \
        libicu-dev \
        libxml2-dev \
        libzip-dev \
        libssl-dev \
        libxslt-dev \
        default-mysql-client \
        wget \
        cron \
        unzip

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) bcmath ftp gd intl pdo_mysql simplexml soap sockets xsl zip
RUN a2enmod rewrite

# Ship Composer in the image instead of persisting it through a named volume
# mounted over /usr/local/bin. That volume used to freeze the whole bin dir
# (including the php binary) from the first image it was built against, so a
# later PHP bump kept running the old php and every extension failed to load.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN rm -f /etc/apache2/sites-enabled/default-ssl.conf

WORKDIR /var/www/html
COPY php.ini /usr/local/etc/php/
COPY install_magento.sh /tmp/install_magento.sh
COPY install_extension.sh /tmp/install_extension.sh

ARG MAGENTO_VERSION
# Download the Magento source from the public mirror. Use wget with retries: the
# on-the-fly GitHub archive endpoint intermittently returns 502 for a repo this
# large, and `ADD <url>` does not retry (it even fails while computing the build
# cache key on a 502). wget is installed in the apt step above.
RUN wget --tries=5 --waitretry=15 --retry-on-http-error=429,500,502,503,504 \
    -O /tmp/magento.tar.gz \
    "https://github.com/magento/magento2/archive/refs/tags/${MAGENTO_VERSION}.tar.gz"

# The magento/magento2 source archive above does NOT bundle the security-package
# (Magento_TwoFactorAuth, ReCaptcha, ...) that the CE metapackage would pull in from
# repo.magento.com. Without Magento_TwoFactorAuth this install is unrepresentative of
# a real store and setup:di:compile fails to autoload the 2FA types that modules
# reference. Fetch the Apache-2.0 source here (auth-free — no Marketplace keys); the
# TwoFactorAuth module is dropped into app/code by install_magento.sh, which pulls
# its third-party libs from packagist. Keep aligned with MAGENTO_VERSION's patch line.
ARG SECURITY_PACKAGE_VERSION=1.1.7-p5
RUN wget --tries=5 --waitretry=15 --retry-on-http-error=429,500,502,503,504 \
    -O /tmp/security-package.tar.gz \
    "https://github.com/magento/security-package/archive/refs/tags/${SECURITY_PACKAGE_VERSION}.tar.gz"

RUN chmod +x /tmp/install_magento.sh
RUN chmod +x /tmp/install_extension.sh

CMD ["bash", "/tmp/install_magento.sh"]
