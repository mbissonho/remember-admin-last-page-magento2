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
- PHP 7.4 – 8.5. PHP **8.2+ is recommended**: earlier versions are past their
  active-support window under Adobe Commerce's
  [lifecycle policy](https://experienceleague.adobe.com/en/docs/commerce-operations/release/planning/lifecycle-policy).

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
  saved page was about.

## How it works

When an admin's session expires, the module records the last page they were on and,
right after they sign back in, takes them straight back to it — no need to navigate
back to the order, customer or configuration screen they were editing.

![Automatic redirect back to the last page after signing in](.assets/how-it-works.gif)

Once a tab's session has expired, the login page greets the admin with a short,
**configurable notification** — *"You can come back to your last accessed page if
sign in"* by default — so it is immediately clear that this tab can simply restore
where they left off, rather than looking like a plain, dead login screen.

![Initial notification on the login page telling the admin the session can restore their last page](.assets/resume-last-page-notification-card.png)

### Entity preview on the login screen

The login-page notification can go one step further and preview **which record** the
remembered page was about. This is the *entity preview* mechanism: from the saved URL
the module detects the entity type (order, customer, product, …), resolves the
matching record and renders a compact card with its key fields — always **masked** —
next to a *Go to the page* shortcut, so the admin can tell whether returning to that
tab is still worth it instead of jumping to a page they no longer need.

For security, the preview details are only resolved and shown when the user is
**already signed in elsewhere** — typically because they re-authenticated in another
tab and this expired tab is still on the login screen. If there is no active authenticated admin session, the card stays hide: the module never discloses record details to an unauthenticated user.

![Entity preview card shown on the login page, with masked customer details](.assets/entity-preview.png)

## Entity details & extending

The entity preview shown above is extensible: you can teach it about your own entity
types, change which fields it displays and how each value is masked. These topics
live in dedicated docs:

- **[Entity details on the notification](docs/entity-details.md)** — how the masked
  preview works and the architecture behind it (detectors, resolvers, formatters,
  masking).
- **[Tutorial: show details for a new entity type](docs/tutorial-new-entity-type.md)**
  — step-by-step guide to support a new admin edit page (CMS Pages worked example).
- **[Recipes](docs/recipes.md)** — add/change fields for a bundled type and change
  how values are masked.

## Testing locally before pushing

The repository ships CI workflows (integration + Playwright e2e) that also run
locally via [`act`](https://github.com/nektos/act). See
**[Testing locally before pushing](docs/testing-locally.md)** for the full guide,
including the `act` integration-tests workaround.

## Maintainers

- [Mateus Bissonho](https://github.com/mbissonho)

## License

[Apache 2.0](https://github.com/mbissonho/remember-admin-last-page-magento2/blob/main/LICENSE.md)
