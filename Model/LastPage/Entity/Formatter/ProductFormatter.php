<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Formatter;

use Magento\Catalog\Api\Data\ProductInterface;
use Mbissonho\RememberAdminLastPage\Api\EntityFormatterInterface;
use Mbissonho\RememberAdminLastPage\Api\MaskingStrategyInterface;

class ProductFormatter implements EntityFormatterInterface
{
    private MaskingStrategyInterface $textMask;

    public function __construct(MaskingStrategyInterface $textMask)
    {
        $this->textMask = $textMask;
    }

    public function format(object $entity): array
    {
        if (!$entity instanceof ProductInterface) {
            return [];
        }

        $fields = [];

        $name = (string)$entity->getName();
        if ($name !== '') {
            $fields[] = ['label' => (string)__('Name'), 'value' => $this->textMask->mask($name)];
        }

        $sku = (string)$entity->getSku();
        if ($sku !== '') {
            $fields[] = ['label' => (string)__('SKU'), 'value' => $this->textMask->mask($sku)];
        }

        return $fields;
    }
}
