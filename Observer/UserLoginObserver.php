<?php


namespace Mbissonho\RememberAdminLastPage\Observer;

use Magento\Backend\Model\Auth\StorageInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\ObjectManagerInterface;
use Mbissonho\RememberAdminLastPage\Helper\Data as DataHelper;
use Mbissonho\RememberAdminLastPage\Model\Session\AuthStorageKey;

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

        $lastAccessedPageJsonAsString = $this->om->get(RequestInterface::class)
            ->getParam('mbissonho-last-admin-page-accessed');

        if(!empty($lastAccessedPageJsonAsString)) {
            $storage = $this->om->get(StorageInterface::class);
            $storage->setData(AuthStorageKey::LAST_PAGE, $lastAccessedPageJsonAsString);

            // Arm a one-shot resume marker owned by this module. We intentionally do
            // NOT reuse Magento's isFirstPageAfterLogin() flag: that one is
            // self-consuming (Session::isFirstPageAfterLogin() reads is_first_visit
            // with clear=true) and AbstractAction::dispatch() preloads it on the very
            // first backend request after login. With Magento_TwoFactorAuth enabled
            // that first request is the 2FA challenge bounce, so the flag is burned
            // before the user ever reaches the dashboard. Our marker survives the 2FA
            // detour and is cleared only by the ResumeLastPageOnDashboard plugin when
            // it resumes.
            $storage->setData(AuthStorageKey::RESUME_PENDING, true);
        }
    }
}
