<?php

namespace Mbissonho\RememberAdminLastPage\Test\Integration;

use Magento\Framework\App\ResponseInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TwoFactorAuth\Api\TfaInterface;
use Magento\TwoFactorAuth\Api\TfaSessionInterface;
use Magento\TwoFactorAuth\Model\Provider\Engine\Google;
use Mbissonho\RememberAdminLastPage\Model\Session\AuthStorageKey;

/**
 * Exercises the dashboard resume in a context where Magento_TwoFactorAuth is
 * enforced.
 *
 * The integration framework ships Magento\TwoFactorAuth\TestFramework\Plugin\
 * BypassTwoFactorAuth, which short-circuits TFA's ControllerActionPredispatch for
 * every request that does NOT carry the `tfa_enabled` param — that is why the
 * plain-login test (BehavioursThroughCredentialsOnlyAdminAuthenticationTest) never
 * hits 2FA. Here we set `tfa_enabled` on the request so TFA actually runs, then
 * satisfy it, proving the resume still fires once the user has cleared 2FA.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 * @group mbissonho-ralp-tfa
 */
class BehavioursThroughTfaAuthenticationTest extends BaseLoggedIn
{
    /**
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 1
     * @magentoConfigFixture default/twofactorauth/general/force_providers google
     * @magentoAppIsolation enabled
     */
    public function testRedirectToLastAccessedPageAfterTfa()
    {
        $this->_login();
        $userId = (int)$this->_session->getUser()->getId();

        /** @var TfaInterface $tfa */
        $tfa = Bootstrap::getObjectManager()->get(TfaInterface::class);
        /** @var TfaSessionInterface $tfaSession */
        $tfaSession = Bootstrap::getObjectManager()->get(TfaSessionInterface::class);

        // The user has the (forced) Google provider configured...
        $tfa->getProvider(Google::CODE)->activate($userId);
        // ...and has just satisfied it. grantAccess() is the exact seam a real
        // provider's auth controller hits after verifying the user's code: it
        // flips TfaSession::isGranted() to true, which is what TFA's
        // ControllerActionPredispatch checks to let the request through instead of
        // bouncing it to tfa/tfa/index. No engine/TOTP mock is needed because this
        // is the post-verification state, not the verification itself.
        $tfaSession->grantAccess();

        // Post-login state our module relies on (UserLoginObserver sets these in
        // the real flow). The one-shot marker is what survives the 2FA detour and
        // makes the resume fire on the real, post-2FA dashboard load.
        $this->_session->setData(
            AuthStorageKey::LAST_PAGE,
            "{\"route_path\":\"customer/index/edit\",\"edit_details\":{\"url_entity_param_name\":\"id\",\"url_entity_param_value\":\"1\"}}"
        );
        $this->_session->setData(AuthStorageKey::RESUME_PENDING, true);

        // Defeat the integration BypassTwoFactorAuth plugin so TFA enforcement is
        // actually evaluated for this dispatch.
        $this->getRequest()->setParam('tfa_enabled', true);

        $this->dispatch('backend/admin/dashboard/index');

        $response = Bootstrap::getObjectManager()
            ->get(ResponseInterface::class);

        $code = $response->getHttpResponseCode();
        $location = $response->getHeaders()->get('Location');
        $uri = $location->getUri();

        $this->assertTrue($code >= 300 && $code < 400, "Incorrect response code");
        $this->assertStringContainsString(
            "customer/index/edit",
            $uri,
            "Expected resume redirect was not performed after 2FA was satisfied"
        );

        $this->_logout();
    }

    /**
     * While 2FA is still pending (the user has the provider configured but has not
     * entered the code yet), the dashboard request must be bounced to the 2FA
     * challenge and the resume must NOT happen — crucially, the one-shot marker has
     * to stay armed so the resume can still fire on the real, post-2FA dashboard
     * load. This guards the structural behaviour of the ResumeLastPageOnDashboard
     * plugin: it runs in afterExecute, so a request TFA bounced in predispatch
     * (FLAG_NO_DISPATCH, execute() never runs) never reaches it and never consumes
     * the marker.
     *
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 1
     * @magentoConfigFixture default/twofactorauth/general/force_providers google
     * @magentoAppIsolation enabled
     */
    public function testDoesNotResumeWhileTfaPending()
    {
        $this->_login();
        $userId = (int)$this->_session->getUser()->getId();

        /** @var TfaInterface $tfa */
        $tfa = Bootstrap::getObjectManager()->get(TfaInterface::class);

        // Provider configured (so the bounce is the 2FA code form, not the config
        // request page) but deliberately NOT satisfied: no grantAccess().
        $tfa->getProvider(Google::CODE)->activate($userId);

        $this->_session->setData(
            AuthStorageKey::LAST_PAGE,
            "{\"route_path\":\"customer/index/edit\",\"edit_details\":{\"url_entity_param_name\":\"id\",\"url_entity_param_value\":\"1\"}}"
        );
        $this->_session->setData(AuthStorageKey::RESUME_PENDING, true);

        $this->getRequest()->setParam('tfa_enabled', true);

        $this->dispatch('backend/admin/dashboard/index');

        $response = Bootstrap::getObjectManager()
            ->get(ResponseInterface::class);

        $code = $response->getHttpResponseCode();
        $location = $response->getHeaders()->get('Location');
        $uri = $location->getUri();

        $this->assertTrue($code >= 300 && $code < 400, "Incorrect response code");
        // TFA owns the bounce: it goes to the 2FA challenge, not to the stored page.
        $this->assertStringContainsString(
            "tfa/tfa/index",
            $uri,
            "Expected the request to be bounced to the 2FA challenge"
        );
        $this->assertStringNotContainsString(
            "customer/index/edit",
            $uri,
            "Resume must not run while 2FA is still pending"
        );
        // The one-shot marker must survive untouched for the post-2FA dashboard load.
        $this->assertTrue(
            (bool)$this->_session->getData(AuthStorageKey::RESUME_PENDING),
            "Resume marker must remain armed while 2FA is pending"
        );

        $this->_logout();
    }
}
