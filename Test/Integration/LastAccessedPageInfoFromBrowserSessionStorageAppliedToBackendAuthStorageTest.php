<?php

namespace Mbissonho\RememberAdminLastPage\Test\Integration;

use Magento\Backend\Model\Auth;
use Magento\Backend\Model\Auth\StorageInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;
use Mbissonho\RememberAdminLastPage\Model\Session\AuthStorageKey;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 * @group mbissonho-ralp-tfa-agnostic
 */
class LastAccessedPageInfoFromBrowserSessionStorageAppliedToBackendAuthStorageTest extends AbstractController
{
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
            )->getData(AuthStorageKey::LAST_PAGE)
        );
    }
}
