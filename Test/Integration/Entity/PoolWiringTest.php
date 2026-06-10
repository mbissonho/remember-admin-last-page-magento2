<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Test\Integration\Entity;

use Magento\TestFramework\Helper\Bootstrap;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Formatter\CustomerFormatter;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Formatter\OrderFormatter;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Formatter\ProductFormatter;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool\FormatterPool;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool\ResolverPool;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Resolver\CustomerResolver;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Resolver\OrderResolver;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Resolver\ProductResolver;
use PHPUnit\Framework\TestCase;

/**
 * Guards the di.xml wiring of the bundled resolver/formatter pools.
 */
class PoolWiringTest extends TestCase
{
    public function testBundledResolversAreWired(): void
    {
        $pool = Bootstrap::getObjectManager()->get(ResolverPool::class);

        $this->assertInstanceOf(OrderResolver::class, $pool->get('order'));
        $this->assertInstanceOf(CustomerResolver::class, $pool->get('customer'));
        $this->assertInstanceOf(ProductResolver::class, $pool->get('product'));
        $this->assertNull($pool->get('unknown_type'));
    }

    public function testBundledFormattersAreWired(): void
    {
        $pool = Bootstrap::getObjectManager()->get(FormatterPool::class);

        $this->assertInstanceOf(OrderFormatter::class, $pool->get('order'));
        $this->assertInstanceOf(CustomerFormatter::class, $pool->get('customer'));
        $this->assertInstanceOf(ProductFormatter::class, $pool->get('product'));
        $this->assertNull($pool->get('unknown_type'));
    }
}
