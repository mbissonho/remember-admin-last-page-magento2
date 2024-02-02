<?php

namespace Mbissonho\RememberAdminLastPage\Block\Adminhtml;

use Magento\Backend\Model\Url;
use Magento\Framework\View\Element\Template;

class LastPageRemember extends Template
{

    public function getPathData(): array
    {
        return [
            'route_path' => $this->getRoutePath(),
            'edit_details' => $this->getRouteEditDetailsAsJson()
        ];
    }

    public function getRouteEditDetailsAsJson(): array
    {
        $details = [
            'url_entity_param_name' => 'entity_id',
            'url_entity_param_value' => 0
        ];

        $requestParams = $this->getRequest()->getParams();

        $firstParamKey = array_key_first($requestParams);

        if(empty($firstParamKey) || $firstParamKey === Url::SECRET_KEY_PARAM_NAME) {
            return $details;
        }

        $details['url_entity_param_name'] = $firstParamKey;
        $details['url_entity_param_value'] = $requestParams[$firstParamKey];

        return $details;
    }

    protected function getRoutePath(): string
    {
        $request = $this->getRequest();
        return "{$request->getRouteName()}/{$request->getControllerName()}/{$request->getActionName()}";
    }



}
