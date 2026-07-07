<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\Auth\SecondFactor;

use Magento\Authorization\Model\UserContextInterface;
use Magento\TwoFactorAuth\Api\TfaSessionInterface;
use Mbissonho\RememberAdminLastPage\Model\Auth\SecondFactorGuardInterface;

/**
 * Native Magento_TwoFactorAuth guard.
 *
 * This class type-hints Magento_TwoFactorAuth's API, so it must only ever be
 * autoloaded when that module is enabled — {@see \Mbissonho\RememberAdminLastPage\Model\Auth\CompletedAdminAuthentication}
 * resolves it lazily behind a module-enabled check for exactly that reason.
 *
 * The predicate mirrors {@see \Magento\TwoFactorAuth\Observer\ControllerActionPredispatch}:
 * once a user is on the session, access is only granted after 2FA is passed.
 */
class TwoFactorAuthGuard implements SecondFactorGuardInterface
{
    private TfaSessionInterface $tfaSession;

    private UserContextInterface $userContext;

    public function __construct(
        TfaSessionInterface $tfaSession,
        UserContextInterface $userContext
    ) {
        $this->tfaSession = $tfaSession;
        $this->userContext = $userContext;
    }

    public function isSatisfied(): bool
    {
        // No user on the session yet: the credentials gate decides, not 2FA.
        if (!$this->userContext->getUserId()) {
            return true;
        }

        return $this->tfaSession->isGranted();
    }
}
