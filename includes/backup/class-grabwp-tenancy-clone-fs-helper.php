<?php
/**
 * Clone Filesystem Helper
 *
 * Static utility methods for recursive directory copy and removal.
 * Simplified from Pro's GrabWP_Tenancy_Pro_Backup_Fs_Helper — no zip support needed for clone.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static filesystem helpers for clone operations.
 */
class GrabWP_Tenancy_Clone_Fs_Helper {

	/**
	 * Recursively copy $src directory into $dst.
	 *
	 * @param string $src          Absolute source directory path.
	 * @param string $dst          Absolute destination directory path.
	 * @param array  $exclude_dirs Absolute paths to skip during recursion.
	 * @return bool True on success.
	 */
	public static function recurse_copy( $src, $dst, $exclude_dirs = [] ) {
		if ( ! is_dir( $src ) ) {
			return false;
		}
		wp_mkdir_p( $dst );
		$dir = opendir( $src );
		if ( ! $dir ) {
			return false;
		}
		while ( false !== ( $entry = readdir( $dir ) ) ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$src_path = $src . '/' . $entry;
			$dst_path = $dst . '/' . $entry;
			if ( is_dir( $src_path ) ) {
				// Skip excluded directories (e.g. tenancy data dirs inside uploads).
				$real = realpath( $src_path );
				if ( $real && in_array( $real, $exclude_dirs, true ) ) {
					continue;
				}
				self::recurse_copy( $src_path, $dst_path, $exclude_dirs );
			} else {
				copy( $src_path, $dst_path );
			}
		}
		closedir( $dir );
		return true;
	}

	/**
	 * Recursively delete $dir and all its contents.
	 *
	 * @param string $dir Absolute directory path.
	 */
	public static function remove_dir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $entry ) {
			$entry->isDir() ? rmdir( $entry->getRealPath() ) : unlink( $entry->getRealPath() );
		}
		rmdir( $dir );
	}
}
