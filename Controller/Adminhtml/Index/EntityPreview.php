<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Controller\Adminhtml\Index;

use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Mbissonho\RememberAdminLastPage\Model\Config;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\EntityTokenizer;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool\FormatterPool;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool\ResolverPool;

/**
 * Returns the masked, display-ready details of the model the polling tab had
 * stored, so the notification can hint *which* record will be resumed.
 *
 * Same public-GET shape and security stance as the IsLoggedIn endpoint (see its
 * docblock): it is polled from the login page, which holds no admin secret key,
 * so it opts out of secret-key/CSRF validation. It is safe because it only
 * *reads*, and every disclosure is gated four ways before any model is touched:
 *   - the feature flags (module + notification + entity details) are on;
 *   - the shared session is actually authenticated;
 *   - the opaque token decrypts to a server-minted {type, id} (tamper-proof);
 *   - the current user holds the entity's own ACL resource.
 * Any gate failing returns `{"details": null}` — never a partial leak.
 */
class EntityPreview implements HttpGetActionInterface, CsrfAwareActionInterface
{
    protected JsonFactory $resultJsonFactory;

    protected AuthSession $authSession;

    protected AuthorizationInterface $authorization;

    protected RequestInterface $request;

    protected Config $config;

    protected EntityTokenizer $tokenizer;

    protected ResolverPool $resolverPool;

    protected FormatterPool $formatterPool;

    public function __construct(
        JsonFactory $resultJsonFactory,
        AuthSession $authSession,
        AuthorizationInterface $authorization,
        RequestInterface $request,
        Config $config,
        EntityTokenizer $tokenizer,
        ResolverPool $resolverPool,
        FormatterPool $formatterPool
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->authSession = $authSession;
        $this->authorization = $authorization;
        $this->request = $request;
        $this->config = $config;
        $this->tokenizer = $tokenizer;
        $this->resolverPool = $resolverPool;
        $this->formatterPool = $formatterPool;
    }

    public function execute(): Json
    {
        $jsonResult = $this->resultJsonFactory->create();
        $empty = $jsonResult->setData(['details' => null]);

        if (!$this->config->isActive()
            || !$this->config->isNotificationManagerActive()
            || !$this->config->isEntityDetailsActive()
        ) {
            return $empty;
        }

        if (!$this->authSession->isLoggedIn()) {
            return $empty;
        }

        $context = $this->tokenizer->detokenize((string)$this->request->getParam('entity_token', ''));
        if ($context === null) {
            return $empty;
        }

        $resolver = $this->resolverPool->get($context->getEntityTypeCode());
        $formatter = $this->formatterPool->get($context->getEntityTypeCode());
        if ($resolver === null || $formatter === null) {
            return $empty;
        }

        // ACL gate: never disclose a kind of entity the user cannot view.
        if (!$this->authorization->isAllowed($resolver->getAclResource())) {
            return $empty;
        }

        $entity = $resolver->resolve($context->getEntityId());
        if ($entity === null) {
            return $empty;
        }

        $fields = $formatter->format($entity);
        if ($fields === []) {
            return $empty;
        }

        return $jsonResult->setData([
            'details' => [
                'label' => $resolver->getLabel(),
                'fields' => $fields,
            ],
        ]);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // Read-only GET endpoint reachable from the (keyless) login page, exactly
        // like IsLoggedIn. Opting out of secret-key/CSRF validation here.
        return true;
    }
}
