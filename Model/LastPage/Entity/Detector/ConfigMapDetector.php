<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Detector;

use Magento\Framework\App\Request\Http as HttpRequest;
use Mbissonho\RememberAdminLastPage\Api\Data\EntityContextInterface;
use Mbissonho\RememberAdminLastPage\Api\Data\EntityContextInterfaceFactory;
use Mbissonho\RememberAdminLastPage\Api\EntityContextDetectorInterface;
use Mbissonho\RememberAdminLastPage\Model\LastPage\RoutePath;

/**
 * Declarative, zero-code detector: a route_path -> {entity_type_code, id_param}
 * map (injected via di.xml) covers the common "view/edit one record" admin
 * pages. Routes with non-trivial id extraction get a dedicated detector added to
 * the DetectorPool instead.
 */
class ConfigMapDetector implements EntityContextDetectorInterface
{
    private RoutePath $routePath;

    private EntityContextInterfaceFactory $contextFactory;

    /** @var array<string, array{entity_type_code?: string, id_param?: string}> */
    private array $map;

    /**
     * @param array<string, array{entity_type_code?: string, id_param?: string}> $map
     */
    public function __construct(
        RoutePath $routePath,
        EntityContextInterfaceFactory $contextFactory,
        array $map = []
    ) {
        $this->routePath = $routePath;
        $this->contextFactory = $contextFactory;
        $this->map = $map;
    }

    public function detect(HttpRequest $request): ?EntityContextInterface
    {
        $path = $this->routePath->fromRequest($request);

        if (!isset($this->map[$path])) {
            return null;
        }

        $typeCode = (string)($this->map[$path]['entity_type_code'] ?? '');
        $idParam = (string)($this->map[$path]['id_param'] ?? '');

        if ($typeCode === '' || $idParam === '') {
            return null;
        }

        $id = $request->getParam($idParam);

        if (!is_scalar($id) || (string)$id === '' || (string)$id === '0') {
            return null;
        }

        return $this->contextFactory->create([
            'entityTypeCode' => $typeCode,
            'entityId' => (string)$id,
        ]);
    }
}
