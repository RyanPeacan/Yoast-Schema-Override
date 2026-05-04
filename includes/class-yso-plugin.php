<?php
/**
 * Core plugin class: dependency check, singleton bootstrap, hook registration.
 *
 * @package YoastSchemaOverride
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class YSO_Plugin
 */
class YSO_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var YSO_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether Yoast SEO is active and available.
	 *
	 * @var bool
	 */
	private $yoast_active = false;

	/**
	 * Returns the singleton instance.
	 *
	 * @return YSO_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — checks dependencies and registers hooks.
	 */
	private function __construct() {
		$this->yoast_active = defined( 'WPSEO_VERSION' );

		if ( ! $this->yoast_active ) {
			add_action( 'admin_notices', array( $this, 'notice_yoast_missing' ) );
			return;
		}

		$this->register_hooks();
	}

	/**
	 * Registers all plugin hooks.
	 */
	private function register_hooks() {
		// Load translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Metabox (admin only).
		if ( is_admin() ) {
			$metabox = new YSO_Metabox();
			$metabox->register_hooks();
		}

		// Front-end schema filter.
		$schema_filter = new YSO_Schema_Filter();
		$schema_filter->register_hooks();
	}

	/**
	 * Loads the plugin text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'yoast-schema-override',
			false,
			dirname( plugin_basename( YSO_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Admin notice shown when Yoast SEO is not active.
	 */
	public function notice_yoast_missing() {
		$message = sprintf(
			/* translators: %s: plugin name */
			__( '<strong>Yoast Schema Override</strong> requires %s to be installed and activated.', 'yoast-schema-override' ),
			'<a href="https://yoast.com/wordpress/plugins/seo/" target="_blank" rel="noopener noreferrer">Yoast SEO</a>'
		);
		printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
	}
}
