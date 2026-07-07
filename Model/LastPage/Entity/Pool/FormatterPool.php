<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool;

use Mbissonho\RememberAdminLastPage\Api\EntityFormatterInterface;

/**
 * Formatters keyed by `entity_type_code`. Populate via di.xml to control which
 * fields of a model are shown and how they are masked.
 */
class FormatterPool
{
    /** @var array<string, EntityFormatterInterface> */
    private array $formatters;

    /**
     * @param array<string, EntityFormatterInterface> $formatters
     */
    public function __construct(array $formatters = [])
    {
        foreach ($formatters as $code => $formatter) {
            if (!$formatter instanceof EntityFormatterInterface) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Formatter "%s" must implement %s.',
                        (string)$code,
                        EntityFormatterInterface::class
                    )
                );
            }
        }

        $this->formatters = $formatters;
    }

    public function get(string $entityTypeCode): ?EntityFormatterInterface
    {
        return $this->formatters[$entityTypeCode] ?? null;
    }
}
