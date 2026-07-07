<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Plugin\Backend;

use Magento\Backend\Controller\Adminhtml\Dashboard\Index as DashboardIndex;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Mbissonho\RememberAdminLastPage\Helper\Data as DataHelper;
use Mbissonho\RememberAdminLastPage\Model\LastPage\RoutePath;
use Mbissonho\RememberAdminLastPage\Model\Session\AuthStorageKey;

/**
 * Resumes the admin's last accessed page when they land on the dashboard right
 * after logging in.
 *
 * Why a plugin and not a controller_action_predispatch observer
 * -------------------------------------------------------------
 * This used to run as a predispatch observer that stopped the dispatch with
 * Magento\Framework\App\ActionFlag::FLAG_NO_DISPATCH and overwrote the response
 * redirect. That class documents itself as temporally coupled global state to be
 * avoided ("Please use plugins to prevent action dispatching instead"), and the
 * approach forced a second flag read to detect when Magento_TwoFactorAuth had
 * already bounced the request to its challenge page.
 *
 * As an afterExecute plugin both problems disappear:
 *   - execute() only runs after Magento\Framework\App\Action\Action::dispatch()
 *     has passed its `!FLAG_NO_DISPATCH` check, so a request that 2FA (or anything
 *     else) bounced in predispatch never reaches this plugin. The "only resume on
 *     a real, fully-authenticated dashboard load" guard is therefore structural,
 *     not inferred from a flag.
 *   - returning a ResultInterface short-circuits rendering, so there is nothing to
 *     stop and no flag to set.
 *
 * The one-shot resume marker (see UserLoginObserver) is what scopes this to the
 * first dashboard load after login and survives the 2FA detour; it is consumed
 * here the moment the resume fires, which also breaks any redirect loop (e.g. a
 * stored page that itself resolves back to the dashboard).
 */
class ResumeLastPageOnDashboard
{
    protected DataHelper $dataHelper;

    protected AuthSession $authStorage;

    protected RedirectFactory $resultRedirectFactory;

    protected RoutePath $routePath;

    public function __construct(
        DataHelper $dataHelper,
        AuthSession $authStorage,
        RedirectFactory $resultRedirectFactory,
        RoutePath $routePath
    ) {
        $this->dataHelper = $dataHelper;
        $this->authStorage = $authStorage;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->routePath = $routePath;
    }

    /**
     * @param DashboardIndex $subject
     * @param mixed $result Result returned by the dashboard controller (a Page).
     * @return mixed The original result, or a Redirect to the last accessed page.
     */
    public function afterExecute(DashboardIndex $subject, $result)
    {
        if (!$this->dataHelper->isActive()) {
            return $result;
        }

        $target = $this->resolveResumeTarget();

        if ($target === null) {
            return $result;
        }

        // One-shot: consume the marker before redirecting so the resume fires
        // exactly once per login and a stored page that resolves back to the
        // dashboard cannot loop.
        $this->authStorage->unsetData(AuthStorageKey::RESUME_PENDING);

        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();

        return $redirect->setPath($target['path'], $target['params']);
    }

    /**
     * Resolve the route/params to resume to, or null when no resume is due.
     *
     * @return array{path: string, params: array}|null
     */
    private function resolveResumeTarget(): ?array
    {
        if (!$this->authStorage->getData(AuthStorageKey::RESUME_PENDING)) {
            return null;
        }

        $lastPageJson = $this->authStorage->getData(AuthStorageKey::LAST_PAGE);

        if (null === $lastPageJson) {
            return null;
        }

        $lastPage = \json_decode((string)$lastPageJson, true);

        if (!is_array($lastPage)
            || empty($lastPage['route_path'])
            || !$this->routePath->isValid((string)$lastPage['route_path'])
        ) {
            return null;
        }

        return [
            'path' => (string)$lastPage['route_path'],
            'params' => $this->resolveEntityParams($lastPage['edit_details'] ?? []),
        ];
    }

    /**
     * Build the optional entity param (customer/product/order id, etc.) when the
     * stored page pointed at a specific record.
     *
     * @param mixed $editDetails
     * @return array
     */
    private function resolveEntityParams($editDetails): array
    {
        if (!is_array($editDetails)
            || !isset($editDetails['url_entity_param_name'], $editDetails['url_entity_param_value'])
            || $editDetails['url_entity_param_value'] === 0
        ) {
            return [];
        }

        return [$editDetails['url_entity_param_name'] => $editDetails['url_entity_param_value']];
    }
}
