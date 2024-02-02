<?php

namespace Mbissonho\RememberAdminLastPage\Helper;

use Magento\Backend\Model\Auth\StorageInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Backend\Model\Session;

class Data extends AbstractHelper
{
    protected ObjectManagerInterface $om;

    public function __construct(
        Context $context,
        ObjectManagerInterface $om
    )
    {
        $this->om = $om;
        parent::__construct($context);
    }

    public function isActive(): bool
    {
        return $this->scopeConfig->isSetFlag('admin/mbissonho_remember_admin_last_page/active');
    }

    public function shouldRemove(): bool
    {
        $request = $this->om->get(RequestInterface::class);
        return (bool) $request->getParam('logout_success', false);
    }
}
