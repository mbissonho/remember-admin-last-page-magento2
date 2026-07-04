<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\Auth;

use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\Module\Manager as ModuleManager;
use Mbissonho\RememberAdminLastPage\Model\Auth\SecondFactor\TwoFactorAuthGuardFactory;

/**
 * Default implementation: credentials plus every enabled second factor.
 *
 * Second-factor modules are optional, so their guards are pulled in by
 * conditional, lazy instantiation rather than by a hard di.xml dependency:
 * the guard for a factor is created only when its owning module is enabled, so
 * on an install without (or with a disabled) Magento_TwoFactorAuth the guard
 * class — and the module API it type-hints — is never autoloaded.
 */
class CompletedAdminAuthentication implements CompletedAdminAuthenticationInterface
{
    private const MODULE_TWO_FACTOR_AUTH = 'Magento_TwoFactorAuth';

    private ?SecondFactorGuardInterface $twoFactorAuthGuard = null;

    public function __construct(
        private readonly AuthSession $authSession,
        private readonly ModuleManager $moduleManager,
        private readonly TwoFactorAuthGuardFactory $twoFactorAuthGuardFactory
    ) {
    }

    public function isComplete(): bool
    {
        if (!$this->authSession->isLoggedIn()) {
            return false;
        }

        foreach ($this->applicableGuards() as $guard) {
            if (!$guard->isSatisfied()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return SecondFactorGuardInterface[]
     */
    private function applicableGuards(): array
    {
        $guards = [];

        if ($this->moduleManager->isEnabled(self::MODULE_TWO_FACTOR_AUTH)) {
            // Instantiated here, and only here, so the TFA-typed guard stays
            // unloaded on installs where the module is absent or disabled.
            $guards[] = $this->twoFactorAuthGuard
                ??= $this->twoFactorAuthGuardFactory->create();
        }

        return $guards;
    }
}
