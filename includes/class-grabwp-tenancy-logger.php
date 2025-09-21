<?php
/**
 * GrabWP Tenancy Logger
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class for GrabWP Tenancy plugins.
 *
 * This logger can work early in the WordPress loading process and writes
 * to a dedicated grabwp_debug.log file in wp-content.
 *
 * @since 1.0.0
 */
class GrabWP_Tenancy_Logger {


	/**
	 * Log file path.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static $log_file = null;



	/**
	 * Get log file path.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private static function get_log_file() {
		if ( null === self::$log_file ) {
			// Use GrabWP_Tenancy_Path_Manager if available
			if ( class_exists( 'GrabWP_Tenancy_Path_Manager' ) ) {
				$base_path = GrabWP_Tenancy_Path_Manager::get_configured_base_path();
				self::$log_file = $base_path . '/grabwp_debug.log';
			} else {
				return false;
			}
		}
		return self::$log_file;
	}



	/**
	 * Write log entry.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return bool True on success, false on failure.
	 */
	public static function log( $message, $context = array() ) {

		$log_file = self::get_log_file();
		if ( ! $log_file ) {
			return false;
		}

		// Format timestamp
		$timestamp = gmdate( 'Y-m-d H:i:s' );

		// Format context
		$context_str = '';
		if ( ! empty( $context ) ) {
			$context_str = ' ' . wp_json_encode( $context );
		}

		// Format log entry
		$log_entry = sprintf(
			'[%s] %s%s' . PHP_EOL,
			$timestamp,
			$message,
			$context_str
		);

		// Write to file
		return self::write_to_file( $log_file, $log_entry );
	}

	/**
	 * Write to log file.
	 *
	 * @since 1.0.0
	 * @param string $file_path File path.
	 * @param string $content   Content to write.
	 * @return bool True on success, false on failure.
	 */
	private static function write_to_file( $file_path, $content ) {
		// Ensure directory exists
		$dir = dirname( $file_path );
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
		}

		// Write to file
		return file_put_contents( $file_path, $content, FILE_APPEND | LOCK_EX ) !== false;
	}


}
