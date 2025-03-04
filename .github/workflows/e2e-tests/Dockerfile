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
        libxml2-dev \
        libzip-dev \
        libssl-dev \
        libxslt-dev \
        default-mysql-client \
        wget \
        cron \
        unzip

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) bcmath gd intl pdo_mysql simplexml soap sockets xsl zip
RUN a2enmod rewrite

RUN rm -f /etc/apache2/sites-enabled/default-ssl.conf

WORKDIR /var/www/html
COPY php.ini /usr/local/etc/php/
COPY install_magento.sh /tmp/install_magento.sh
COPY install_extension.sh /tmp/install_extension.sh

ARG MAGENTO_VERSION
ADD "https://github.com/magento/magento2/archive/refs/tags/${MAGENTO_VERSION}.tar.gz" /tmp/magento.tar.gz

RUN chmod +x /tmp/install_magento.sh
RUN chmod +x /tmp/install_extension.sh

CMD ["bash", "/tmp/install_magento.sh"]
