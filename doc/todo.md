# *Backlog*: Major issues / questions / tasks / proposals

* Generally, make the various notes (like doc/site-build.md) more readable. Stress test more configurations.

* How do we get composer to resolve dependencies in which an ext requires a specific version of `civicrm-core`? (Possibly different answers depending on deployment style.)

* Optimize the `package.json` file structure to improve cache-ability.
  (This basically requires splitting into smaller index files that can be more static.)

* Listen for and record `notify-batch` events

* Either supplement or replace the Civi-D7 module (`extdir`), e.g.
    * Getting `info.xml` to the web ui:
        * Upload extracted `info.xml` files to `civicrm.org`
        * Load all `info.xml` files into a database and implement support for the `/extdir/{filter}/` and `/extdir/{filter}/single` requests. Update infra/extdir to pull from here instead.
    * Handling dependencies:
        * Maybe provide two `*.zip` files, one for composer-consumers (clean/minimal) and one for traditional consumers (with `./vendor`, but excluding any Civi ext's)

* Present some kind of web UI or notification for discovering build failures (`*.error.json`)

* How to support people who want to use unreviewed/alpha/beta/dev extensions? Maybe this works already (provided)

* Are missing out anything major by virtue of only publishing binaries?

* Should `civicrm.org` have a flag where publishers can choose which scanner to enable?
