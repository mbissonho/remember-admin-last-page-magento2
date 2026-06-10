<?php

declare(strict_types=1);

namespace Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool;

use Magento\Framework\App\Request\Http as HttpRequest;
use Mbissonho\RememberAdminLastPage\Api\Data\EntityContextInterface;
use Mbissonho\RememberAdminLastPage\Api\EntityContextDetectorInterface;

/**
 * Ordered pool of detectors. The first detector to recognise the request wins,
 * so list more specific detectors before broader ones in di.xml.
 */
class DetectorPool
{
    /** @var EntityContextDetectorInterface[] */
    private array $detectors;

    /**
     * @param EntityContextDetectorInterface[] $detectors
     */
    public function __construct(array $detectors = [])
    {
        foreach ($detectors as $key => $detector) {
            if (!$detector instanceof EntityContextDetectorInterface) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Detector "%s" must implement %s.',
                        (string)$key,
                        EntityContextDetectorInterface::class
                    )
                );
            }
        }

        $this->detectors = $detectors;
    }

    public function detect(HttpRequest $request): ?EntityContextInterface
    {
        foreach ($this->detectors as $detector) {
            $context = $detector->detect($request);
            if ($context !== null) {
                return $context;
            }
        }

        return null;
    }
}
