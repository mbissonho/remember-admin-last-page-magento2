<?php

namespace Mbissonho\RememberAdminLastPage\Test\Integration;

use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * @magentoDbIsolation enabled
 */
class RememberScriptAdminhtmlBlockTest extends AbstractBackendController
{
    const SCRIPT = "sessionStorage.setItem('mbissonho-last-admin-page-accessed', '{\"route_path\":\"customer\/index\/index\",\"edit_details\":{\"url_entity_param_name\":\"entity_id\",\"url_entity_param_value\":0}}')";

    /**
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 1
     * @magentoAppIsolation enabled
     */
    public function testHtmlContainsRememberScriptOnceModuleIsEnabled()
    {
        $this->dispatch('backend/customer/index/index/');

        $body = $this->getResponse()->getBody();
        $this->assertStringContainsString(self::SCRIPT, $body);
    }

    /**
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 0
     * @magentoAppIsolation enabled
     */
    public function testHtmlDoesNotContainsRememberScriptOnceModuleIsEnabled()
    {
        $this->dispatch('backend/customer/index/index/');

        $body = $this->getResponse()->getBody();
        $this->assertStringNotContainsString(self::SCRIPT, $body);
    }
}
