# extpub: Extension Publisher

This provides a system which:

* Scans `git` repositories for tagged releases of CiviCRM extensions
* Reconciles general metadata in `info.xml` and `composer.json` (e.g. generating a `composer.json` based on `info.xml`).
* Adds release metadata in `info.xml` and `composer.json`
* Prepares archives for these releases

## Usage

## Requirements

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
