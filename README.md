# extpub: Extension Publisher

This provides a system which:

* Scans `git` repositories for tagged releases of CiviCRM extensions
* Reconciles general metadata in `info.xml` and `composer.json` (e.g. generating a `composer.json` based on `info.xml`).
* Adds release metadata in `info.xml` and `composer.json`
* Prepares archives for these releases
* Advertises a composer-compatible feed (`composer config repositories.cxt composer http://extpub.bknix:8001`)

## Background/Goal

* Get past the jam -- no easy way forward, bite the bullet

## Explanation for extension authors

* General info.xml => composer.json
* What if you already have composer.json

## How to simulate the whole workflow locally (without any special access)

## Reference: Git Feed Format

```
[
  {
    "key": "...extension key...",
    "git_url": "...url...",
    "ready": "ready|not_ready|...",
    "path": "...optional relative path, within the repo..."
  }
]
```
