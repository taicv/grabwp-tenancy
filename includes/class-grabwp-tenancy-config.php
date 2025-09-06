<?php
/**
 * GrabWP Tenancy Config Class
 *
 * Handles tenant configuration management.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GrabWP Tenancy Config Class
 *
 * @since 1.0.0
 */
class GrabWP_Tenancy_Config {

	/**
	 * Config file path
	 *
	 * @var string
	 */
	private static $config_file = null;

	/**
	 * Cached configuration
	 *
	 * @var array
	 */
	private static $cached_config = null;

	/**
	 * Initialize config system
	 */
	public static function init() {
		self::$config_file = GrabWP_Tenancy_Path_Manager::get_config_file_path();

		// Ensure grabwp directory exists
		$grabwp_dir = dirname( self::$config_file );
		if ( ! is_dir( $grabwp_dir ) ) {
			wp_mkdir_p( $grabwp_dir );
		}
	}

	/**
	 * Get tenant configuration
	 *
	 * @return array Configuration array
	 */
	public static function get_tenant_config() {
		if ( null === self::$cached_config ) {
			self::$cached_config = self::load_config();
		}
		return self::$cached_config;
	}

	/**
	 * Load configuration from file
	 *
	 * @return array Configuration array
	 */
	private static function load_config() {
		if ( ! file_exists( self::$config_file ) ) {
			return array(
				'load_extra_js_file' => '',
				'default_options'    => array(),
			);
		}

		// Load configuration variables
		$grabwp_auto_tasks             = array();
		$grabwp_default_tenant_options = array();

		include_once self::$config_file;

		return array(
			'load_extra_js_file' => $grabwp_auto_tasks['load_extra_js_file'] ?? '',
			'default_options'    => $grabwp_default_tenant_options ?? array(),
		);
	}

	/**
	 * Save configuration to file
	 *
	 * @param array $config Configuration to save
	 * @return bool True on success, false on failure
	 */
	public static function save_config( $config ) {
		// Load existing config first
		$existing_config = self::get_tenant_config();

		// Merge with new config
		$final_config = array_merge( $existing_config, $config );

		// Build file content
		$content  = "<?php\n";
		$content .= "/**\n";
		$content .= " * GrabWP Tenancy Configuration\n";
		$content .= " * Auto-generated configuration file\n";
		$content .= ' * Generated on: ' . gmdate( 'Y-m-d H:i:s' ) . "\n";
		$content .= " */\n\n";
		$content .= "// Prevent direct access\n";
		$content .= "if ( ! defined( 'ABSPATH' ) ) {\n";
		$content .= "    exit;\n";
		$content .= "}\n\n";

		// Auto tasks configuration
		if ( isset( $final_config['load_extra_js_file'] ) ) {
			$content .= "// Auto tasks configuration\n";
			$content .= "\$grabwp_auto_tasks = array(\n";
			$content .= "    'load_extra_js_file' => " . self::format_php_value( $final_config['load_extra_js_file'] ) . ",\n";
			$content .= ");\n\n";
		}

		// Default tenant options
		if ( isset( $final_config['default_options'] ) && ! empty( $final_config['default_options'] ) ) {
			$content .= "// Default tenant options\n";
			$content .= '$grabwp_default_tenant_options = ' . self::format_php_array( $final_config['default_options'] ) . ";\n";
		}

		// Write to file
		$result = file_put_contents( self::$config_file, $content );

		// Clear cache to force reload
		self::$cached_config = null;

		return $result !== false;
	}

	/**
	 * Get specific config value
	 *
	 * @param string $key Config key
	 * @param mixed  $default Default value if key not found
	 * @return mixed Config value
	 */
	public static function get( $key, $default = null ) {
		$config = self::get_tenant_config();
		return $config[ $key ] ?? $default;
	}

	/**
	 * Set specific config value
	 *
	 * @param string $key Config key
	 * @param mixed  $value Config value
	 * @return bool True on success, false on failure
	 */
	public static function set( $key, $value ) {
		$config = array( $key => $value );
		return self::save_config( $config );
	}

	/**
	 * Check if config file exists
	 *
	 * @return bool True if config file exists
	 */
	public static function config_file_exists() {
		return file_exists( self::$config_file );
	}

	/**
	 * Get config file path
	 *
	 * @return string Config file path
	 */
	public static function get_config_file_path() {
		return self::$config_file;
	}

	/**
	 * Format a PHP value for safe output in configuration files
	 *
	 * @param mixed $value Value to format
	 * @return string Formatted PHP value string
	 */
	private static function format_php_value( $value ) {
		if ( is_string( $value ) ) {
			return "'" . addslashes( $value ) . "'";
		} elseif ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		} elseif ( is_null( $value ) ) {
			return 'null';
		} elseif ( is_numeric( $value ) ) {
			return (string) $value;
		} else {
			// For complex types, use WordPress serialization
			return "'" . addslashes( maybe_serialize( $value ) ) . "'";
		}
	}

	/**
	 * Format a PHP array for safe output in configuration files
	 *
	 * @param array $array Array to format
	 * @return string Formatted PHP array string
	 */
	private static function format_php_array( $array ) {
		if ( ! is_array( $array ) ) {
			return self::format_php_value( $array );
		}

		$output = "array(\n";
		foreach ( $array as $key => $value ) {
			$formatted_key   = is_string( $key ) ? "'" . addslashes( $key ) . "'" : $key;
			$formatted_value = self::format_php_value( $value );
			$output         .= "    {$formatted_key} => {$formatted_value},\n";
		}
		$output .= ')';

		return $output;
	}
}
