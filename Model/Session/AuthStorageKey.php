<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\Session;

/**
 * Centralizes the keys this module writes onto the shared admin auth storage
 * (Magento\Backend\Model\Auth\Session).
 *
 * Every key is namespaced with a module-scoped prefix so it cannot collide with
 * core session data or with other modules (including sibling Mbissonho_* modules)
 * sharing the same admin session. Access these via the session's explicit
 * getData()/setData()/unsetData() so the real key stays visible and defined once.
 */
class AuthStorageKey
{
    /**
     * Module-scoped prefix: vendor + RememberAdminLastPage acronym.
     */
    private const PREFIX = 'mbissonho_ralp_';

    /**
     * JSON describing the last admin page the user accessed before logging in.
     */
    public const LAST_PAGE = self::PREFIX . 'last_page';

    /**
     * One-shot marker armed at login and consumed when the dashboard resumes,
     * gating the post-login redirect (survives the Magento_TwoFactorAuth detour,
     * unlike Magento's self-consuming isFirstPageAfterLogin()).
     */
    public const RESUME_PENDING = self::PREFIX . 'resume_pending';
}
