#!/bin/bash
#
# magento_post_install_script for the "TFA off" integration flow.
#
# The extdn action sources this right after `bin/magento setup:install`, before
# it builds dev/tests/integration/phpunit.xml and runs the suite (see the action
# entrypoint). It runs from $MAGENTO_ROOT with `set -e` inherited from the
# entrypoint, so a failing module:disable aborts the flow instead of silently
# testing the wrong state.
#
# Why disable here at all: the integration sandbox inherits its enabled-modules
# list from the real install's app/etc/config.php (phpunit.xml sets
# TESTS_GLOBAL_CONFIG_DIR=../../../app/etc), so turning native 2FA off in the
# main install is what makes the tests run against a genuinely TFA-off store —
# exercising the module's "Magento_TwoFactorAuth not enabled" branches (the
# CompletedAdminAuthentication guard skip and the SatisfiesSecondFactorWhenEnabled
# no-op) instead of just group-excluding the 2FA test.
#
# Both modules go together: Magento_AdminAdobeImsTwoFactorAuth depends on
# Magento_TwoFactorAuth, so disabling only the latter fails the dependency check
# and leaves the inconsistent "2FA off but Adobe-IMS-2FA on" state that made
# keyless controllers fatal on the missing getResponse(). If a future Magento
# version adds another dependent of Magento_TwoFactorAuth, list it here too.
set -e

php bin/magento module:disable \
    Magento_AdminAdobeImsTwoFactorAuth \
    Magento_TwoFactorAuth
