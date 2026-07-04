# Tutorial: show details for a new entity type

[← Back to README](../README.md) · See also: [Entity details on the notification](entity-details.md)

Let's teach the notification about **CMS Pages** (`cms/page/edit`). The same recipe
applies to any "view/edit one record" admin page. All code below lives in your own
module — you never edit this one.

## 1. Capture the page declaratively

Most admin edit pages expose the id as a request param, so no code is needed — just
add an entry to the bundled `ConfigMapDetector` map in your module's
`etc/adminhtml/di.xml` (the capture runs in the admin area):

```xml
<type name="Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Detector\ConfigMapDetector">
    <arguments>
        <argument name="map" xsi:type="array">
            <item name="cms/page/edit" xsi:type="array">
                <item name="entity_type_code" xsi:type="string">magento_cms_page</item>
                <item name="id_param" xsi:type="string">page_id</item>
            </item>
        </argument>
    </arguments>
</type>
```

`route_path` is `frontName/controller/action`; `id_param` is the request parameter
that carries the id on that route (CMS page edit uses `page_id`).

Note the code is `magento_cms_page`, not `cms_page` or `vendor_cms_page`: a CMS Page
belongs to `Magento\Cms`, so it is prefixed with the **entity's** owning vendor
(`magento`), even though *your* module wiring it is `Vendor\Module`. Prefix with
your own vendor only for entities you own (e.g. `acme_subscription`).

> Need a page whose id is not a plain request param (composite keys, slugs, …)?
> Implement `EntityContextDetectorInterface` and add it to the `DetectorPool`
> instead. The first detector to return a non-null context wins.

## 2. Resolve the model (load + ACL)

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\RememberAdminLastPage\Resolver;

use Magento\Cms\Api\PageRepositoryInterface;
use Mbissonho\RememberAdminLastPage\Api\EntityResolverInterface;

class CmsPageResolver implements EntityResolverInterface
{
    private PageRepositoryInterface $pageRepository;

    public function __construct(PageRepositoryInterface $pageRepository)
    {
        $this->pageRepository = $pageRepository;
    }

    public function getLabel(): string
    {
        return 'CMS Page';
    }

    public function getAclResource(): string
    {
        // The preview endpoint checks this *before* resolve() is ever called.
        return 'Magento_Cms::page';
    }

    public function resolve(string $entityId): ?object
    {
        try {
            return $this->pageRepository->getById((int)$entityId);
        } catch (\Exception $e) {
            // Never throw to the caller: an unloadable record means "no details".
            return null;
        }
    }
}
```

## 3. Format the model into display rows

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Model\RememberAdminLastPage\Formatter;

use Magento\Cms\Api\Data\PageInterface;
use Mbissonho\RememberAdminLastPage\Api\EntityFormatterInterface;

class CmsPageFormatter implements EntityFormatterInterface
{
    public function format(object $entity): array
    {
        // Defensive: the pool guarantees the type, but guard anyway.
        if (!$entity instanceof PageInterface) {
            return [];
        }

        $fields = [];

        $title = (string)$entity->getTitle();
        if ($title !== '') {
            $fields[] = ['label' => 'Title', 'value' => $title];
        }

        $identifier = (string)$entity->getIdentifier();
        if ($identifier !== '') {
            $fields[] = ['label' => 'URL Key', 'value' => $identifier];
        }

        return $fields;
    }
}
```

A CMS page title is not sensitive, so it is shown as-is. **If a field is sensitive,
mask it.** Inject a `MaskingStrategyInterface` and pass the value through it before
emitting — see `CustomerFormatter`/`OrderFormatter` in this module, which mask the
customer name with the default interleaved mask and the e-mail with the
structure-preserving `EmailMask`.

## 4. Wire both into the pools

In the same `etc/adminhtml/di.xml`, register the resolver and formatter under the
**same** `entity_type_code` you used in the map (`magento_cms_page`):

```xml
<type name="Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool\ResolverPool">
    <arguments>
        <argument name="resolvers" xsi:type="array">
            <item name="magento_cms_page" xsi:type="object">Vendor\Module\Model\RememberAdminLastPage\Resolver\CmsPageResolver</item>
        </argument>
    </arguments>
</type>

<type name="Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool\FormatterPool">
    <arguments>
        <argument name="formatters" xsi:type="array">
            <item name="magento_cms_page" xsi:type="object">Vendor\Module\Model\RememberAdminLastPage\Formatter\CmsPageFormatter</item>
        </argument>
    </arguments>
</type>
```

## 5. Apply

```shell
./bin/magento setup:upgrade && ./bin/magento setup:di:compile
```

Now open a CMS page in admin, let the session expire, and the resume notification
will read *"CMS Page — Title: …, URL Key: …"*.
