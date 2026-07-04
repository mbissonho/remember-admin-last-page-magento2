#!/bin/bash
#
# magento_pre_install_script for both integration flows.
#
# The extdn action sources this (with `set -e`) after its db-create-and-test.php
# step and BEFORE `bin/magento setup:install`. That create step only runs
# `CREATE DATABASE IF NOT EXISTS`, so when the MySQL server is REUSED across runs
# (the common local case with `act` + the shared docker-compose MySQL) the old
# schema survives and setup:install aborts with:
#   SQLSTATE[42S01] ... Table 'store_website' already exists
#
# Dropping and recreating the two integration databases here guarantees an empty
# schema regardless of what a previous flow left behind, so the two flows can run
# against the same MySQL without a clash and re-runs are self-healing. On CI each
# job gets a fresh MySQL service, so this is a harmless no-op there.
#
# Host/user/password/db-names mirror the extdn action's own db-create-and-test.php
# and install-config-mysql.php (mysql / root / root, magento2 + magento2test); if
# the action ever changes them, update this script to match.
set -e

php -r '
$pdo = new PDO("mysql:host=mysql", "root", "root");
foreach (["magento2", "magento2test"] as $db) {
    $pdo->exec("DROP DATABASE IF EXISTS " . $db);
    $pdo->exec("CREATE DATABASE " . $db);
    echo "Reset integration database: " . $db . PHP_EOL;
}
'
