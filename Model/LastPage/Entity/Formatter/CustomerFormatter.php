<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Formatter;

use Magento\Customer\Api\Data\CustomerInterface;
use Mbissonho\RememberAdminLastPage\Api\EntityFormatterInterface;
use Mbissonho\RememberAdminLastPage\Api\MaskingStrategyInterface;

class CustomerFormatter implements EntityFormatterInterface
{
    private MaskingStrategyInterface $textMask;

    private MaskingStrategyInterface $emailMask;

    public function __construct(
        MaskingStrategyInterface $textMask,
        MaskingStrategyInterface $emailMask
    ) {
        $this->textMask = $textMask;
        $this->emailMask = $emailMask;
    }

    public function format(object $entity): array
    {
        if (!$entity instanceof CustomerInterface) {
            return [];
        }

        $fields = [];

        $name = trim((string)$entity->getFirstname() . ' ' . (string)$entity->getLastname());
        if ($name !== '') {
            $fields[] = ['label' => 'Name', 'value' => $this->textMask->mask($name)];
        }

        $email = (string)$entity->getEmail();
        if ($email !== '') {
            $fields[] = ['label' => 'Email', 'value' => $this->emailMask->mask($email)];
        }

        return $fields;
    }
}
