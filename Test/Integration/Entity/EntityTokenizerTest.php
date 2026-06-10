<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Test\Integration\Entity;

use Magento\TestFramework\Helper\Bootstrap;
use Mbissonho\RememberAdminLastPage\Api\Data\EntityContextInterfaceFactory;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\EntityTokenizer;
use PHPUnit\Framework\TestCase;

class EntityTokenizerTest extends TestCase
{
    private EntityTokenizer $tokenizer;

    private EntityContextInterfaceFactory $contextFactory;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->tokenizer = $objectManager->get(EntityTokenizer::class);
        $this->contextFactory = $objectManager->get(EntityContextInterfaceFactory::class);
    }

    public function testTokenIsOpaqueAndRoundTrips(): void
    {
        $context = $this->contextFactory->create([
            'entityTypeCode' => 'order',
            'entityId' => '10523',
        ]);

        $token = $this->tokenizer->tokenize($context);

        // Opaque: neither the id nor the plain "type:id" leak to the client.
        $this->assertStringNotContainsString('10523', $token);
        $this->assertStringNotContainsString('order:10523', $token);

        $restored = $this->tokenizer->detokenize($token);

        $this->assertNotNull($restored);
        $this->assertSame('order', $restored->getEntityTypeCode());
        $this->assertSame('10523', $restored->getEntityId());
    }

    public function testTamperedTokenIsRejected(): void
    {
        $context = $this->contextFactory->create([
            'entityTypeCode' => 'order',
            'entityId' => '10523',
        ]);

        $token = $this->tokenizer->tokenize($context);

        $this->assertNull($this->tokenizer->detokenize($token . 'tampered'));
    }

    public function testEmptyAndGarbageTokensAreRejected(): void
    {
        $this->assertNull($this->tokenizer->detokenize(''));
        $this->assertNull($this->tokenizer->detokenize('not-a-real-token'));
    }
}
