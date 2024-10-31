<?php

namespace Mbissonho\RememberAdminLastPage\Test\Integration;

use Magento\Backend\Model\Auth;
use Magento\Backend\Model\Auth\Session;
use Magento\Backend\Model\Auth\StorageInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class BehavioursThroughAdminAuthenticationTest extends AbstractController
{
    /**
     * @var Session
     */
    protected ?Session $_session;

    /**
     * @var Auth
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


    /**
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 1
     * @magentoAppIsolation enabled
     */
    public function testLastAccessedPageInfoFromBrowserSessionStorageAppliedToBackendAuthStorage()
    {
        /** @var FormKey $formKey */
        $formKey = $this->_objectManager->get(FormKey::class);
        $this->getRequest()->setPostValue(
            [
                'login' => [
                    'username' => \Magento\TestFramework\Bootstrap::ADMIN_NAME,
                    'password' => \Magento\TestFramework\Bootstrap::ADMIN_PASSWORD,
                ],
                'form_key' => $formKey->getFormKey(),
                'mbissonho-last-admin-page-accessed' => "{\"foo\": \"bar\"}"
            ]
        );

        $this->dispatch('backend/admin/index/index');

        /* @var \Magento\TestFramework\Response $response */
        $response = Bootstrap::getObjectManager()
            ->get(ResponseInterface::class);
        $code = $response->getHttpResponseCode();

        $this->assertTrue($code >= 300 && $code < 400, 'Incorrect response code');

        $this->assertTrue(
            Bootstrap::getObjectManager()->get(
                Auth::class
            )->isLoggedIn()
        );

        $this->assertNotEmpty(
            Bootstrap::getObjectManager()->get(
                StorageInterface::class
            )->getLastPage()
        );
    }

    /**
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 1
     * @magentoAppIsolation enabled
     */
    public function testRedirectToLastAccessedPageAfterLogin()
    {
        $this->_login();

        Bootstrap::getObjectManager()->get(StorageInterface::class)->setLastPage("{\"route_path\":\"customer/index/edit\",\"edit_details\":{\"url_entity_param_name\":\"id\",\"url_entity_param_value\":\"1\"}}");

        $this->dispatch('backend/admin/dashboard/index');

        $response = Bootstrap::getObjectManager()
            ->get(ResponseInterface::class);

        $code = $response->getHttpResponseCode();
        $headers = $response->getHeaders();
        $location = $headers->get('Location');
        $uri = $location->getUri();

        $this->assertTrue($code >= 300 && $code < 400, "Incorrect response code");
        $this->assertStringContainsString(
            "customer/index/edit",
            $uri,
            "Expected redirect was not performed when accessing the dashboard"
        );

        $this->_logout();
    }
}
