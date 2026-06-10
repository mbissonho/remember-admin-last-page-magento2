<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Api;

/**
 * Reusable masking primitive shared by formatters. Keeps "how a value is
 * partially hidden" independent from "which field of which entity is shown", so
 * the same strategy (e.g. interleaved character hiding) can be applied across
 * entity types and swapped via di.xml.
 */
interface MaskingStrategyInterface
{
    public function mask(string $value): string;
}
