<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity;

use Mbissonho\RememberAdminLastPage\Api\Data\EntityContextInterface;

class EntityContext implements EntityContextInterface
{
    private string $entityTypeCode;

    private string $entityId;

    public function __construct(string $entityTypeCode, string $entityId)
    {
        $this->entityTypeCode = $entityTypeCode;
        $this->entityId = $entityId;
    }

    public function getEntityTypeCode(): string
    {
        return $this->entityTypeCode;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }
}
