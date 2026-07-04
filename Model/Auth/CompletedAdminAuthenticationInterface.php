<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\Auth;

/**
 * Answers whether the current admin session is *fully* authenticated, i.e. the
 * credentials were accepted and, when a second factor applies (e.g. 2FA), it was
 * satisfied too.
 *
 * The backend {@see \Magento\Backend\Model\Auth\Session::isLoggedIn()} flag turns
 * true right after the password check — before any second factor — so it is not a
 * safe gate on its own for disclosing anything to the freshly-credentialed admin.
 * This contract is that safe gate.
 */
interface CompletedAdminAuthenticationInterface
{
    /**
     * True only when the admin session has cleared every authentication step that
     * applies to it (credentials + any enabled second factor).
     */
    public function isComplete(): bool;
}
