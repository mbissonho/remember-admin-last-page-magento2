# Remember Admin Last Page Magento 2 module

[![Latest Stable Version](https://img.shields.io/packagist/v/mbissonho/module-remember-admin-last-page.svg?style=flat-square)](https://packagist.org/packages/mbissonho/module-remember-admin-last-page)
[![Packagist Downloads](https://img.shields.io/packagist/dt/mbissonho/module-remember-admin-last-page?style=flat-square)](https://packagist.org/packages/mbissonho/module-remember-admin-last-page)
[![Packagist Downloads](https://img.shields.io/packagist/dm/mbissonho/module-remember-admin-last-page?style=flat-square)](https://packagist.org/packages/mbissonho/module-remember-admin-last-page)

This module allows an admin user to come back to the same page (order, customer or
config management) when their session expires.

On top of that, the resume notification can optionally **hint which record** the
saved page was about — for example *"Customer — Name: J•h• S•i•h, Email:
c•s•o•e•@e•a•p•e.com"* — so the admin can decide whether returning to the tab is
worth it before clicking, instead of jumping to a page they no longer need.

## Prerequisites

- Magento Open Source version 2.4.x
- PHP 7.4+

## Installation

This module can be installed via composer:

```shell
composer require mbissonho/module-remember-admin-last-page
```

After the installation, run:

```shell
./bin/magento setup:upgrade && ./bin/magento setup:di:compile
```

## Get Started

After installation, activate the module under:
***Stores -> Configuration -> Advanced -> Admin -> Remember Admin Last Page.***

Relevant settings there:

- **Enabled** — turns the whole feature on.
- **Enable notification message** — shows the "resume your last page" notification.
- **Show entity details on notification** — *off by default.* When on (and the
  notification is on), the notification also shows masked details of the record the
  saved page was about. This is the feature the rest of this README explains how to
  extend.

## How it works

![how-it-works](https://github.com/mbissonho/remember-admin-last-page-magento2/assets/25405618/b686b97d-ad0a-4af8-acac-7260b9f5c95b)

## Entity details on the notification

When **Show entity details on notification** is enabled, the module recognizes the
"view/edit one record" admin pages it knows about and, on resume, renders a small
set of masked fields describing that record.

It is designed to be safe by construction:

- **Confidential reference.** What the deslogged tab keeps in `sessionStorage` is
  not the raw `{type, id}` but an opaque, installation-keyed token. A reader of
  that storage learns neither the record nor even its type.
- **Tamper-evident.** The token is authenticated, so the preview endpoint cannot be
  driven with a forged `{type, id}` to probe arbitrary records.
- **ACL-gated.** Details are disclosed only to a user who holds the entity's own
  ACL resource (e.g. `Magento_Sales::actions_view` for orders).
- **Masked.** Formatters emit values already partially hidden, so sensitive data
  (name, e-mail, …) is never shown in full — and never reaches the browser in raw
  form.

Out of the box it covers **orders**, **customers** and **products**.

### The four moving parts

Two phases, each with its own extension point:

| Phase | Interface | Pool / wiring | Responsibility |
|---|---|---|---|
| **Capture** (page being remembered) | `EntityContextDetectorInterface` | `DetectorPool` (or the declarative `ConfigMapDetector` map) | Decide the page is "about" a record and return its `{entity_type_code, id}`. |
| **Display** (load) | `EntityResolverInterface` | `ResolverPool` | Load the model for an `entity_type_code` and declare the ACL resource gating it. |
| **Display** (render) | `EntityFormatterInterface` | `FormatterPool` | Turn the model into masked `label/value` rows. |
| **Display** (mask) | `MaskingStrategyInterface` | injected into formatters | Reusable "how to partially hide a value" primitive. |

The token sealing/opening between capture and display is automatic — you never
touch it.

> **The join key.** A single string, the `entity_type_code`, ties the three pools
> together. The detector emits it; the `ResolverPool` and `FormatterPool` are keyed
> by it. Use the *same* string everywhere (e.g. `cms_page`) and the pieces find
> each other.

## Tutorial: show details for a new entity type

Let's teach the notification about **CMS Pages** (`cms/page/edit`). The same recipe
applies to any "view/edit one record" admin page. All code below lives in your own
module — you never edit this one.

### 1. Capture the page declaratively

Most admin edit pages expose the id as a request param, so no code is needed — just
add an entry to the bundled `ConfigMapDetector` map in your module's
`etc/adminhtml/di.xml` (the capture runs in the admin area):

```xml
<type name="Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Detector\ConfigMapDetector">
    <arguments>
        <argument name="map" xsi:type="array">
            <item name="cms/page/edit" xsi:type="array">
                <item name="entity_type_code" xsi:type="string">cms_page</item>
                <item name="id_param" xsi:type="string">page_id</item>
            </item>
        </argument>
    </arguments>
</type>
```

`route_path` is `frontName/controller/action`; `id_param` is the request parameter
that carries the id on that route (CMS page edit uses `page_id`).

> Need a page whose id is not a plain request param (composite keys, slugs, …)?
> Implement `EntityContextDetectorInterface` and add it to the `DetectorPool`
> instead. The first detector to return a non-null context wins.

### 2. Resolve the model (load + ACL)

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

### 3. Format the model into display rows

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

### 4. Wire both into the pools

In the same `etc/adminhtml/di.xml`, register the resolver and formatter under the
**same** `entity_type_code` you used in the map (`cms_page`):

```xml
<type name="Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool\ResolverPool">
    <arguments>
        <argument name="resolvers" xsi:type="array">
            <item name="cms_page" xsi:type="object">Vendor\Module\Model\RememberAdminLastPage\Resolver\CmsPageResolver</item>
        </argument>
    </arguments>
</type>

<type name="Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool\FormatterPool">
    <arguments>
        <argument name="formatters" xsi:type="array">
            <item name="cms_page" xsi:type="object">Vendor\Module\Model\RememberAdminLastPage\Formatter\CmsPageFormatter</item>
        </argument>
    </arguments>
</type>
```

### 5. Apply

```shell
./bin/magento setup:upgrade && ./bin/magento setup:di:compile
```

Now open a CMS page in admin, let the session expire, and the resume notification
will read *"CMS Page — Title: …, URL Key: …"*.

## Recipes

### Add or change fields for a bundled type

Pool items are keyed, so redefining an item with an existing key **replaces** the
bundled one. To add a field to the customer card, point the `customer` formatter at
your own class:

```xml
<type name="Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool\FormatterPool">
    <arguments>
        <argument name="formatters" xsi:type="array">
            <item name="customer" xsi:type="object">Vendor\Module\Model\RememberAdminLastPage\Formatter\RicherCustomerFormatter</item>
        </argument>
    </arguments>
</type>
```

### Change how values are masked

The default mask (`MaskingStrategyInterface`) is the interleaved "hide every other
character" strategy, set via a `preference` in this module's `di.xml`. Swap it
globally with your own `preference`, or per formatter by injecting a different
strategy into that formatter's constructor argument — exactly how `EmailMask` is
wired for the e-mail field today.

## Testing locally before pushing

The repository ships two CI workflows under `.github/workflows/`:

- `integration-tests.yml` — Magento integration tests via the [extdn action](https://github.com/extdn/github-actions-m2).
- `e2e-tests.yml` — Playwright end-to-end tests.

Both run automatically on GitHub. To validate them on your machine before pushing,
use [`act`](https://github.com/nektos/act). Always target a single workflow with
`-W`; a bare `act` would try to parse the support YAML files (e.g. the
`docker-compose*.yml`) as workflows and fail.

```shell
gh act push -W .github/workflows/e2e-tests.yml
```

### Integration tests with `act`

The integration workflow declares `mysql` and `es` as job `services:`. **`act`
cannot run those services the way GitHub does**: it does not expose a service's
hostname to a Docker container action (the extdn action is one), so the run fails
with `getaddrinfo for host "mysql" ... Temporary failure in name resolution`, and
it still starts the services and grabs ports `3306`/`9200`, causing conflicts.
See nektos/act [#944](https://github.com/nektos/act/issues/944),
[#835](https://github.com/nektos/act/issues/835) and
[#5885](https://github.com/nektos/act/issues/5885).

To work around this, run the integration tests locally with the dedicated,
**local-only** setup in `.github/workflows/integration-tests/`:

- `docker-compose.yml` — brings up the same MySQL 8.0 + Elasticsearch 8.11
  dependencies on a named network (`integration-local`), exposing the `mysql` and
  `es` hostnames the extdn action expects.
- `act-integration.yml` — a copy of the integration job **without** the
  `services:` block, so `act` does not start (and conflict on) those services.
  It lives in a subfolder, so GitHub never runs it — it is strictly local.

```shell
# 1. Start the dependencies and wait until they are healthy
docker compose -f .github/workflows/integration-tests/docker-compose.yml up -d --wait

# 2. Run the local workflow, attaching act's containers to the same network
#    so the extdn action can resolve `mysql` and `es`
gh act push -W .github/workflows/integration-tests/act-integration.yml -j run \
  --network integration-local

# 3. Tear the dependencies down
docker compose -f .github/workflows/integration-tests/docker-compose.yml down -v
```

If a previous run left containers behind and you hit a port conflict, clean them
up first:

```shell
docker ps -a --filter "name=act-" -q | xargs -r docker rm -f
```

> Keep the matrix and step in `act-integration.yml` in sync with
> `integration-tests.yml`; the local file only drops the `services:` block, which
> is unusable under `act`. The CI workflow on GitHub remains the source of truth.

## Maintainers

- [Mateus Bissonho](https://github.com/mbissonho)

## License

[Apache 2.0](https://github.com/mbissonho/remember-admin-last-page-magento2/blob/main/LICENSE.md)
