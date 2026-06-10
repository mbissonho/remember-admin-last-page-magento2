<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Resolver;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Mbissonho\RememberAdminLastPage\Api\EntityResolverInterface;

class ProductResolver implements EntityResolverInterface
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getLabel(): string
    {
        return 'Product';
    }

    public function getAclResource(): string
    {
        return 'Magento_Catalog::products';
    }

    public function resolve(string $entityId): ?object
    {
        try {
            return $this->productRepository->getById((int)$entityId);
        } catch (\Exception $e) {
            return null;
        }
    }
}
