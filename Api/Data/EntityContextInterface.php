<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Api\Data;

/**
 * A type-aware reference to the model shown on the page being remembered.
 *
 * It pairs a stable, human-meaningful `entity_type_code` (e.g. "order",
 * "customer", "product") with the raw entity id. It is intentionally tiny: it
 * carries only what the resolver/formatter pools need to load and render the
 * model, and it is never exposed to the browser in this raw form — it travels
 * to the client only after being sealed by the EntityTokenizer.
 */
interface EntityContextInterface
{
    /**
     * Stable code identifying the kind of model (resolver/formatter pool key).
     */
    public function getEntityTypeCode(): string;

    /**
     * The raw entity id (as a string; numeric for the bundled resolvers).
     */
    public function getEntityId(): string;
}
