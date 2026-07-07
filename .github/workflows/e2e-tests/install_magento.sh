#!/bin/bash

MAGENTO_INSTALL_ARGS="";

if [ "$DB_SERVER" != "<will be defined>" ]; then
	RET=1
	while [ $RET -ne 0 ]; do
		echo "Checking if $DB_SERVER is available."
		mysql -h "$DB_SERVER" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -e "status" >/dev/null 2>&1
		RET=$?

		if [ $RET -ne 0 ]; then
			echo "Connection to MySQL/MariaDB is pending."
			sleep 5
		fi
	done
	echo "DB server $DB_SERVER is available."
else
	echo "MySQL/MariaDB server is not defined!"
	exit 1
fi

if [ "$OPENSEARCH_SERVER" != "<will be defined>" ]; then
	MAGENTO_INSTALL_ARGS=$(echo \
	    --search-engine="opensearch" \
		--opensearch-host="$OPENSEARCH_SERVER" \
		--opensearch-port="$OPENSEARCH_PORT" \
		--opensearch-index-prefix="$OPENSEARCH_INDEX_PREFIX" \
		--opensearch-timeout="$OPENSEARCH_TIMEOUT")
	RET=1
	while [ $RET -ne 0 ]; do
		echo "Checking if $OPENSEARCH_SERVER is available."
		curl -XGET "$OPENSEARCH_SERVER:$OPENSEARCH_PORT/_cat/health?v&pretty" >/dev/null 2>&1
		RET=$?

		if [ $RET -ne 0 ]; then
			echo "Connection to OpenSearch is pending."
			sleep 5
		fi
	done
	echo "OpenSearch server $OPENSEARCH_SERVER is available."
fi

echo "Current directory: $(pwd)"

if [[ -e /tmp/magento.tar.gz ]]; then
	mv /tmp/magento.tar.gz /var/www/html
else
	echo "Magento 2 tar is already moved to /var/www/html"
fi

if [[ -e /var/www/html/pub/index.php ]]; then
	echo "Already extracted Magento"
else
	tar -xf magento.tar.gz --strip-components 1
	rm magento.tar.gz
fi

if [[ -e /usr/local/bin/composer ]]; then
	echo "Composer already exists"
else
	php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
	php composer-setup.php --quiet
	rm composer-setup.php
	mv composer.phar /usr/local/bin/composer
fi

# Install Composer dependencies only when the code is missing. The app volume
# persists across local `act` runs, so this is usually a no-op there; on CI the
# code is always fresh, so it runs.
if [[ ! -d /var/www/html/vendor/magento ]]; then
	composer install -n
fi

