# comex: CiviCRM-Composer Extension Publisher (Proof of Concept)

`comex` is a system for publishing CiviCRM extensions as `composer` packages.

Extensions from the `civicrm.org` extension directory are automatically scanned. Each tagged version
is published as a `composer` package in the format `comex/<key>` (e.g. `comex/org.civicrm.flexmailer`).

* [*Site Building*: Building a Civi site and downloading extensions from comex](doc/site-build.md)
* [*Publishing*: Sharing extensions through comex](doc/publish.md)
* [*Administration*: Managing the comex system](doc/admin.md)
* [*Backlog*: Major issues and tasks](doc/backlog.md)

## Background/Goal

Get past the log-jam -- we've stagnated because there's no easy+perfect
solution to composer+extensions.  Bite the bullet and try a solution to
the first obvious problem.
