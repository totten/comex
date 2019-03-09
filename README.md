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

```
## Pull the feed of published extensions. Take the first two and plan how to build them.
./bin/extpub plan -v --git-feed https://civicrm.org/extdir/git-urls.json --limit 2

## Use a couple of local git repos. Plan how to build them.
./bin/extpub plan -v ~/bknix/build/dmaster/sites/all/modules/civicrm/ext/{api4,flexmailer,mosaico}

## (Dry run) Build the extension 'org.civicrm.api4' at version 4.1.0.
./bin/extpub build -v --ext='org.civicrm.api4' --git-url='https://github.com/civicrm/org.civicrm.api4'  --commit='d5a853a6f4d1cad11e8655755b329f15eb3fc27b' --ver='4.1.0' -N

## (Real run) Build the extension 'org.civicrm.api4' at version 4.1.0. Overwrite any existing zip files.
./bin/extpub build -v --ext='org.civicrm.api4' --git-url='https://github.com/civicrm/org.civicrm.api4'  --commit='d5a853a6f4d1cad11e8655755b329f15eb3fc27b' --ver='4.1.0' -f

## (Dry run) Reconcile the info.xml and composer.json in a local copy of the api4 extension
./bin/extpub reconcile -v ~/bknix/build/dmaster/sites/all/modules/civicrm/ext/api4 -N

## (Real run) Reconcile the info.xml and composer.json in a local copy of the api4 extension
./bin/extpub reconcile -v ~/bknix/build/dmaster/sites/all/modules/civicrm/ext/api4
```

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
