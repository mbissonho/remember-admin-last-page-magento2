<?php

namespace Mbissonho\RememberAdminLastPage\Observer;

use Magento\Backend\Model\Auth\StorageInterface;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Mbissonho\RememberAdminLastPage\Helper\Data as DataHelper;

/**
 * Observes the `controller_action_predispatch_admin_dashboard_index` event.
 */
class DashboardPageObserver implements ObserverInterface
{
    protected ActionInterface $action;

    protected DataHelper $dataHelper;

    protected ObjectManagerInterface $om;

    public function __construct(
        ObjectManagerInterface $om,
        DataHelper $dataHelper
    ) {
        $this->om = $om;
        $this->dataHelper = $dataHelper;
    }

    public function execute(Observer $observer): void
    {
        if(!$this->dataHelper->isActive()) return;

        $this->action = $observer->getEvent()->getData('controller_action');

        $storage = $this->om->get(StorageInterface::class);

        $last = $storage->getLastPage();

        if(null === $last) return;

        $last = json_decode($last, true);

        if(!$storage->isFirstPageAfterLogin() || empty($last['route_path'])) return;

        if($last['edit_details']['url_entity_param_value'] !== 0) {
            $this->redirect(
                $last['route_path'],
                $last['edit_details']['url_entity_param_name'],
                $last['edit_details']['url_entity_param_value']
            );
            return;
        }

        $this->redirect($last['route_path']);
    }

    private function redirect(string $routePath, $urlEntityParamName = null, $urlEntityParamValue = null): void
    {
        $this->om->get(ActionFlag::class)->set('', ActionInterface::FLAG_NO_DISPATCH, true);
        $this->action->getResponse()
            ->setRedirect($this->om->get(UrlInterface::class)->getUrl(
                $routePath, [$urlEntityParamName => $urlEntityParamValue]));
    }
}
