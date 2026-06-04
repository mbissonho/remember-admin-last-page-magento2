<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Plugin\Backend;

use Magento\Backend\App\AbstractAction;
use Magento\Backend\Model\Auth;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Request\Http as HttpRequest;
use Mbissonho\RememberAdminLastPage\Helper\Data as DataHelper;

/**
 * Resumes an admin tab whose only problem is a stale secret key straight back to
 * the page it requested, instead of letting Magento bounce it to the dashboard.
 *
 * Why this exists
 * ---------------
 * With two admin tabs sharing one browser session, re-authenticating in tab A
 * renews the per-route secret keys. Tab B still holds URLs signed with the
 * previous session's key, so reloading tab B makes
 * Magento\Backend\App\AbstractAction::_processUrlKeys() invalidate the key and
 * 302-redirect it to the startup (dashboard) page, dropping the page tab B asked
 * for. The first-page-after-login resume only helps the tab that actually went
 * through the login screen (tab A), so tab B was stranded on the dashboard.
 *
 * This plugin closes that gap entirely server-side: the failing request still
 * carries its own route, so on a stale-key bounce we re-mint the secret key for
 * that exact route and redirect there. No dashboard render, no client-side
 * JavaScript, and it works on the first reload for every admin controller.
 *
 * The intercepted method is "_processUrlKeys"; because Magento builds the plugin
 * method name as 'around' . ucfirst($method) and ucfirst() leaves a leading
 * underscore untouched, the hook MUST be named around_processUrlKeys (not
 * aroundProcessUrlKeys) for the interceptor to find it.
 *
 * Security scope (read before widening)
 * -------------------------------------
 * The admin secret key is an anti-CSRF control for GET requests. To avoid
 * turning into an open URL re-signer, this plugin only acts when ALL of:
 *   - the module is enabled;
 *   - the user already has a valid admin session (we re-key, never re-auth);
 *   - the request is a non-AJAX GET (a POST failure here is a form-key/CSRF
 *     failure and is left untouched);
 *   - the request actually carried a (now stale) "key" param, i.e. it is a
 *     previously-minted admin URL being reloaded, not the keyless deep link a
 *     forged cross-site request would use;
 *   - the target is a well-formed route/controller/action.
 * A keyless or POST request keeps Magento's original dashboard bounce.
 */
class ResumeOnSecretKeyBounce
{
    private const ROUTE_PATH_PATTERN = '#^[a-z0-9_]+/[a-z0-9_]+/[a-z0-9_]+$#i';

    /**
     * Params that must never be carried over when re-minting the URL: the stale
     * secret key, the AJAX markers and the POST form key.
     */
    private const STRIPPED_PARAMS = [
        BackendUrl::SECRET_KEY_PARAM_NAME,
        'isAjax',
        'ajax',
        'form_key',
    ];

    protected Auth $auth;

    protected BackendUrl $backendUrl;

    protected DataHelper $dataHelper;

    public function __construct(
        Auth $auth,
        BackendUrl $backendUrl,
        DataHelper $dataHelper
    ) {
        $this->auth = $auth;
        $this->backendUrl = $backendUrl;
        $this->dataHelper = $dataHelper;
    }

    /**
     * @param AbstractAction $subject
     * @param callable $proceed
     * @return bool
     */
    public function around_processUrlKeys(AbstractAction $subject, callable $proceed): bool
    {
        $valid = (bool)$proceed();

        if ($valid || !$this->dataHelper->isActive()) {
            return $valid;
        }

        $request = $subject->getRequest();

        if (!$this->shouldResume($request)) {
            return false;
        }

        $url = $this->buildResignedUrl($request);

        if ($url === null) {
            return false;
        }

        // Replace the dashboard bounce that _processUrlKeys() just queued with a
        // redirect to the same page, re-signed for the current session. The
        // FLAG_NO_DISPATCH it already set stays on, so nothing renders here.
        $subject->getResponse()->setRedirect($url);

        return false;
    }

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     * @return bool
     */
    private function shouldResume($request): bool
    {
        if (!$request instanceof HttpRequest) {
            return false;
        }

        // Only secret-key (GET) bounces. A POST that fails here failed form-key
        // (CSRF) validation and must never be silently re-issued.
        if ($request->isPost() || $request->isAjax()) {
            return false;
        }

        if ($request->getQuery('isAjax', false) || $request->getQuery('ajax', false)) {
            return false;
        }

        if (!$this->auth->isLoggedIn()) {
            return false;
        }

        // Must be a previously-keyed URL being reloaded, not a keyless deep link.
        return (string)$request->getParam(BackendUrl::SECRET_KEY_PARAM_NAME, '') !== '';
    }

    private function buildResignedUrl(HttpRequest $request): ?string
    {
        $routePath = $request->getRouteName()
            . '/' . $request->getControllerName()
            . '/' . $request->getActionName();

        if (!preg_match(self::ROUTE_PATH_PATTERN, $routePath)) {
            return null;
        }

        $params = $request->getParams();

        foreach (self::STRIPPED_PARAMS as $stripped) {
            unset($params[$stripped]);
        }

        // getUrl() re-mints the per-route secret key for the current session.
        return $this->backendUrl->getUrl($routePath, $params);
    }
}
