# *Administration*: Managing the comex system

`comex` runs in a few distinct phases:

* `comex scan`: Given a list of `git` repositories, figure a full list of extensions/versions which should be built
* `comex build`: Given a specific extension/version (with `git` repository and commit), prepare a `*.zip` release.
  (By default, this is lazy and quits quickly if the `*.zip` is already built.)
    * `comex reconcile`: As part of the `comex build` process, analyze the `info.xml` and `composer.json` files.
      Combine key information (such as the list of dependencies) to provide consistent information in both formats.
* `comex extract`: Given a series of `*.zip` releases, extract metadata. Augment it with extra publication details
  (e.g. download URLs and checksums).
* `comex compile`: Given a full set of metadata, combine it all into one feed.

## Examples: `scan`

```
## Scan the feed of published extensions. Take the first two. Plan how to build them.
./bin/comex scan -v --git-feed https://civicrm.org/extdir/git-urls.json --limit 2

## Scan a couple of local git repos. Plan how to build them.
./bin/comex scan -v ~/src/{api4,flexmailer,mosaico}

## As above, but actually execute the commands (4 parallel threads)
./bin/comex scan ~/src/{api4,flexmailer,mosaico} -px \
  | xargs -L1 -P4 ./bin/comex
```

## Examples: `build`

```
## (Dry run) Build the extension 'org.civicrm.api4' at version 4.1.0.
./bin/comex build -v --ext='org.civicrm.api4' --git-url='https://github.com/civicrm/org.civicrm.api4' \
  --commit='d5a853a6f4d1cad11e8655755b329f15eb3fc27b' --ver='4.1.0' -f -N

## (Real run) Build the extension 'org.civicrm.api4' at version 4.1.0. Overwrite any existing zip files.
./bin/comex build -v --ext='org.civicrm.api4' --git-url='https://github.com/civicrm/org.civicrm.api4' \
  --commit='d5a853a6f4d1cad11e8655755b329f15eb3fc27b' --ver='4.1.0' -f
```

## Examples: `reconcile`

```
## (Dry run) Reconcile the info.xml and composer.json in a local copy of the api4 extension
./bin/comex reconcile -v ~/src/api4 -N

## (Real run) Reconcile the info.xml and composer.json in a local copy of the api4 extension
./bin/comex reconcile -v ~/src/api4
```

## Examples: `extract`

```
## Extract and augment metadata for `*.zip` files. (Lazy mode)
./bin/comex extract --web-url http://localhost

## Extract and augment metadata for `*.zip` files. (Force mode)
./bin/comex extract --web-url http://localhost -f
```

## Examples: `compile`

```
## Compile all the metadata into one feed.
./bin/comex compile
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
