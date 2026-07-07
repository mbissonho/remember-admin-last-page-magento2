<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\Auth;

/**
 * One second-factor step (2FA, WebAuthn, …) as seen by
 * {@see CompletedAdminAuthenticationInterface}. Implementations are owned by the
 * module that provides the factor and are only resolved when that module is
 * enabled, so the base module keeps no hard dependency on any of them.
 */
interface SecondFactorGuardInterface
{
    /**
     * True if this factor does not apply to the current session or was already
     * satisfied; false while it is still pending.
     */
    public function isSatisfied(): bool;
}
