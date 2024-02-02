<?php


namespace Mbissonho\RememberAdminLastPage\Observer;

use Magento\Backend\Model\Auth\StorageInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\ObjectManagerInterface;
use Mbissonho\RememberAdminLastPage\Helper\Data as DataHelper;

class UserLoginObserver implements ObserverInterface
{
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

        $last = $this->om->get(RequestInterface::class)
            ->getParam('last-admin-page-accessed');

        if(!empty($last)) {
            $this->om->get(StorageInterface::class)->setLastPage($last);
        }
    }
}
