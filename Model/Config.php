<?php

namespace Mbissonho\RememberAdminLastPage\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    public const XML_PATH_NOTIFICATION_MESSAGE_ACTIVE = 'admin/mbissonho_remember_admin_last_page/active_notification_message';
    public const XML_PATH_HAS_SAVED_PAGE_NOTIFICATION_MESSAGE = 'admin/mbissonho_remember_admin_last_page/has_saved_page_notification_message';
    public const XML_PATH_GO_TO_THE_SAVED_PAGE_NOTIFICATION_MESSAGE = 'admin/mbissonho_remember_admin_last_page/go_to_the_saved_page_notification_message';
    public const XML_PATH_LOGIN_PAGE_TITLE_BLINK_MESSAGE = 'admin/mbissonho_remember_admin_last_page/login_page_title_blink_message';

    protected ScopeConfigInterface $config;

    public function __construct(
        ScopeConfigInterface $config
    )
    {
        $this->config = $config;
    }

    public function isNotificationManagerActive(): bool
    {
        return $this->config->isSetFlag(self::XML_PATH_NOTIFICATION_MESSAGE_ACTIVE);
    }

    public function hasSavedPageNotificationMessage()
    {
        return $this->config->getValue(self::XML_PATH_HAS_SAVED_PAGE_NOTIFICATION_MESSAGE);
    }

    public function goToTheSavedPageNotificationMessage()
    {
        return $this->config->getValue(self::XML_PATH_GO_TO_THE_SAVED_PAGE_NOTIFICATION_MESSAGE);
    }

    public function loginPageTitleBlinkMessage()
    {
        return $this->config->getValue(self::XML_PATH_LOGIN_PAGE_TITLE_BLINK_MESSAGE);
    }


}
