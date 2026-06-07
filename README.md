# Remember Admin Last Page Magento 2 module

[![Latest Stable Version](https://img.shields.io/packagist/v/mbissonho/module-remember-admin-last-page.svg?style=flat-square)](https://packagist.org/packages/mbissonho/module-remember-admin-last-page)
[![Packagist Downloads](https://img.shields.io/packagist/dt/mbissonho/module-remember-admin-last-page?style=flat-square)](https://packagist.org/packages/mbissonho/module-remember-admin-last-page)
[![Packagist Downloads](https://img.shields.io/packagist/dm/mbissonho/module-remember-admin-last-page?style=flat-square)](https://packagist.org/packages/mbissonho/module-remember-admin-last-page)

This module allow an admin user to come back to the same page(order, customer or config management) when it's session expires.

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

After installation, you just need to activate the module on the following configuration: 
***Stores -> Configuration -> Advanced -> Admin -> Remember Admin Last Page.*** 

## How it works

![how-it-works](https://github.com/mbissonho/remember-admin-last-page-magento2/assets/25405618/b686b97d-ad0a-4af8-acac-7260b9f5c95b)

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
