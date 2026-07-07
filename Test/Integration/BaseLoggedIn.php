<?php

namespace Mbissonho\RememberAdminLastPage\Test\Integration;

use Magento\Backend\Model\Auth;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\Model\UrlInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Base class for integration tests that need an authenticated admin session.
 *
 * Exposes reusable _login()/_logout() helpers (turning the admin secret key off
 * while logged in so redirect URLs stay easy to assert), so subclasses can
 * exercise post-login behaviour in different contexts — plain admin auth
 * (BehavioursThroughAdminAuthenticationTest) and an enforced Magento_TwoFactorAuth
 * flow (BehavioursThroughTfaAuthenticationTest).
 */
abstract class BaseLoggedIn extends AbstractController
{
    /**
     * @var Session|null
     */
    protected ?Session $_session;

    /**
     * @var Auth|null
     */
    protected ?Auth $_auth;

    protected function tearDown(): void
    {
        $this->_session = null;
        $this->_auth = null;
        parent::tearDown();
    }

    /**
     * Performs user login
     */
    protected function _login()
    {
        Bootstrap::getObjectManager()->get(
            UrlInterface::class
        )->turnOffSecretKey();

        $this->_auth = Bootstrap::getObjectManager()->get(
            Auth::class
        );
        $this->_auth->login(
            \Magento\TestFramework\Bootstrap::ADMIN_NAME,
            \Magento\TestFramework\Bootstrap::ADMIN_PASSWORD
        );
        $this->_session = $this->_auth->getAuthStorage();
    }

    /**
     * Performs user logout
     */
    protected function _logout()
    {
        $this->_auth->logout();
        Bootstrap::getObjectManager()->get(
            UrlInterface::class
        )->turnOnSecretKey();
    }
}
