<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool;

use Mbissonho\RememberAdminLastPage\Api\EntityResolverInterface;

/**
 * Resolvers keyed by `entity_type_code`. Populate via di.xml to teach the
 * feature how to load a new kind of model.
 */
class ResolverPool
{
    /** @var array<string, EntityResolverInterface> */
    private array $resolvers;

    /**
     * @param array<string, EntityResolverInterface> $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        foreach ($resolvers as $code => $resolver) {
            if (!$resolver instanceof EntityResolverInterface) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Resolver "%s" must implement %s.',
                        (string)$code,
                        EntityResolverInterface::class
                    )
                );
            }
        }

        $this->resolvers = $resolvers;
    }

    public function get(string $entityTypeCode): ?EntityResolverInterface
    {
        return $this->resolvers[$entityTypeCode] ?? null;
    }
}
