<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage;

use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Shared helpers for the "remember last page" route path — the
 * `route/controller/action` string the feature stores, validates and turns into
 * an admin URL.
 *
 * Centralizes the validation pattern and the request -> path assembly that were
 * previously duplicated across the IsLoggedIn controller, the
 * ResumeOnSecretKeyBounce plugin and the LastPageRemember block. URL building is
 * intentionally left to each caller, since they legitimately use different URL
 * builders.
 */
class RoutePath
{
    /**
     * A well-formed admin route path: exactly route/controller/action, each a
     * single segment of word characters.
     */
    private const PATTERN = '#^[a-z0-9_]+/[a-z0-9_]+/[a-z0-9_]+$#i';

    public function isValid(string $routePath): bool
    {
        return (bool)preg_match(self::PATTERN, $routePath);
    }

    /**
     * Build the `route/controller/action` path for the current request.
     */
    public function fromRequest(HttpRequest $request): string
    {
        return $request->getRouteName()
            . '/' . $request->getControllerName()
            . '/' . $request->getActionName();
    }
}
