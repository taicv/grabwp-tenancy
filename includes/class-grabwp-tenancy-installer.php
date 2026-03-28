<?php
/**
 * GrabWP Tenancy Installer
 *
 * Single source of truth for all install, fix, activate, and deactivate operations.
 * Handles: directories, htaccess, tenant mappings, MU-plugin, wp-config loader.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GrabWP_Tenancy_Installer {

	/**
	 * MU-plugin filename.
	 */
	const MU_PLUGIN_FILE = 'mu-grabwp-tenancy.php';

	// =========================================================================
	// Activation / Deactivation
	// =========================================================================

	/**
	 * Full plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Data directory & security files.
		self::create_directories();
		self::create_htaccess();
		self::create_index_protection();
		self::create_tenant_mappings_file();

		// Root .htaccess rewrite rules for /site/[tenant-id].
		self::add_site_path_rewrite_rules();

		// MU-plugin and wp-config loader (silent — failures shown on status page).
		self::install_mu_plugin();
		self::install_loader();
	}

	/**
	 * Full plugin deactivation.
	 *
	 * @since 1.1.0
	 */
	public static function deactivate() {
		// Root .htaccess rewrite rules.
		self::remove_site_path_rewrite_rules();

		// wp-config loader and MU-plugin.
		self::remove_loader();
		self::remove_mu_plugin();
	}

	// =========================================================================
	// MU-Plugin Install / Remove
	// =========================================================================

	/**
	 * Install the MU-plugin file.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function install_mu_plugin() {
		$mu_dir  = self::get_mu_plugins_dir();
		$mu_path = $mu_dir . '/' . self::MU_PLUGIN_FILE;

		if ( file_exists( $mu_path ) ) {
			return array( 'success' => true, 'message' => 'MU-Plugin is already installed.' );
		}

		if ( ! is_dir( $mu_dir ) ) {
			if ( ! wp_mkdir_p( $mu_dir ) ) {
				return array( 'success' => false, 'message' => 'Could not create mu-plugins directory. Please check file permissions.' );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = @file_put_contents( $mu_path, self::get_mu_plugin_content() );

		if ( false === $result ) {
			return array( 'success' => false, 'message' => 'Could not write MU-Plugin file. Please check file permissions.' );
		}

		return array( 'success' => true, 'message' => 'MU-Plugin installed successfully.' );
	}

	/**
	 * Remove the MU-plugin file.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function remove_mu_plugin() {
		$mu_path = self::get_mu_plugin_path();

		if ( ! file_exists( $mu_path ) ) {
			return array( 'success' => true, 'message' => 'MU-Plugin was not installed.' );
		}

		// Verify it's our file before deleting.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = file_get_contents( $mu_path );
		if ( false !== $content && strpos( $content, 'grabwp-tenancy' ) === false ) {
			return array( 'success' => false, 'message' => 'MU-Plugin file exists but does not appear to be ours. Skipping removal.' );
		}

		wp_delete_file( $mu_path );
		if ( file_exists( $mu_path ) ) {
			return array( 'success' => false, 'message' => 'Could not delete MU-Plugin file. Please check file permissions.' );
		}

		return array( 'success' => true, 'message' => 'MU-Plugin removed successfully.' );
	}

	// =========================================================================
	// wp-config.php Loader Install / Remove
	// =========================================================================

	/**
	 * Inject load.php require into wp-config.php using marker comments.
	 *
	 * Uses `# BEGIN/END GrabWP Tenancy` markers (valid PHP comments) for clean
	 * install/removal. Places the block before the stop-editing marker on first
	 * install. The injected code uses file_exists() to prevent white-screen if
	 * the plugin is deleted without deactivation.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function install_loader() {
		$wp_config_path = ABSPATH . 'wp-config.php';

		if ( ! self::filesystem_is_writable( $wp_config_path ) ) {
			return array( 'success' => false, 'message' => 'wp-config.php is not writable. Please check file permissions.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = file_get_contents( $wp_config_path );

		if ( false === $content ) {
			return array( 'success' => false, 'message' => 'Could not read wp-config.php.' );
		}

		$marker_start = '# BEGIN GrabWP Tenancy';

		// Already installed with markers — idempotent.
		if ( strpos( $content, $marker_start ) !== false ) {
			return array( 'success' => true, 'message' => 'Loader is already present in wp-config.php.' );
		}

		// Legacy check: raw require without markers (from older versions).
		if ( strpos( $content, 'grabwp-tenancy/load.php' ) !== false ) {
			return array( 'success' => true, 'message' => 'Loader is already present in wp-config.php (legacy format).' );
		}

		// Build the marker block — safe with file_exists() guard.
		$block = $marker_start . "\n"
			. '( $_grabwpl = __DIR__ . "/wp-content/plugins/grabwp-tenancy/load.php" ) && file_exists( $_grabwpl ) && require_once $_grabwpl;' . "\n"
			. '# END GrabWP Tenancy' . "\n\n";

		// Find the stop-editing marker for placement.
		$pos = strpos( $content, "/* That's all, stop editing! Happy publishing. */" );
		if ( false === $pos ) {
			$pos = strpos( $content, "/* That's all, stop editing! */" );
		}

		if ( false === $pos ) {
			return array( 'success' => false, 'message' => 'Could not find the stop-editing marker in wp-config.php. Please add the line manually.' );
		}

		// Insert before the stop-editing marker.
		$new_content = substr( $content, 0, $pos ) . $block . substr( $content, $pos );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = @file_put_contents( $wp_config_path, $new_content );

		if ( false === $result ) {
			return array( 'success' => false, 'message' => 'Could not write to wp-config.php. Please check file permissions.' );
		}

		return array( 'success' => true, 'message' => 'Loader installed to wp-config.php successfully.' );
	}

	/**
	 * Remove the GrabWP Tenancy loader block from wp-config.php.
	 *
	 * Removes everything between `# BEGIN GrabWP Tenancy` and `# END GrabWP Tenancy`
	 * markers (inclusive). Also handles legacy single-line format.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function remove_loader() {
		$wp_config_path = ABSPATH . 'wp-config.php';

		if ( ! self::filesystem_is_writable( $wp_config_path ) ) {
			return array( 'success' => false, 'message' => 'wp-config.php is not writable.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = file_get_contents( $wp_config_path );

		if ( false === $content ) {
			return array( 'success' => false, 'message' => 'Could not read wp-config.php.' );
		}

		$changed      = false;
		$marker_start = '# BEGIN GrabWP Tenancy';
		$marker_end   = '# END GrabWP Tenancy';
		$start_pos    = strpos( $content, $marker_start );

		// Remove marker block.
		if ( false !== $start_pos ) {
			$end_pos = strpos( $content, $marker_end, $start_pos );
			if ( false !== $end_pos ) {
				$block_end = $end_pos + strlen( $marker_end );
				$before    = rtrim( substr( $content, 0, $start_pos ) ) . "\n";
				$after     = ltrim( substr( $content, $block_end ) );
				$content   = $before . "\n" . $after;
				$changed   = true;
			}
		}

		// Also remove legacy single-line format (no markers).
		if ( ! $changed ) {
			$legacy_line = 'require_once __DIR__ . "/wp-content/plugins/grabwp-tenancy/load.php";';
			if ( strpos( $content, $legacy_line ) !== false ) {
				$content = str_replace( $legacy_line . "\n", '', $content );
				$content = str_replace( $legacy_line, '', $content );
				$changed = true;
			}
		}

		if ( ! $changed ) {
			return array( 'success' => true, 'message' => 'Loader was not present in wp-config.php.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = @file_put_contents( $wp_config_path, $content );

		if ( false === $result ) {
			return array( 'success' => false, 'message' => 'Could not write to wp-config.php.' );
		}

		return array( 'success' => true, 'message' => 'Loader removed from wp-config.php.' );
	}

	// =========================================================================
	// Fix Helpers (for status page "Fix Now" buttons)
	// =========================================================================

	/**
	 * Fix root .htaccess by adding/repositioning the GrabWP Tenancy rewrite block.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function fix_root_htaccess() {
		self::add_site_path_rewrite_rules();

		// Verify it was written.
		$htaccess_file = ABSPATH . '.htaccess';
		if ( is_readable( $htaccess_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$content = file_get_contents( $htaccess_file );
			if ( false !== $content && strpos( $content, '# BEGIN GrabWP Tenancy' ) !== false ) {
				return array( 'success' => true, 'message' => 'Root .htaccess GrabWP Tenancy block installed successfully.' );
			}
		}

		return array( 'success' => false, 'message' => 'Could not write to .htaccess. Please check file permissions.' );
	}

	/**
	 * Fix data directory .htaccess security protection.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function fix_data_htaccess() {
		$base_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		$result   = self::create_htaccess_for_directory( $base_dir, 'GrabWP Tenancy Security Protection' );

		if ( $result ) {
			return array( 'success' => true, 'message' => 'Data directory .htaccess created successfully.' );
		}

		return array( 'success' => false, 'message' => 'Could not create data directory .htaccess. Please check file permissions.' );
	}

	/**
	 * Fix index.php protection in data directory.
	 *
	 * @since 1.3.0
	 * @return array{success: bool, message: string}
	 */
	public static function fix_index_protection() {
		$base_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		$result   = self::create_index_protection_for_directory( $base_dir, 'GrabWP_Tenancy' );

		if ( $result ) {
			return array( 'success' => true, 'message' => 'index.php protection created successfully.' );
		}

		return array( 'success' => false, 'message' => 'Could not create index.php protection. Please check file permissions.' );
	}

	// =========================================================================
	// Root .htaccess Rewrite Rules
	// =========================================================================

	/**
	 * Write /site/[tenant-id] rewrite rules into the WordPress root .htaccess.
	 *
	 * CRITICAL: These rules MUST appear BEFORE the WordPress rewrite block,
	 * otherwise WordPress's catch-all `RewriteRule . /index.php [L]` matches
	 * /site/{id}/* first (the path doesn't exist on disk) and the tenant
	 * rewrite rules never fire — causing a redirect loop.
	 *
	 * insert_with_markers() appends new blocks at the end of the file, so we
	 * manually reposition the block before "# BEGIN WordPress" after writing.
	 *
	 * @since 1.1.0
	 */
	public static function add_site_path_rewrite_rules() {
		$htaccess_file = ABSPATH . '.htaccess';

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$rules = array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'# Tenant homepage: /site/{tenant-id}[/] → WordPress front-end with site param',
			'RewriteRule ^site/([a-z0-9]{6})/?$ /index.php?site=$1 [QSA,L]',
			'# Tenant sub-paths (wp-admin, wp-login, pages, etc.): strip prefix, pass site param',
			'RewriteRule ^site/([a-z0-9]{6})/(.+)$ /$2?site=$1 [QSA,L,NE]',
			'</IfModule>',
		);

		insert_with_markers( $htaccess_file, 'GrabWP Tenancy', $rules );
		self::reposition_htaccess_block( $htaccess_file );
	}

	/**
	 * Remove /site/[tenant-id] rewrite rules from .htaccess on deactivation.
	 *
	 * @since 1.1.0
	 */
	public static function remove_site_path_rewrite_rules() {
		$htaccess_file = ABSPATH . '.htaccess';

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		insert_with_markers( $htaccess_file, 'GrabWP Tenancy', array() );
	}

	// =========================================================================
	// Data Directory Security Files (reusable utilities)
	// =========================================================================

	/**
	 * Create .htaccess file for any directory (reusable utility).
	 *
	 * @since 1.0.0
	 * @param string $directory      Directory path where to create .htaccess.
	 * @param string $comment_header Header comment for the .htaccess file.
	 * @return bool Success status.
	 */
	public static function create_htaccess_for_directory( $directory, $comment_header = 'GrabWP Tenancy Security Protection' ) {
		if ( empty( $directory ) || ! is_string( $directory ) ) {
			return false;
		}

		$htaccess_file = $directory . '/.htaccess';

		$htaccess_content  = "# {$comment_header}\n";
		$htaccess_content .= "# Prevent directory listing\n";
		$htaccess_content .= "Options -Indexes\n\n";
		$htaccess_content .= "# Deny access to PHP files\n";
		$htaccess_content .= "<FilesMatch \"\\.php$\">\n";
		$htaccess_content .= "    <IfModule mod_authz_core.c>\n";
		$htaccess_content .= "        Require all denied\n";
		$htaccess_content .= "    </IfModule>\n";
		$htaccess_content .= "    <IfModule !mod_authz_core.c>\n";
		$htaccess_content .= "        Order allow,deny\n";
		$htaccess_content .= "        Deny from all\n";
		$htaccess_content .= "    </IfModule>\n";
		$htaccess_content .= "</FilesMatch>\n\n";

		if ( ! file_exists( $directory ) ) {
			if ( ! wp_mkdir_p( $directory ) ) {
				return false;
			}
		}

		if ( ! file_exists( $htaccess_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$result = @file_put_contents( $htaccess_file, $htaccess_content );
			return false !== $result;
		}

		return true;
	}

	/**
	 * Create index.php protection for any directory (reusable utility).
	 *
	 * @since 1.0.0
	 * @param string $directory Directory path where to create index.php.
	 * @param string $package   Package name for the comment.
	 * @return bool Success status.
	 */
	public static function create_index_protection_for_directory( $directory, $package = 'GrabWP_Tenancy' ) {
		if ( empty( $directory ) || ! is_string( $directory ) ) {
			return false;
		}

		$index_file = $directory . '/index.php';

		$index_content  = "<?php\n";
		$index_content .= "/**\n";
		$index_content .= " * {$package} - Directory Protection\n";
		$index_content .= " * \n";
		$index_content .= " * @package {$package}\n";
		$index_content .= " */\n\n";
		$index_content .= "// Silence is golden.\n";

		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$result = @file_put_contents( $index_file, $index_content );
			return false !== $result;
		}

		return true;
	}

	// =========================================================================
	// MU-Plugin Helpers
	// =========================================================================

	/**
	 * Get the mu-plugins directory path.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public static function get_mu_plugins_dir() {
		return defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( ABSPATH . 'wp-content/mu-plugins' );
	}

	/**
	 * Get the expected mu-plugin file path.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public static function get_mu_plugin_path() {
		return self::get_mu_plugins_dir() . '/' . self::MU_PLUGIN_FILE;
	}

	/**
	 * Generate the mu-plugin file content.
	 *
	 * Uses __DIR__ for portable paths that work even if wp-content is relocated.
	 *
	 * @since 1.3.0
	 * @return string PHP file content.
	 */
	public static function get_mu_plugin_content() {
		return <<<'PHP'
<?php
// GrabWP Tenancy MU-Plugin — auto-generated.
$mu_grabwp_base = __DIR__ . '/../plugins/grabwp-tenancy/grabwp-tenancy.php';
$mu_grabwp_pro  = __DIR__ . '/../plugins/grabwp-tenancy-pro/grabwp-tenancy-pro.php';
if ( file_exists( $mu_grabwp_base ) ) { require_once $mu_grabwp_base; }
if ( file_exists( $mu_grabwp_pro ) )  { require_once $mu_grabwp_pro; }

PHP;
	}

	/**
	 * Get the loader line used in wp-config.php (for admin notice display).
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public static function get_loader_snippet() {
		return '( $_grabwpl = __DIR__ . "/wp-content/plugins/grabwp-tenancy/load.php" ) && file_exists( $_grabwpl ) && require_once $_grabwpl;';
	}

	/**
	 * Check if wp-config.php contains the stop-editing marker.
	 *
	 * @since 1.3.0
	 * @param string $wp_config_path Absolute path to wp-config.php.
	 * @return bool True if a marker was found.
	 */
	public static function has_stop_editing_marker( $wp_config_path ) {
		if ( ! is_readable( $wp_config_path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = file_get_contents( $wp_config_path );
		if ( false === $content ) {
			return false;
		}

		return strpos( $content, "/* That's all, stop editing! Happy publishing. */" ) !== false
			|| strpos( $content, "/* That's all, stop editing! */" ) !== false;
	}

	// =========================================================================
	// Private Helpers
	// =========================================================================

	/**
	 * Move the "GrabWP Tenancy" block before the "WordPress" block in .htaccess.
	 *
	 * @since 1.1.0
	 * @param string $htaccess_file Path to .htaccess file.
	 */
	private static function reposition_htaccess_block( $htaccess_file ) {
		if ( ! is_readable( $htaccess_file ) || ! self::filesystem_is_writable( $htaccess_file ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$contents = file_get_contents( $htaccess_file );
		if ( false === $contents ) {
			return;
		}

		$marker_start = '# BEGIN GrabWP Tenancy';
		$marker_end   = '# END GrabWP Tenancy';
		$wp_start     = '# BEGIN WordPress';

		$grabwp_pos = strpos( $contents, $marker_start );
		$wp_pos     = strpos( $contents, $wp_start );

		if ( false === $grabwp_pos || false === $wp_pos || $grabwp_pos < $wp_pos ) {
			return;
		}

		$end_pos = strpos( $contents, $marker_end );
		if ( false === $end_pos ) {
			return;
		}
		$block_end    = $end_pos + strlen( $marker_end );
		$grabwp_block = substr( $contents, $grabwp_pos, $block_end - $grabwp_pos );

		$before_block = rtrim( substr( $contents, 0, $grabwp_pos ) );
		$after_block  = ltrim( substr( $contents, $block_end ) );
		$contents     = $before_block . "\n" . $after_block;

		$wp_pos   = strpos( $contents, $wp_start );
		$contents = substr( $contents, 0, $wp_pos ) . $grabwp_block . "\n\n" . substr( $contents, $wp_pos );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $htaccess_file, $contents );
	}

	/**
	 * Create .htaccess file for data directory.
	 *
	 * @since 1.0.0
	 */
	private static function create_htaccess() {
		$grabwp_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		self::create_htaccess_for_directory( $grabwp_dir, 'GrabWP Tenancy Security Protection' );
	}

	/**
	 * Create index.php protection for data directory.
	 *
	 * @since 1.0.0
	 */
	private static function create_index_protection() {
		$grabwp_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();
		self::create_index_protection_for_directory( $grabwp_dir, 'GrabWP_Tenancy' );
	}

	/**
	 * Create necessary directories.
	 *
	 * @since 1.0.0
	 */
	private static function create_directories() {
		$grabwp_dir = GrabWP_Tenancy_Path_Manager::get_tenants_base_dir();

		if ( ! file_exists( $grabwp_dir ) ) {
			wp_mkdir_p( $grabwp_dir );
		}
	}

	/**
	 * Create default tenant mappings file.
	 *
	 * @since 1.0.0
	 */
	private static function create_tenant_mappings_file() {
		$mappings_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();

		if ( ! file_exists( $mappings_file ) ) {
			$content  = "<?php\n";
			$content .= "/**\n";
			$content .= " * Tenant Domain Mappings\n";
			$content .= " * Generated by GrabWP Tenancy Plugin\n";
			$content .= " * \n";
			$content .= " * This file contains domain mappings for tenant identification.\n";
			$content .= " * Format: \$tenant_mappings['tenant_id'] = array( 'domain1', 'domain2' );\n";
			$content .= " * \n";
			$content .= " * @package GrabWP_Tenancy\n";
			$content .= " */\n\n";
			$content .= "defined( 'ABSPATH' ) || exit; // Exit if accessed directly\n\n";
			$content .= "\$tenant_mappings = array(\n";
			$content .= "    // Example: 'abc123' => array( 'tenant1.grabwp.local' ),\n";
			$content .= ");\n";

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $mappings_file, $content );
		}
	}

	// =========================================================================
	// Filesystem Helpers
	// =========================================================================

	/**
	 * Check if a path is writable using WP_Filesystem.
	 *
	 * @since 1.3.1
	 * @param string $path Absolute path to check.
	 * @return bool
	 */
	private static function filesystem_is_writable( $path ) {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
		}

		return $wp_filesystem ? $wp_filesystem->is_writable( $path ) : false;
	}
}
