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
composer require mbissonho/module-remenber-admin-last-page
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

## Maintainers

- [Mateus Bissonho](https://github.com/mbissonho)

## License

[Apache 2.0](https://github.com/mbissonho/remember-admin-last-page-magento2/blob/main/LICENSE.md)
