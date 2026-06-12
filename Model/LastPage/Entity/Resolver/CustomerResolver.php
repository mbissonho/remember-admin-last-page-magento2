<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Mbissonho\RememberAdminLastPage\Api\EntityResolverInterface;

class CustomerResolver implements EntityResolverInterface
{
    private CustomerRepositoryInterface $customerRepository;

    public function __construct(CustomerRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function getLabel(): string
    {
        return (string)__('Customer');
    }

    public function getAclResource(): string
    {
        return 'Magento_Customer::manage';
    }

    public function resolve(string $entityId): ?object
    {
        try {
            return $this->customerRepository->getById((int)$entityId);
        } catch (\Exception $e) {
            return null;
        }
    }
}
