<?php

namespace Mbissonho\RememberAdminLastPage\Controller\Adminhtml\Index;

use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Mbissonho\RememberAdminLastPage\Model\Config;
use Mbissonho\RememberAdminLastPage\Model\LastPage\RoutePath;

/**
 * Reports whether an admin session is currently authenticated and, when it is,
 * returns a ready-to-use link to the page the polling tab had stored.
 *
 * This is an intentionally public, admin-routed GET endpoint. It is polled from
 * the login page (an unauthenticated context that has no admin secret key) to
 * detect a successful sign-in in another tab. It must therefore keep answering
 * the very moment the shared session becomes valid.
 *
 * The destination URL is built server-side from the route stored in the polling
 * tab's session storage (passed in as request params), because only the server
 * can mint the per-route admin secret key the URL needs. The route is validated
 * to a strict `route/controller/action` shape and falls back to the dashboard.
 *
 * Because this controller does not extend Magento\Backend\App\AbstractAction,
 * Magento\Backend\App\Request\BackendValidator falls back to secret-key
 * validation and, for a logged-in GET without a "key" param, returns 401 (AJAX)
 * or 302 to the startup page. Implementing CsrfAwareActionInterface and
 * returning `true` from validateForCsrf() is the documented way to opt this
 * specific endpoint out of that secret-key check. Do NOT remove it: doing so
 * breaks the cross-tab polling (the request 401s as soon as the user logs in).
 *
 * This is safe here: the action only reads session state and builds an internal
 * admin URL, it never mutates anything, it is a GET (the Same-Origin Policy
 * stops cross-origin scripts from reading the JSON body), and to unauthenticated
 * callers it discloses only a boolean.
 */
class IsLoggedIn implements HttpGetActionInterface, CsrfAwareActionInterface
{
    private const PARAM_NAME_PATTERN = '#^[a-z0-9_]+$#i';

    protected JsonFactory $resultJsonFactory;
    protected AuthSession $authSession;
    protected UrlInterface $url;
    protected RequestInterface $request;
    protected ResponseInterface $response;
    protected Config $config;
    protected RoutePath $routePath;

    public function __construct(
        JsonFactory $resultJsonFactory,
        AuthSession $authSession,
        UrlInterface $url,
        RequestInterface $request,
        ResponseInterface $response,
        Config $config,
        RoutePath $routePath
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->authSession = $authSession;
        $this->url = $url;
        $this->request = $request;
        $this->response = $response;
        $this->config = $config;
        $this->routePath = $routePath;
    }

    public function execute(): Json
    {
        $jsonResult = $this->resultJsonFactory->create();

        // The login-page poller must stay inert unless both the module and its
        // notification feature are enabled (same flags the notification block
        // and observers gate on). Answer as "not logged in" so the JS never
        // surfaces a resume link, even if the endpoint is reached directly.
        if (!$this->config->isActive() || !$this->config->isNotificationManagerActive()) {
            return $jsonResult->setData(['logged_in' => false]);
        }

        if (!$this->authSession->isLoggedIn()) {
            return $jsonResult->setData(['logged_in' => false]);
        }

        return $jsonResult->setData([
            'logged_in' => true,
            'redirect_url' => $this->buildLastPageUrl(),
        ]);
    }

    /**
     * Build a keyed admin URL for the route stored in the polling tab.
     *
     * Falls back to the dashboard (whose predispatch observer redirects to the
     * last accessed page) when no valid route was provided.
     */
    private function buildLastPageUrl(): string
    {
        $path = (string)$this->request->getParam('route_path', '');

        if (!$this->routePath->isValid($path)) {
            return $this->url->getUrl('adminhtml/dashboard');
        }

        $params = [];
        $entityParamName = (string)$this->request->getParam('entity_param_name', '');
        $entityParamValue = $this->request->getParam('entity_param_value');

        if ($entityParamName !== ''
            && preg_match(self::PARAM_NAME_PATTERN, $entityParamName)
            && is_scalar($entityParamValue)
            && (string)$entityParamValue !== ''
            && (string)$entityParamValue !== '0'
        ) {
            $params[$entityParamName] = $entityParamValue;
        }

        return $this->url->getUrl($path, $params);
    }

    /**
     * Expose the shared application response.
     *
     * Magento_TwoFactorAuth's controller_action_predispatch observer redirects a
     * request by calling getResponse()->setRedirect() on the matched controller.
     * Being a keyless ActionInterface (not an AbstractAction), this controller has
     * no such accessor, so under enforced 2FA that predispatch would fatal before
     * execute() ever runs. Returning the same ResponseInterface the FrontController
     * holds lets the observer issue a clean 302 to the 2FA challenge: it also sets
     * FLAG_NO_DISPATCH, so FrontController returns this response and skips execute()
     * (see FrontController::getActionResponse). No dependency on the 2FA module is
     * introduced — this is a plain framework accessor, inert unless some predispatch
     * observer chooses to redirect.
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // Read-only GET endpoint that must be reachable without a secret key
        // (see class docblock). Opting out of secret-key/CSRF validation here.
        return true;
    }
}
