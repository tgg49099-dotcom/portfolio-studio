# Test harness

The tests run against a **real WordPress**, through WP-CLI. There is no mock of
WordPress, no stub of the database and no network access: an assertion that passes
here is an assertion about the real plugin running inside the real WordPress.

## Prerequisites

- PHP 7.4 or later (the plugin requires PHP 7.4+).
- [WP-CLI](https://wp-cli.org/) (`wp`), and a WordPress installation whose database
  is reachable.
- The plugin folder of this repository (`portfolio-studio/`).

## Running the whole harness

```bash
bash tests/run-tests.sh /path/to/wordpress
```

Environment variables:

| Variable | Meaning | Default |
| --- | --- | --- |
| `WP_DIR` | path of the WordPress installation | the first argument |
| `WP_CLI` | path of the WP-CLI binary | `wp` |
| `PS_FIXTURES` | where the fixtures are written | a temporary folder |

The runner installs the plugin, activates it, runs the phases, restores the site
(it activates the plugins that were active before) and prints the number of failed
phases. It never writes outside `WP_DIR` and `PS_FIXTURES`.

## The suites

| File | Phases | What it proves |
| --- | --- | --- |
| `suite.php` | `solo`, `activated`, `deactivated`, `coexistence` | the content type and the taxonomies are registered once and never overwrite an existing registration; the four metadata keys keep their declared type through a round trip; the activation and the deactivation leave nothing behind. |
| `suite-j1b.php` | `j1b-defaut1-*`, `j1b-defaut2`, `j1b-defaut3-*`, `j1b-readme` | the three defects found by the hostile review stay fixed: a category the plugin did not create is never deleted, the optional metadata cleanup really removes the rows, and an existing taxonomy is reused instead of duplicated. |
| `suite-parite.php` | `parite` | the five parity fixes measured against the original plugin: the URL slugs of the taxonomies, `rewrite.with_front`, the position of the admin menu, the admin labels and the multisite activation. |
| `suite-secu.php` | `secu-hostile` | a hostile string typed in the editor panel never reaches the database nor the rendered page. |

The `coexistence` phase needs another portfolio plugin on the site (the runner uses
`portfolio-post-type` when it is installed); it is skipped otherwise.

## The counter-proof of the input sanitisation

`suite-secu.php` exists because the official WordPress.org validator (Plugin Check)
raises four `InputNotSanitized` warnings on the four `$_POST` reads of
`includes/class-portfolio-studio-fields.php`. The suite does not assert that the
warning is a false positive: it injects a hostile string through the real save path
and asserts that nothing reaches the database nor the rendered page.

A test that cannot fail proves nothing, so the same suite is run against a variant
of the plugin whose sanitiser does not sanitise. That variant is **built from the
current source**, so it is always the current code with the sanitisation removed and
nothing else:

```bash
bash tests/run-counter-proof.sh /path/to/wordpress
```

It prints:

- the result of the suite against the real plugin (it must pass);
- the result of the same suite against the unsanitised variant (it must fail).

## The proof of the uninstall routine

`uninstall.php` runs when the plugin is deleted from the Plugins screen. It must
remove what the plugin owns and **nothing the user wrote**: not the `portfolio`
items, and not their metadata unless the site owner asks for it explicitly.

```bash
bash tests/run-uninstall-proof.sh /path/to/wordpress
```

`uninstall-keep-proof.php` creates a `portfolio` item with the four metadata keys
of the plugin and one key of the site, then runs the uninstall twice — without and
with the explicit opt-in — and asserts that:

- the item of the user still exists, is not trashed, and its title and content are intact;
- the four metadata keys of the plugin are **kept** when the cleanup is not requested;
- they are **removed** when the cleanup is requested (the
  `portfolio_studio_uninstall_remove_post_meta` filter);
- the metadata of the site is never touched;
- the routine really ran (its own option is gone).

The same test is then run against a witness that deletes the `portfolio` items: the
test **must** fail. The witness is written to a temporary folder at every run — a
script that deletes the items of a site has no business in a public repository.

## The counter-proof of the five parity fixes

`suite-parite.php` compares the objects WordPress actually registered with the
values of the **original** plugin (`portfolio-post-type` 1.0.1, the stable tag the
40 000 installations run). A test that cannot fail proves nothing, so the same
suite is run against the plugin **as it was before the five fixes**: the three
files of that version are shipped in `tests/variants/parite-avant/` — they are the
published files of the previous commit, not a hand-written variant.

```bash
bash tests/run-parite-proof.sh /path/to/wordpress
```

It prints:

- the result of the suite against the plugin as it is (it must pass);
- the result of the same suite against the previous version (it **must fail**, and
  each of the five defects must be caught by at least one assertion);
- the result of the suite once the corrected files are put back (it must pass again).

The script takes the WordPress path as its first argument, the folder of the
previous version as its second and the folder of the plugin as its third:

```bash
bash tests/run-parite-proof.sh /path/to/wordpress tests/variants/parite-avant portfolio-studio
```

The multisite API is not loaded on a single site since WordPress 6.4: the suite
loads it itself and injects the list of the sites with the `sites_pre_query`
filter. Nothing is written to the network tables.

## Writing a test

The framework is `framework.php`: a tiny TAP-like runner with `ok()`, `same()`,
`equals()` and `not_empty()`. A suite is a PHP file that is executed by
`wp eval-file`, so it runs inside WordPress with every core function available.

```php
require_once __DIR__ . '/framework.php';

PS_Test_Runner::same( 4, count( $ps_definitions ), 'four metadata keys are declared' );

exit( PS_Test_Runner::summary() );
```

`summary()` prints the counters and returns the exit code: 0 when everything
passed.
