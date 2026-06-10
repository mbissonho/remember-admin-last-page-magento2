<?php

namespace Mbissonho\RememberAdminLastPage\Block\Adminhtml;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\View\Element\Template;
use Mbissonho\RememberAdminLastPage\Model\Config;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\EntityTokenizer;
use Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool\DetectorPool;
use Mbissonho\RememberAdminLastPage\Model\LastPage\RoutePath;

class LastPageRemember extends Template
{
    protected RoutePath $routePath;

    protected Config $config;

    protected DetectorPool $detectorPool;

    protected EntityTokenizer $tokenizer;

    public function __construct(
        Template\Context $context,
        RoutePath $routePath,
        Config $config,
        DetectorPool $detectorPool,
        EntityTokenizer $tokenizer,
        array $data = []
    ) {
        $this->routePath = $routePath;
        $this->config = $config;
        $this->detectorPool = $detectorPool;
        $this->tokenizer = $tokenizer;
        parent::__construct($context, $data);
    }

    public function getPathData(): array
    {
        $data = [
            'route_path' => $this->getRoutePath(),
            'edit_details' => $this->getRouteEditDetailsAsJson()
        ];

        // Optional, opt-in: a sealed, type-aware reference to the model this page
        // is about, so the resume notification can hint which record it was (see
        // the EntityPreview controller). Stored only when the feature is enabled
        // and a detector recognises the page; ignored by older clients otherwise.
        $entityToken = $this->getEntityToken();
        if ($entityToken !== null) {
            $data['entity_token'] = $entityToken;
        }

        return $data;
    }

    public function getRouteEditDetailsAsJson(): array
    {
        $details = [
            'url_entity_param_name' => 'entity_id',
            'url_entity_param_value' => 0
        ];

        $requestParams = $this->getRequest()->getParams();

        $firstParamKey = array_key_first($requestParams);

        if(empty($firstParamKey) || $firstParamKey === UrlInterface::SECRET_KEY_PARAM_NAME) {
            return $details;
        }

        $details['url_entity_param_name'] = $firstParamKey;
        $details['url_entity_param_value'] = $requestParams[$firstParamKey];

        return $details;
    }

    protected function getRoutePath(): string
    {
        return $this->routePath->fromRequest($this->getRequest());
    }

    private function getEntityToken(): ?string
    {
        if (!$this->config->isEntityDetailsActive()) {
            return null;
        }

        $request = $this->getRequest();
        if (!$request instanceof HttpRequest) {
            return null;
        }

        $context = $this->detectorPool->detect($request);
        if ($context === null) {
            return null;
        }

        return $this->tokenizer->tokenize($context);
    }
}
