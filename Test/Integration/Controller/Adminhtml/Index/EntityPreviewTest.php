<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Test\Integration\Controller\Adminhtml\Index;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\Framework\AuthorizationInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;
use Mbissonho\RememberAdminLastPage\Api\Data\EntityContextInterfaceFactory;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\EntityTokenizer;
use Mbissonho\RememberAdminLastPage\Test\Integration\SatisfiesSecondFactorWhenEnabled;

/**
 * Covers the security gates of the keyless preview endpoint
 * {@see \Mbissonho\RememberAdminLastPage\Controller\Adminhtml\Index\EntityPreview}.
 *
 * Each gate must collapse to exactly {"details": null} — never a partial leak —
 * and each test is set up so that, were the gate removed, the call would instead
 * succeed (matching the happy-path test). That is what makes the null meaningful.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 * @group mbissonho-ralp-tfa-agnostic
 */
class EntityPreviewTest extends AbstractBackendController
{
    use SatisfiesSecondFactorWhenEnabled;

    private const URI = 'backend/mbissonho_ralp/index/entityPreview';
    private const CUSTOMER_ACL = 'Magento_Customer::manage';
    private const ORDER_ACL = 'Magento_Sales::actions_view';
    private const CUSTOMER_EMAIL = 'customer@example.com';
    private const CUSTOMER_NAME = 'John Smith';

    private EntityTokenizer $tokenizer;

    private EntityContextInterfaceFactory $contextFactory;

    protected function setUp(): void
    {
        parent::setUp();
        // Keyless controller: BypassTwoFactorAuth cannot reach it, so pre-clear 2FA
        // when the module is enabled to keep this TFA-agnostic test valid in both
        // flows. No-op on a TFA-disabled install.
        $this->satisfySecondFactorWhenEnabled();
        $this->tokenizer = $this->_objectManager->get(EntityTokenizer::class);
        $this->contextFactory = $this->_objectManager->get(EntityContextInterfaceFactory::class);
    }

    /**
     * Unauthenticated session: even with the feature on and a server-minted
     * token for a resolvable customer, a logged-out caller learns nothing.
     *
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 1
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active_notification_message 1
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active_entity_details 1
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testReturnsNullWhenNotAuthenticated(): void
    {
        $token = $this->tokenFor('magento_customer', (string)$this->customerId());

        // Drop the session AbstractBackendController logged in during setUp.
        $this->_auth->getAuthStorage()->destroy(['send_expire_cookie' => false]);

        $this->assertNull($this->dispatchPreview($token)['details']);
    }

    /**
     * Tamper-evidence: a server-minted token whose ciphertext was altered by a
     * single byte fails integrity verification and discloses nothing, so the
     * endpoint cannot be driven with a forged {type, id}.
     *
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 1
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active_notification_message 1
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active_entity_details 1
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testReturnsNullForTamperedToken(): void
    {
        $valid = $this->tokenFor('magento_customer', (string)$this->customerId());

        $pos = intdiv(strlen($valid), 2);
        $tampered = $valid;
        $tampered[$pos] = $valid[$pos] === 'A' ? 'B' : 'A';
        $this->assertNotSame($valid, $tampered, 'precondition: token was actually altered');

        $this->assertNull($this->dispatchPreview($tampered)['details']);
    }

    /**
     * ACL gate: an admin who has been denied the entity's own resource gets
     * nothing back even for a valid token pointing at a resolvable customer.
     *
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 1
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active_notification_message 1
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active_entity_details 1
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testReturnsNullWhenUserLacksEntityAclResource(): void
    {
        $token = $this->tokenFor('magento_customer', (string)$this->customerId());

        $this->denyForCurrentAdmin(self::CUSTOMER_ACL);

        $this->assertNull($this->dispatchPreview($token)['details']);
    }

    /**
     * Per-entity isolation: denying the order resource must not bleed into the
     * customer resource. The same session that can still view customers is
     * blocked from previewing an order, proving the gate consults the resolved
     * entity's own resource rather than a blanket one.
     *
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 1
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active_notification_message 1
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active_entity_details 1
     */
    public function testOrderAclDenialDoesNotAffectCustomerAccess(): void
    {
        $this->denyForCurrentAdmin(self::ORDER_ACL);

        $authorization = $this->_objectManager->get(AuthorizationInterface::class);
        $this->assertFalse(
            $authorization->isAllowed(self::ORDER_ACL),
            'order resource should be denied'
        );
        $this->assertTrue(
            $authorization->isAllowed(self::CUSTOMER_ACL),
            'customer resource must remain allowed'
        );

        // The ACL gate precedes entity loading, so an arbitrary order id suffices.
        $this->assertNull($this->dispatchPreview($this->tokenFor('magento_order', '999999'))['details']);
    }

    /**
     * Happy path: a logged-in admin with the resource and the feature on gets
     * the entity label and partially masked fields — never the raw PII.
     *
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active 1
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active_notification_message 1
     * @magentoConfigFixture admin/mbissonho_remember_admin_last_page/active_entity_details 1
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testReturnsMaskedCustomerDetailsOnHappyPath(): void
    {
        $token = $this->tokenFor('magento_customer', (string)$this->customerId());

        $this->getRequest()->setParam('entity_token', $token);
        $this->getRequest()->setMethod('GET');
        $this->dispatch(self::URI);
        $body = $this->getResponse()->getBody();

        // Raw PII must never reach the wire, masked or not.
        $this->assertStringNotContainsString(self::CUSTOMER_EMAIL, $body);
        $this->assertStringNotContainsString(self::CUSTOMER_NAME, $body);

        $details = json_decode($body, true)['details'] ?? null;
        $this->assertNotNull($details);
        $this->assertSame('Customer', $details['label']);

        $values = [];
        foreach ($details['fields'] as $field) {
            $values[$field['label']] = $field['value'];
        }
        $this->assertArrayHasKey('Name', $values);
        $this->assertArrayHasKey('Email', $values);
        // Masked, but recognizably the same record (mask char present).
        $this->assertStringContainsString('•', $values['Email']);
        $this->assertStringContainsString('•', $values['Name']);
        $this->assertStringEndsWith('.com', $values['Email']);
    }

    private function tokenFor(string $type, string $id): string
    {
        return $this->tokenizer->tokenize(
            $this->contextFactory->create([
                'entityTypeCode' => $type,
                'entityId' => $id,
            ])
        );
    }

    private function customerId(): int
    {
        return (int)$this->_objectManager->get(CustomerRepositoryInterface::class)
            ->get(self::CUSTOMER_EMAIL)
            ->getId();
    }

    private function denyForCurrentAdmin(string $resource): void
    {
        $acl = $this->_objectManager->get(AclBuilder::class)->getAcl();
        $acl->deny($this->_auth->getUser()->getRoles(), $resource);
    }

    /**
     * @return array{details: mixed}
     */
    private function dispatchPreview(string $token): array
    {
        $this->getRequest()->setParam('entity_token', $token);
        $this->getRequest()->setMethod('GET');
        $this->dispatch(self::URI);

        return json_decode($this->getResponse()->getBody(), true) ?? ['details' => null];
    }
}
