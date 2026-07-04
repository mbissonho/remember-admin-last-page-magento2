# Entity details on the notification

[← Back to README](../README.md)

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

## The four moving parts

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
> together: the detector emits it, and the `ResolverPool`/`FormatterPool` are keyed
> by it. Use the *same* string everywhere, and **namespace it with the vendor that
> owns the entity** — `vendor_entity`, the first segment of the model's namespace.
> That is why the bundled types are `magento_order`, `magento_customer` and
> `magento_product` (they are Magento core models), while an entity of your own
> would be e.g. `acme_subscription`. Keying by the entity's owner — not by whoever
> wires it — is what lets two modules describing the *same* entity converge on one
> code, while genuinely different entities never clash on a bare word like `order`.

## See also

- [Tutorial: show details for a new entity type](tutorial-new-entity-type.md)
- [Recipes](recipes.md)
