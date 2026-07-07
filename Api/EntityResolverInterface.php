<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Api;

/**
 * Display-side extension point: loads the model for one `entity_type_code` and
 * declares the ACL resource a user must hold to be shown anything about it.
 *
 * Exactly one resolver is registered per entity type in the ResolverPool. The
 * preview controller enforces {@see getAclResource()} before ever calling
 * {@see resolve()}, so a resolver only loads a model for a user already
 * authorized to view that kind of entity.
 */
interface EntityResolverInterface
{
    /**
     * Short, translatable label for the entity kind (e.g. "Order").
     */
    public function getLabel(): string;

    /**
     * ACL resource id gating the display (e.g. "Magento_Sales::actions_view").
     */
    public function getAclResource(): string;

    /**
     * Load the model for the given raw id, or null when it cannot be loaded.
     */
    public function resolve(string $entityId): ?object;
}
