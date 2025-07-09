<?php

namespace Mbissonho\RememberAdminLastPage\Controller\Adminhtml\Index;

use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class IsLoggedIn implements HttpGetActionInterface, CsrfAwareActionInterface
{
    protected JsonFactory $resultJsonFactory;
    protected AuthSession $authSession;
    protected UrlInterface $url;

    public function __construct(
        JsonFactory $resultJsonFactory,
        AuthSession $authSession,
        UrlInterface $url
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->authSession = $authSession;
        $this->url = $url;
    }

    public function execute()
    {
        $jsonResult = $this->resultJsonFactory->create();
        $isLoggedIn = $this->authSession->isLoggedIn();

        if(!$isLoggedIn) {
            return $jsonResult->setData([
                'logged_in' => $isLoggedIn,
            ]);
        }

        return $jsonResult->setData([
            'logged_in' => $isLoggedIn,
            'secret_key' => $this->url->getSecretKey('customer', 'index', 'index')
        ]);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
