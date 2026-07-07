<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Formatter;

use Magento\Sales\Api\Data\OrderInterface;
use Mbissonho\RememberAdminLastPage\Api\EntityFormatterInterface;
use Mbissonho\RememberAdminLastPage\Api\MaskingStrategyInterface;

class OrderFormatter implements EntityFormatterInterface
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
        if (!$entity instanceof OrderInterface) {
            return [];
        }

        $fields = [];

        $increment = (string)$entity->getIncrementId();
        if ($increment !== '') {
            $fields[] = ['label' => (string)__('Order'), 'value' => $this->textMask->mask($increment)];
        }

        $name = trim(
            (string)$entity->getCustomerFirstname() . ' ' . (string)$entity->getCustomerLastname()
        );
        if ($name !== '') {
            $fields[] = ['label' => (string)__('Customer'), 'value' => $this->textMask->mask($name)];
        }

        $email = (string)$entity->getCustomerEmail();
        if ($email !== '') {
            $fields[] = ['label' => (string)__('Email'), 'value' => $this->emailMask->mask($email)];
        }

        return $fields;
    }
}
