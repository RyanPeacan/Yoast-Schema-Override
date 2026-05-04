<?php
/**
 * Plugin Name:       Yoast Schema Override
 * Plugin URI:        https://github.com/ryanpeacan/yoast-schema-override
 * Description:       Override Yoast SEO schema output on a per-page/post basis using a simple field builder or raw JSON.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  wordpress-seo
 * Author:            Ryan Peacan
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       yoast-schema-override
 * Domain Path:       /languages
 *
 * @package YoastSchemaOverride
 */

defined( 'ABSPATH' ) || exit;

define( 'YSO_VERSION', '1.0.0' );
define( 'YSO_PLUGIN_FILE', __FILE__ );
define( 'YSO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YSO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once YSO_PLUGIN_DIR . 'includes/class-yso-plugin.php';
require_once YSO_PLUGIN_DIR . 'includes/class-yso-metabox.php';
require_once YSO_PLUGIN_DIR . 'includes/class-yso-simple-schema.php';
require_once YSO_PLUGIN_DIR . 'includes/class-yso-schema-filter.php';

/**
 * Bootstraps the plugin on plugins_loaded so all other plugins are available.
 */
function yso_init() {
	YSO_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'yso_init' );
