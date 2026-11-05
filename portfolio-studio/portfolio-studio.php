<?php
/**
 * Plugin Name:       Portfolio Studio
 * Plugin URI:        https://github.com/tgg49099-dotcom/portfolio-studio
 * Description:       Editorial portfolio for WordPress: a portfolio content type, categories and tags, and per-item details (client, year, role, technologies).
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Tgg Labs
 * Author URI:        https://labs.chiffreo.ch
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       portfolio-studio
 * Domain Path:       /languages
 *
 * @package Portfolio_Studio
 *
 * `Plugin URI` points to the public source repository of the plugin: the code that
 * ships in the WordPress.org directory is the code of that repository, nothing else.
 *
 * The plugin coexists with any other plugin that already registers the same content
 * type: it reuses the existing content type and taxonomies instead of replacing
 * them, so the items, categories and tags already published keep working.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'PORTFOLIO_STUDIO_VERSION' ) ) {
	// The file was already loaded in this request (WordPress includes a plugin file
	// again inside the activation sandbox). Never declare anything twice.
	return;
}

define( 'PORTFOLIO_STUDIO_VERSION', '0.1.0' );
define( 'PORTFOLIO_STUDIO_SLUG', 'portfolio-studio' );
define( 'PORTFOLIO_STUDIO_FILE', __FILE__ );
define( 'PORTFOLIO_STUDIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'PORTFOLIO_STUDIO_BASENAME', plugin_basename( __FILE__ ) );

require_once PORTFOLIO_STUDIO_DIR . 'includes/class-portfolio-studio-content-types.php';
require_once PORTFOLIO_STUDIO_DIR . 'includes/class-portfolio-studio-meta.php';
require_once PORTFOLIO_STUDIO_DIR . 'includes/class-portfolio-studio-fields.php';
require_once PORTFOLIO_STUDIO_DIR . 'includes/class-portfolio-studio-lifecycle.php';

register_activation_hook( __FILE__, array( 'Portfolio_Studio_Lifecycle', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Portfolio_Studio_Lifecycle', 'deactivate' ) );

/**
 * Boot the plugin.
 *
 * The content types are registered late on `init` (priority 20) on purpose: plugins that
 * already register the same content type hook `init` at the default priority 10, so by the
 * time we run they have had their say and we can detect them instead of overwriting them.
 *
 * @return void
 */
function portfolio_studio_bootstrap() {
	Portfolio_Studio_Content_Types::register();
	Portfolio_Studio_Meta::register();
	Portfolio_Studio_Fields::register();
}
add_action( 'init', 'portfolio_studio_bootstrap', 20 );
