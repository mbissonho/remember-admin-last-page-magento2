<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Controller\Adminhtml\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Mbissonho\RememberAdminLastPage\Model\Auth\CompletedAdminAuthenticationInterface;
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
 *   - the shared session is fully authenticated — credentials and, when a second
 *     factor such as 2FA is enabled, that too (never a pre-2FA disclosure);
 *   - the opaque token decrypts to a server-minted {type, id} (tamper-proof);
 *   - the current user holds the entity's own ACL resource.
 * Any gate failing returns `{"details": null}` — never a partial leak.
 */
class EntityPreview implements HttpGetActionInterface, CsrfAwareActionInterface
{
    protected JsonFactory $resultJsonFactory;

    protected CompletedAdminAuthenticationInterface $completedAuthentication;

    protected AuthorizationInterface $authorization;

    protected RequestInterface $request;

    protected ResponseInterface $response;

    protected Config $config;

    protected EntityTokenizer $tokenizer;

    protected ResolverPool $resolverPool;

    protected FormatterPool $formatterPool;

    public function __construct(
        JsonFactory $resultJsonFactory,
        CompletedAdminAuthenticationInterface $completedAuthentication,
        AuthorizationInterface $authorization,
        RequestInterface $request,
        ResponseInterface $response,
        Config $config,
        EntityTokenizer $tokenizer,
        ResolverPool $resolverPool,
        FormatterPool $formatterPool
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->completedAuthentication = $completedAuthentication;
        $this->authorization = $authorization;
        $this->request = $request;
        $this->response = $response;
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

        if (!$this->completedAuthentication->isComplete()) {
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

    /**
     * Expose the shared application response.
     *
     * Magento_TwoFactorAuth's controller_action_predispatch observer redirects a
     * request by calling getResponse()->setRedirect() on the matched controller.
     * Being a keyless ActionInterface (not an AbstractAction), this controller has
     * no such accessor, so under enforced 2FA that predispatch would fatal before
     * execute() ever runs. Returning the same ResponseInterface the FrontController
     * holds lets the observer issue a clean 302 to the 2FA challenge: it also sets
     * FLAG_NO_DISPATCH, so FrontController returns this response and skips execute()
     * (see FrontController::getActionResponse). No dependency on the 2FA module is
     * introduced — this is a plain framework accessor, inert unless some predispatch
     * observer chooses to redirect.
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
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
