<?php

namespace Mbissonho\RememberAdminLastPage\Block\Adminhtml;

use Magento\Framework\View\Element\Template;
use Mbissonho\RememberAdminLastPage\Model\Config;

class LastPageNotificationManager extends Template
{
    protected Config $config;

    public function __construct(
        Template\Context $context,
        Config $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    public function isNotificationManagerActive(): bool
    {
        return $this->config->isNotificationManagerActive();
    }

    public function getBackendUrl(): ?string
    {
        return $this->_urlBuilder->getUrl('mbissonho_ralp/index/isloggedin');
    }

    public function hasSavedPageNotificationMessage(): ?string
    {
        return $this->config->hasSavedPageNotificationMessage();
    }

    public function goToTheSavedPageNotificationMessage(): ?string
    {
        return $this->config->goToTheSavedPageNotificationMessage();
    }

    public function loginPageTitleBlinkMessage(): ?string
    {
        return $this->config->loginPageTitleBlinkMessage();
    }
}
