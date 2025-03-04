#!/bin/bash

composer config --json repositories.local '{"type": "path", "url": "/data/extensions/workdir", "options": { "symlink": false } }'
composer require "mbissonho/module-remember-admin-last-page:*"
bin/magento module:disable Magento_TwoFactorAuth
bin/magento module:disable Magento_AdminAnalytics
bin/magento module:enable Mbissonho_RememberAdminLastPage
bin/magento config:set admin/usage/enabled 0
bin/magento config:set admin/mbissonho_remember_admin_last_page/active 1
# Ensure short admin session lifetime
bin/magento config:set admin/security/session_lifetime 60
bin/magento setup:upgrade
bin/magento setup:di:compile
