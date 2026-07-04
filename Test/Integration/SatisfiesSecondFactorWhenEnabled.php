<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Test\Integration;

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TwoFactorAuth\Api\TfaSessionInterface;

/**
 * Lets a TFA-agnostic integration test dispatch a keyless ActionInterface
 * controller (EntityPreview, IsLoggedIn) unchanged whether Magento_TwoFactorAuth
 * is enabled or not.
 *
 * The integration BypassTwoFactorAuth plugin can only neutralise TFA for
 * controllers that expose getRequest() (it checks the request is a
 * TestFramework\Request). Keyless controllers have no getRequest(), so under an
 * enforced-TFA install their *authenticated* dispatch reaches TFA's
 * ControllerActionPredispatch, which bounces it — and fatals on the getResponse()
 * those controllers do not have. Marking 2FA as already cleared makes that
 * observer inert, so the test asserts the same behaviour in both flows.
 *
 * No-op — and it references no Magento_TwoFactorAuth type — when the module is
 * disabled, so the trait is safe to use on a plain install too.
 */
trait SatisfiesSecondFactorWhenEnabled
{
    protected function satisfySecondFactorWhenEnabled(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        if (!$objectManager->get(ModuleManager::class)->isEnabled('Magento_TwoFactorAuth')) {
            return;
        }

        // grantAccess() is the exact seam a provider's auth controller hits after
        // verifying the user's code: it flips TfaSession::isGranted() to true, the
        // flag ControllerActionPredispatch checks to let a request through.
        $objectManager->get(TfaSessionInterface::class)->grantAccess();
    }
}
