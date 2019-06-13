# *Site Building*: Building a Civi site and downloading extensions from comex

## Problem space

Suppose we want to download the latest beta of [CiviCRM-Mosaico integration](https://civicrm.org/extensions/email-template-builder) (aka `uk.co.vedaconsulting.mosaico`). One might run a command:

```bash
cv dl --dev mosaico
```

This will work for many extensions, but Mosaico has a dependency: it requires [Flexmailer](https://civicrm.org/extensions/flexmailer) (aka `org.civicrm.flexmailer`). If you know this, then you can write a slightly longer command:

```bash
cv dl --dev flexmailer mosaico
```

Of course, as the ecosystem behind the software becomes more complicated, it becomes harder to know which dependencies are needed -- and we start getting more edge-cases (e.g. where specific versions of Mosaico require specific versions of Flexmailer). We *could* add more functionality directly to CiviCRM and/or `cv` to handle these versioned dependencies. I'd say we have the skills for this (and not necessarily the time), but...

There is already a popular tool called `composer` that specifically handles versioned dependencies -- and it comes with a built-in network of third-party libraries. It could be a handy way to save some work... except... it doesn't include the library of CiviCRM extensions.

`comex` is a bridge which allows you to download CiviCRM extensions with `composer`.

`comex`  is currently a proof-of-concept. There are user/admin-experience and marketing considerations that limit `composer` as a total solution for all users of the CiviCRM product; however, for developers and advanced adminsitrators, it may be useful today -- and (hopefully) it's a stepping-stone that will make the other challenges more approachable.

## Basic concept

The idea here is pretty simple: *use `composer` to download extensions from `civicrm.org` via `comex`*. You can use it with just two steps

```bash
## 1. Register the comex bridge
composer config repositories.comex composer <FIXME-URL>

## 2. Download the extension using the "civipkg/" prefix.
composer require civipkg/uk.co.vedaconsulting.mosaico
```

Sounds easy, right?

## The monkey-wrench

If it were that easy, this document wouldn't exist: there'd be a README with 3 sentences and two example commands. Mission accomplished! But it's not.

Here's the hard part: *where do you run those commands*? (The same question can be phrased other ways: "*where do you store the extension?*" "*What is the root-project for the build*?")

There are basically four possible answers, which stake different positions on the spectrum from "*most correct*" to "*easiest to operationalize*". Let's consider the steps and the trade-offs of each.

## Deployment Scenario 1: Site Root

```bash
cd /var/www
composer config repositories.comex composer <FIXME-URL>
# FIXME (for local dev): composer config secure-http false
composer require civipkg/uk.co.vedaconsulting.mosaico
```

## Deployment Scenario 2: Extension Root

```bash
cd /var/www/sites/default/files/civicrm/ext
composer init
composer config repositories.comex composer <FIXME-URL>
# FIXME (for local dev): composer config secure-http false
composer require civipkg/uk.co.vedaconsulting.mosaico
```

## Deployment Scenario 3: CiviCRM Root

```bash
cd /var/www/sites/modules/civicrm
composer config repositories.comex composer <FIXME-URL>
# FIXME (for local dev): composer config secure-http false
composer require civipkg/uk.co.vedaconsulting.mosaico
```

## Deployment Scenario 4: Per-Extension

```bash
cd /var/www/sites/../org.example.mymyext
composer init
composer config repositories.comex composer <FIXME-URL>
# FIXME (for local dev): composer config secure-http false
composer require civipkg/uk.co.vedaconsulting.mosaico
```