# Bring Magento_TwoFactorAuth into this monorepo install. The magento/magento2 source
# archive extracted above omits the security-package that the CE metapackage would
# provide, so a plain monorepo has no Magento_TwoFactorAuth — unrepresentative of a
# real store, and setup:di:compile of any module referencing 2FA types cannot autoload
# the class. Drop the module into app/code (the monorepo maps Magento\ -> app/code/
# Magento, so PSR-4 resolves the new files with no dump-autoload) and pull only its
# third-party libs from packagist (its magento/* deps already ship in the monorepo,
# which does not `replace` this package). All auth-free. Idempotent: skip when the
# app volume already carries it (local `act` reuse).
if [[ ! -d app/code/Magento/TwoFactorAuth && -e /tmp/security-package.tar.gz ]]; then
	echo "Injecting Magento_TwoFactorAuth from the security-package source."
	mkdir -p /tmp/security-package
	tar -xf /tmp/security-package.tar.gz -C /tmp/security-package --strip-components 1
	mkdir -p app/code/Magento/TwoFactorAuth
	cp -R /tmp/security-package/TwoFactorAuth/. app/code/Magento/TwoFactorAuth/

	# Require exactly the constraints the module declares (read from its own
	# composer.json), minus php and the magento/* deps the monorepo already provides,
	# so the lib set never drifts from the fetched SECURITY_PACKAGE_VERSION.
	TFA_LIBS=$(php -r '
		$r = json_decode(file_get_contents("app/code/Magento/TwoFactorAuth/composer.json"), true)["require"] ?? [];
		foreach ($r as $p => $c) {
			if ($p === "php" || strpos($p, "magento/") === 0) { continue; }
			echo escapeshellarg($p . ":" . $c), " ";
		}
	')
	if [[ -n "$TFA_LIBS" ]]; then
		eval "composer require --no-interaction $TFA_LIBS"
	fi
fi

# Normalize permissions before any bin/magento call (idempotent and cheap).
find var generated vendor pub/static pub/media app/etc -type f -exec chmod g+w {} + 2>/dev/null
find var generated vendor pub/static pub/media app/etc -type d -exec chmod g+ws {} + 2>/dev/null
chown -R www-data:www-data .
chmod u+x bin/magento

# Decide whether to (re)install from the DATABASE state, not just the presence of
# code. The app volume persists between runs but the `db` service is ephemeral,
# so the code can be present while the database is empty — Magento then bootstraps
# into "The store that was requested wasn't found". Treat "no store rows" as
# "needs install". On CI everything is fresh, so this always installs, as before.
STORE_COUNT=$(mysql -h "$DB_SERVER" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" \
	-N -e "SELECT COUNT(*) FROM ${DB_PREFIX}store" "$DB_NAME" 2>/dev/null || echo 0)

if [[ "${STORE_COUNT:-0}" -gt 0 ]]; then
	echo "Magento is already installed (found ${STORE_COUNT} store(s) in the database)."
else
	echo "No stores found in the database — running setup:install."
	bin/magento setup:install \
		--base-url="http://$MAGENTO_HOST" \
		--db-host="$DB_SERVER:$DB_PORT" \
		--db-name="$DB_NAME" \
		--db-user="$DB_USER" \
		--db-password="$DB_PASSWORD" \
		--db-prefix="$DB_PREFIX" \
		--admin-firstname="$ADMIN_NAME" \
		--admin-lastname="$ADMIN_LASTNAME" \
		--admin-email="$ADMIN_EMAIL" \
		--admin-user="$ADMIN_USERNAME" \
		--admin-password="$ADMIN_PASSWORD" \
		--backend-frontname="$ADMIN_URLEXT" \
		--language="$MAGENTO_LANGUAGE" \
		--currency="$MAGENTO_CURRENCY" \
		--timezone="$MAGENTO_TZ" \
		--use-rewrites=1 \
		--cleanup-database \
		$MAGENTO_INSTALL_ARGS;

	bin/magento setup:di:compile
	bin/magento setup:static-content:deploy -f
	bin/magento indexer:reindex
	bin/magento deploy:mode:set developer
	bin/magento maintenance:disable

	echo "Installation completed"
fi

ISSET_USE_SSL=$(bin/magento config:show web/secure/use_in_frontend)

if [ "$USE_SSL" -eq 1 ]; then
	if [ "${ISSET_USE_SSL:-0}" -eq 1 ]; then
		echo "Use SSL is set, but SSL is already enabled."
	else
		bin/magento setup:store-config:set \
			--base-url-secure="https://$MAGENTO_HOST" \
			--use-secure=1 \
			--use-secure-admin=1
		echo "SSL for Magento is configured."
	fi
else
	echo "Use SSL is not set, skipping."
fi

grep "ServerName" /etc/apache2/apache2.conf >/dev/null 2>&1
SERVERNAME_EXISTS=$?

if [ $SERVERNAME_EXISTS -eq 0 ]; then
	echo "ServerName is already added in Apache config."
else
	echo "ServerName $MAGENTO_HOST" >>/etc/apache2/apache2.conf
	echo "ServerName is added to Apache config."
fi

echo "Magento configuration"
bin/magento config:show

exec apache2-foreground
