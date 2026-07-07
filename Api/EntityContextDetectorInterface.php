<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Api;

use Magento\Framework\App\Request\Http as HttpRequest;
use Mbissonho\RememberAdminLastPage\Api\Data\EntityContextInterface;

/**
 * Capture-side extension point: given the request for the page being
 * remembered, decide whether it is "about" a known model and, if so, return its
 * {entity_type_code, id} context.
 *
 * Implementations are collected in a pool (see DetectorPool); the first one to
 * return a non-null context wins. Add an implementation to the pool via di.xml
 * to support a route the bundled ConfigMapDetector cannot express declaratively.
 */
interface EntityContextDetectorInterface
{
    public function detect(HttpRequest $request): ?EntityContextInterface;
}
