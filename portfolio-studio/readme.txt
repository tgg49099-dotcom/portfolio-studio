=== Portfolio Studio ===
Contributors: tgg49099
Tags: portfolio, projects, clients, custom post type
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Editorial portfolio for WordPress: a portfolio content type with client, year, role and technologies.

== Description ==

Replacing Portfolio Post Type? Deactivate it, activate Portfolio Studio: your items, categories and tags stay.

Portfolio Studio turns WordPress into an editorial portfolio: a `portfolio` content type,
a category and a tag taxonomy, and four project details for every item — **client**,
**year**, **role** and **technologies**.

Each detail has a declared type, so the same value is stored and shown in the editor the same way
for every item, and the data stays yours: it lives in your own database, in your own posts.

== Works alongside the portfolio plugin you already have ==

**Replace Portfolio Post Type: deactivate it, activate Portfolio Studio — your items,
categories and tags stay.**

Portfolio Studio does not create a second portfolio: it uses the `portfolio` content type
that already exists on your site, so every item you published stays exactly where it is —
nothing to migrate, nothing to re-enter, no duplicate categories.
The same goes for the taxonomies: if your site already has portfolio categories or tags,
Portfolio Studio uses them instead of adding a second list next to them.
If your site has no portfolio content type yet, Portfolio Studio simply registers one.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate it from the Plugins screen.
3. Open **Portfolio → Add New Portfolio Item** and fill in the project details.
4. Manage the categories under **Portfolio Categories** and the technologies under the item itself.

Deactivating the plugin removes its own settings and the default category it created, and
nothing else. The details you typed on your portfolio items stay in place.

== Frequently Asked Questions ==

= Does it work with the portfolio items I already have? =

Yes. If your site already has a `portfolio` content type, Portfolio Studio attaches to it
instead of replacing it: your existing items, categories and tags keep working, and the
project details become available on them.

= Which details can I record? =

Client, year, role and technologies. Each detail has a declared type, so the same value is
stored and shown in the editor the same way for every item.

= Are the project details private? =

With the content type this plugin registers, the WordPress REST API does not publish
them: that content type does not declare the custom fields support, so the API does
not expose the details at all. They live in your own database and are shown in the
editor, and only the people who can edit the item can change them. Do not put anything
confidential in them anyway: that is a property of the content type, not a promise
of the plugin. If another plugin owns that content type and declares the custom fields
support, the API can expose them: check that before you type anything private.

= Does the plugin create a category of its own? =

On activation, Portfolio Studio creates one default category in the portfolio categories —
the one named `Uncategorized`, with the slug `uncategorized` — and only when the portfolio
categories do not already hold that category: it never adds a second list next to the
categories you already have. It removes that default category when you deactivate the
plugin, unless one of your items already uses it.

= What happens when I deactivate the plugin? =

The plugin removes its own settings and the default category it created, and nothing else.
The details you typed on your portfolio items stay in place.

= How do I report a security issue? =

Please report it privately to tgg.apps.support@gmail.com, with the plugin version, your WordPress
version and the steps needed to reproduce the issue. Please do not open a public support thread
for a security issue.

== Screenshots ==

1. The project details panel on a portfolio item.
2. The portfolio categories screen.

== Changelog ==

= 0.1.2 =
* Multisite: a site created later on a network is configured only when the plugin is
  activated for the whole network. The sites of the network where the plugin is not
  activated are left untouched.
* Internal: the deprecated WordPress hook `wpmu_new_blog` is no longer used.

= 0.1.1 =
* Parity fixes: the category and tag URL slugs, the permalink prefix, the admin menu
  position, the admin labels and the multisite activation now match the original
  Portfolio Post Type plugin exactly.

= 0.1.0 =
* First release: portfolio content type, portfolio categories and tags, and the client,
  year, role and technologies project details.
* Coexists with any other plugin that already registers the same content type: existing
  items, categories and tags are reused, never replaced.
