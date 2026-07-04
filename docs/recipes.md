# Recipes

[← Back to README](../README.md) · See also: [Entity details on the notification](entity-details.md) · [Tutorial: show details for a new entity type](tutorial-new-entity-type.md)

## Add or change fields for a bundled type

Pool items are keyed, so redefining an item with the existing key **replaces** the
bundled one. To add a field to the customer card, point the `magento_customer`
formatter at your own class:

```xml
<type name="Mbissonho\RememberAdminLastPage\Model\LastPage\Entity\Pool\FormatterPool">
    <arguments>
        <argument name="formatters" xsi:type="array">
            <item name="magento_customer" xsi:type="object">Vendor\Module\Model\RememberAdminLastPage\Formatter\RicherCustomerFormatter</item>
        </argument>
    </arguments>
</type>
```

## Change how values are masked

The default mask (`MaskingStrategyInterface`) is the interleaved "hide every other
character" strategy, set via a `preference` in this module's `di.xml`. Swap it
globally with your own `preference`, or per formatter by injecting a different
strategy into that formatter's constructor argument — exactly how `EmailMask` is
wired for the e-mail field today.
