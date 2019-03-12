# *Publishing*: Sharing extensions through comex

...Explanation for extension authors... At time of writing, this is based on planning/speculation and not fuly tested...

## ...Workflow...

* Create your extension
* If your extension has dependencies on other extensions, list the extensions in one or both of these media:
    * `composer.json`: `"require": {"comex/org.other.extension": "~2.5}`
    * `info.xml`: `<requires><ext version="~2.5">org.other.extension</ext></requires>`
* Publish it to a public, standalone repo on `github.com` or `lab.civicrm.org`
* Register the extension on `civicrm.org`. Specify the "Git URL".
* Whenever you do a release, a make a tag.
    * Note: It is **not required** to edit the `info.xml` to specify `<version>`, `<releaseDate>`, `<develStage>`, or `<downloadUrl>`. `comex` will automatically fill these in.

## ...Metadata reconciliation...

There are two formats in which you can describe a package (`info.xml` and `composer.json`). There is a lot of overlap between them, which invites duplicate steps and mistakes. To mitigate this, `comex` performs automatic reconciliation. You can get more complete details in the [source code](../scriptlet/reconcile) and [test cases](../tests/fixtures/reconcile)), but here's a general summary:

* The extension key from `info.xml` (eg `org.example.foo`) is copied to `composer.json` as a package name (`comex/org.example.foo`). However, if you've already set a different name in `composer.json`, then `comex/org.example.foo` functions as an alias.
* The `version` and `develStage` are taken from the `git` tag and copied into both `info.xml` and `composer.json`. These replace any existing values.
* The `description` and `license` are copied from `info.xml`. These are only used to fill-in blanks.
* The list of requirements is read from both `info.xml` and `composer.json`. It is merged/deduped and written back to both -- which ensures that:
   * The `composer` download process can see the dependency list.
   * The `civicrm` activation process can see the depenency list.

## Use-Case: Specifying a plain-old extension

Suppose your extension is a thin, plain-old extension -- it has no external dependencies, or it only depends on other extensions. Most extensions published in the directory are like this.

Suggestions:
* Use `info.xml` as the canonical metadata. If you need versioned-dependencies, use the notation `<requires><ext version="~2.5">org.example.foobar</ext></requires>`
* Omit `composer.json` completely. (The file will be auto-generated based on `info.xml`.)

## Use-Case: Specifying a composer-ified extension

Suppose your extension requires third-party PHP libraries or needs more advanced package-relationships (`require-dev`,  `provide`, `suggest`, `conflict`, `replace`). There is no way to enumerate these requirements in `info.xml`.

Suggestions:
* Use `info.xml` for name/description/license. *Omit* the `<requires>` section. (This will be auto-generated.)
* Use `composer.json` for all package-relationships. *Omit* other metadata. Be sure to reference extensions in the notation `comex/<ext-key>`.

NOTE: Some existing/pre-comex extensions may already bundle in their own private `vendor/` folder -- such extensions would include a statement like `require_once './vendor/autoload.php`. This makes a trade-off that I understand but which is problematic in the long-run (e.g. convenient for non-composer consumers; but prone to mis-matching versions). In the interim, while that's not resolved, I'd suggest this minimal change to improve forward-compatibility with a more "correct" mechanism:

```php
## Old
require_once __DIR__ . '/vendor/autoload.php';

## Revised
if (file_exists(__DIR__ . '/vendor/autoload.php') && !defined('CIVICRM_UNIFIED_AUTOLOAD')) {
  require_once __DIR__ . '/vendor/autoload.php';
}
```
