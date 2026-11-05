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
  LICENSE                  GPL-2.0-or-later (required by WordPress.org).
tests/                     The test harness (see tests/README.md).
LICENSE                    MIT, for everything outside portfolio-studio/.
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
plugin folder, with frozen timestamps, so that the same source always produces the
same archive.

```bash
cd portfolio-studio && zip -X -r ../portfolio-studio.zip portfolio-studio
```

## License

- The plugin (`portfolio-studio/`) is licensed under **GPL-2.0-or-later** — the
  WordPress.org plugin directory only accepts GPL-compatible plugins.
- Everything else in this repository (tests, tooling, documentation) is licensed
  under the **MIT** license.

See [`LICENSE`](LICENSE) for the scope of each license.

## Author

**Tgg Labs** — <https://labs.chiffreo.ch>
