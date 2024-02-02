<?php

namespace Mbissonho\RememberAdminLastPage\Plugin;

use Magento\Backend\Controller\Adminhtml\Auth\Logout;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Mbissonho\RememberAdminLastPage\Helper\Data as DataHelper;

class AdminLogoutAfter
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

    public function afterExecute(Logout $subject, $result)
    {
        if(!$this->dataHelper->isActive()) return $result;

        if($result instanceof Redirect) {
            $urlBuilder = $this->om->get(UrlInterface::class);

            $url = $urlBuilder
                ->turnOffSecretKey()
                ->getUrl('adminhtml/auth/login', ['_query' => ['logout_success' => 1]]);
            $result->setPath($url);
        }

        return $result;
    }
}
