<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Resolver;

use Magento\Sales\Api\OrderRepositoryInterface;
use Mbissonho\RememberAdminLastPage\Api\EntityResolverInterface;

class OrderResolver implements EntityResolverInterface
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function getLabel(): string
    {
        return (string)__('Order');
    }

    public function getAclResource(): string
    {
        return 'Magento_Sales::actions_view';
    }

    public function resolve(string $entityId): ?object
    {
        try {
            return $this->orderRepository->get((int)$entityId);
        } catch (\Exception $e) {
            return null;
        }
    }
}
