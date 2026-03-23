<?php
/**
 * GrabWP Tenancy Settings Class
 *
 * Handles loading and saving plugin settings to a PHP file.
 * Settings are stored in GRABWP_TENANCY_BASE_DIR/settings.php
 * as a PHP array for early bootstrap access.
 *
 * @package GrabWP_Tenancy
 * @since   1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GrabWP Tenancy Settings Class
 *
 * @since 1.1.0
 */
class GrabWP_Tenancy_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var GrabWP_Tenancy_Settings|null
	 */
	private static $instance = null;

	/**
	 * Loaded settings array.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Whether settings have been loaded.
	 *
	 * @var bool
	 */
	private $loaded = false;

	/**
	 * Get singleton instance.
	 *
	 * @since  1.1.0
	 * @return GrabWP_Tenancy_Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — loads settings on first instantiation.
	 *
	 * @since 1.1.0
	 */
	private function __construct() {
		$this->load();
	}

	/**
	 * Get default settings.
	 *
	 * @since  1.1.0
	 * @return array Default settings values.
	 */
	public static function get_defaults() {
		return array(
			'disallow_file_mods' => true,
			'disallow_file_edit' => true,
			'hide_plugin_management'     => true,
			'hide_theme_management'      => true,
			'hide_grabwp_plugins'        => true,
		);
	}

	/**
	 * Get the settings file path.
	 *
	 * @since  1.1.0
	 * @return string Absolute path to the settings file.
	 */
	public function get_settings_file_path() {
		$base_dir = defined( 'GRABWP_TENANCY_BASE_DIR' )
			? GRABWP_TENANCY_BASE_DIR
			: GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();

		return trailingslashit( $base_dir ) . 'settings.php';
	}

	/**
	 * Load settings from the PHP file.
	 *
	 * @since 1.1.0
	 */
	private function load() {
		if ( $this->loaded ) {
			return;
		}

		$this->settings = self::get_defaults();
		$file           = $this->get_settings_file_path();

		if ( file_exists( $file ) && is_readable( $file ) ) {
			$grabwp_tenancy_settings = array();
			ob_start();
			include $file;
			ob_end_clean();

			if ( is_array( $grabwp_tenancy_settings ) ) {
				$this->settings = wp_parse_args( $grabwp_tenancy_settings, $this->settings );
			}
		}

		$this->loaded = true;
	}

	/**
	 * Get a single setting value.
	 *
	 * @since  1.1.0
	 * @param  string $key     Setting key.
	 * @param  mixed  $default Default value if not set.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		if ( isset( $this->settings[ $key ] ) ) {
			return $this->settings[ $key ];
		}

		$defaults = self::get_defaults();
		if ( null === $default && isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Get all settings.
	 *
	 * @since  1.1.0
	 * @return array All current settings merged with defaults.
	 */
	public function get_all() {
		return $this->settings;
	}

	/**
	 * Sanitize settings values.
	 *
	 * All current settings are boolean checkboxes.
	 *
	 * @since  1.1.0
	 * @param  array $raw_settings Raw input from form submission.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $raw_settings ) {
		$defaults  = self::get_defaults();
		$sanitized = array();

		foreach ( array_keys( $defaults ) as $key ) {
			$sanitized[ $key ] = ! empty( $raw_settings[ $key ] );
		}

		return $sanitized;
	}

	/**
	 * Save settings to the PHP file.
	 *
	 * @since  1.1.0
	 * @param  array $settings Settings array to save.
	 * @return bool  True on success, false on failure.
	 */
	public function save( $settings ) {
		$sanitized = $this->sanitize_settings( $settings );
		$file      = $this->get_settings_file_path();
		$dir       = dirname( $file );

		// Ensure directory exists.
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Build PHP file content.
		$content  = "<?php\n";
		$content .= "// GrabWP Tenancy Settings - Auto-generated. Do not edit manually.\n";
		$content .= "if ( ! defined( 'ABSPATH' ) ) { exit; }\n\n";
		$content .= '$grabwp_tenancy_settings = array(' . "\n";

		foreach ( $sanitized as $key => $value ) {
			$export   = $value ? 'true' : 'false';
			$content .= "\t'" . $key . "' => " . $export . ",\n";
		}

		$content .= ");\n";

		// Write the file.
		global $wp_filesystem;

		// Try to initialize the WP Filesystem.
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$result = false;
		if ( $wp_filesystem && $wp_filesystem->put_contents( $file, $content, FS_CHMOD_FILE ) ) {
			$result = true;
		} else {
			// Fallback to file_put_contents if WP_Filesystem is not available.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$result = ( false !== file_put_contents( $file, $content ) );
		}

		if ( $result ) {
			// Clear file system cache and PHP OpCache so the next request reads fresh data.
			clearstatcache( true, $file );
			if ( function_exists( 'opcache_invalidate' ) ) {
				opcache_invalidate( $file, true );
			}

			// Update in-memory settings.
			$this->settings = $sanitized;
		}

		return $result;
	}
}
