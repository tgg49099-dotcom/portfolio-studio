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
