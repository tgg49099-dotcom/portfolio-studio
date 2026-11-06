# Portfolio Studio

Source code of **Portfolio Studio**, a WordPress plugin that turns WordPress into an
editorial portfolio: a `portfolio` content type, portfolio categories and tags, and
four project details for every item — **client**, **year**, **role** and
**technologies**.

The code published in the WordPress.org plugin directory is the code of this
repository: nothing else.

## What the plugin does

- Registers a `portfolio` content type — but only when the site does not already
  have one. If another portfolio plugin is installed, Portfolio Studio **reuses** it
  instead of overwriting it, so existing items, categories and tags keep working and
  the editor never shows a second list of categories.
- Registers a category and a tag taxonomy for the portfolio — again, only when
  they are missing. Hyphenated aliases used by other plugins (`portfolio-category`,
  `portfolio-tag`) are detected and reused.
- Stores four project details per item, each with a declared type, sanitised on
  every write path, and exposed to the REST API.
- Creates no custom database table. On deactivation it removes its own option and
  the default category **it** created, and nothing else.

## Repository layout

```
portfolio-studio/          The plugin, exactly as it is shipped.
  portfolio-studio.php     Plugin header and bootstrap.
  includes/                Content types, metadata, editor panel, lifecycle.
  readme.txt               The readme of the WordPress.org listing.
  uninstall.php            Cleanup, run when the plugin is deleted.
  assets/                  The screenshots of the listing (screenshot-1.png, screenshot-2.png).
  LICENSE                  GPL-2.0-or-later (required by WordPress.org).
tests/                     The test harness (see tests/README.md).
LICENSE                    GPL-2.0-or-later — the license of the whole repository.
```

The `portfolio-studio/languages/` folder is intentionally empty: it is where
WordPress.org's translation tooling puts the translations. Empty folders are not
tracked by git.

## Running the tests

The tests run against a **real WordPress**, through WP-CLI — there is no mock and no
network access. See [`tests/README.md`](tests/README.md) for the prerequisites and
the exact commands.

```bash
# The whole harness, against a WordPress install.
bash tests/run-tests.sh /path/to/wordpress

# Only the counter-proof of the input sanitisation.
bash tests/run-counter-proof.sh /path/to/wordpress
```

## Building the submission archive

The archive uploaded to WordPress.org is not committed here: it is built from the
plugin folder, with **frozen timestamps**, so that the same source always produces
the same archive (same md5). Without the `touch`, the md5 changes on every build
and proves nothing.

```bash
# 1. copy the plugin to a staging directory (one top-level folder only)
mkdir -p /tmp/build && rm -rf /tmp/build/portfolio-studio
cp -a portfolio-studio /tmp/build/portfolio-studio

# 2. freeze the timestamps
find /tmp/build -exec touch -h -t 202611050000 {} +

# 3. build the archive
( cd /tmp/build && zip -q -X -r /tmp/portfolio-studio.zip portfolio-studio )

# 4. check: the same source gives the same md5, build it twice
md5sum /tmp/portfolio-studio.zip
```

## The code

The plugin is four files and a bootstrap; there is no third-party library, no
build step and no generated code. The four project details, with their declared
type and their sanitiser, are declared in one place:

```php
// includes/class-portfolio-studio-meta.php
const KEY_CLIENT       = 'portfolio_studio_client';       // string
const KEY_YEAR         = 'portfolio_studio_year';         // integer
const KEY_ROLE         = 'portfolio_studio_role';         // string
const KEY_TECHNOLOGIES = 'portfolio_studio_technologies'; // array of strings

public static function sanitize_client( $value ) {
    if ( ! is_scalar( $value ) ) {
        return '';
    }

    return sanitize_text_field( (string) $value );
}
```

Every write path goes through those sanitisers, and the counter-proof in
`tests/` proves it: the same suite fails on a variant whose sanitiser has been
removed.

```bash
bash tests/run-counter-proof.sh /path/to/wordpress
```

## License

This repository — the plugin (`portfolio-studio/`), the test harness (`tests/`), the
documentation and the tooling — is licensed under the **GNU General Public License,
version 2 or later (GPL-2.0-or-later)**.

The WordPress.org plugin directory only accepts GPL-compatible plugins, and the plugin
published there is the code of this repository, so the repository carries the same
license. The full text is in [`LICENSE`](LICENSE); the plugin folder also carries its
own copy in [`portfolio-studio/LICENSE`](portfolio-studio/LICENSE).

[`NOTICE`](NOTICE) repeats that statement.

## Author

**Tgg Labs** — <https://labs.chiffreo.ch>
