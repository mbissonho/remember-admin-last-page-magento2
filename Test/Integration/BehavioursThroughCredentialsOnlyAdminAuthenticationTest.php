<?php

namespace Mbissonho\RememberAdminLastPage\Test\Integration;

use Magento\Backend\Model\Auth\StorageInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Mbissonho\RememberAdminLastPage\Model\Session\AuthStorageKey;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 * @group mbissonho-ralp-no-tfa
 */
class BehavioursThroughCredentialsOnlyAdminAuthenticationTest extends BaseLoggedIn
{
    /**
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 1
     * @magentoAppIsolation enabled
     */
    public function testRedirectToLastAccessedPageAfterLogin()
    {
        $this->_login();

        $storage = Bootstrap::getObjectManager()->get(StorageInterface::class);
        $storage->setData(AuthStorageKey::LAST_PAGE, "{\"route_path\":\"customer/index/edit\",\"edit_details\":{\"url_entity_param_name\":\"id\",\"url_entity_param_value\":\"1\"}}");
        // Mirror the post-login state: UserLoginObserver arms this one-shot marker
        // alongside the stored page; the ResumeLastPageOnDashboard plugin gates on
        // it (no longer on Magento's self-consuming isFirstPageAfterLogin()).
        $storage->setData(AuthStorageKey::RESUME_PENDING, true);

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
