<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Api;

/**
 * Display-side extension point: turns a resolved model into the label/value
 * rows shown in the notification.
 *
 * Formatters are responsible for privacy: values they emit are meant to be
 * displayed as-is, so any sensitive field (name, e-mail, document, …) must
 * already be masked here (see the MaskingStrategyInterface implementations).
 * One formatter is registered per `entity_type_code` in the FormatterPool.
 */
interface EntityFormatterInterface
{
    /**
     * @param object $entity model produced by the matching EntityResolverInterface
     * @return array<int, array{label: string, value: string}> display-ready, already masked
     */
    public function format(object $entity): array;
}
