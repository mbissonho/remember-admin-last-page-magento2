<?php

namespace Mbissonho\RememberAdminLastPage\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Mbissonho\RememberAdminLastPage\Model\Config;

class Data extends AbstractHelper
{
    protected ObjectManagerInterface $om;

    protected Config $config;

    public function __construct(
        Context $context,
        ObjectManagerInterface $om,
        Config $config
    )
    {
        $this->om = $om;
        $this->config = $config;
        parent::__construct($context);
    }

    public function isActive(): bool
    {
        return $this->config->isActive();
    }

    public function shouldRemove(): bool
    {
        $request = $this->om->get(RequestInterface::class);
        return (bool) $request->getParam('logout_success', false);
    }
}
