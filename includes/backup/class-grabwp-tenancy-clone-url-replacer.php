<?php
/**
 * Clone URL Replacer
 *
 * Comprehensive search-and-replace of source domain URLs with target domain
 * across all tenant database tables. Handles plain text, PHP-serialized data,
 * and BeTheme Muffin Builder base64-encoded serialized data.
 *
 * Simplified from Pro's GrabWP_Tenancy_Pro_Clone_Url_Replacer — shared MySQL only.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL replacer for tenant clone (shared MySQL only).
 */
class GrabWP_Tenancy_Clone_Url_Replacer {

	/**
	 * Replace old URL with new URL across all tenant tables (shared MySQL).
	 *
	 * @param string $tenant_id Target tenant ID.
	 * @param string $old_url   Source site URL.
	 * @param string $new_url   Target site URL.
	 */
	public function replace( $tenant_id, $old_url, $new_url ) {
		if ( $old_url === $new_url || empty( $old_url ) ) {
			return;
		}

		global $wpdb;
		$prefix     = $tenant_id . '_';
		$old_domain = preg_replace( '#^https?://#', '', rtrim( $old_url, '/' ) );
		$new_domain = preg_replace( '#^https?://#', '', rtrim( $new_url, '/' ) );

		$this->replace_in_mysql( $wpdb, $prefix, $old_url, $new_url, $old_domain, $new_domain );
	}

	/**
	 * Read current siteurl from the target tenant's options table (shared MySQL).
	 *
	 * @param string $tenant_id Target tenant ID.
	 * @return string Current siteurl value or empty string.
	 */
	public function read_current_siteurl( $tenant_id ) {
		global $wpdb;
		$table = $tenant_id . '_options';
		return $wpdb->get_var( "SELECT option_value FROM `{$table}` WHERE option_name = 'siteurl'" ) ?: '';
	}

	/**
	 * Iterate all tenant tables in MySQL, replace URLs in text columns.
	 *
	 * @param wpdb   $db         Database connection.
	 * @param string $prefix     Tenant table prefix.
	 * @param string $old_url    Source URL.
	 * @param string $new_url    Target URL.
	 * @param string $old_domain Source domain (without protocol).
	 * @param string $new_domain Target domain (without protocol).
	 */
	private function replace_in_mysql( $db, $prefix, $old_url, $new_url, $old_domain, $new_domain ) {
		$tables = $db->get_col( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $prefix ) . '%' ) );

		foreach ( $tables as $table ) {
			$columns   = $db->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
			$pk        = null;
			$text_cols = [];

			foreach ( $columns as $col ) {
				if ( 'PRI' === $col['Key'] ) {
					$pk = $col['Field'];
				}
				if ( preg_match( '/^(varchar|text|longtext|mediumtext|tinytext|char)/i', $col['Type'] ) ) {
					$text_cols[] = $col['Field'];
				}
			}
			if ( ! $pk || empty( $text_cols ) ) {
				continue;
			}

			// Serialized-aware replacement for each text column.
			foreach ( $text_cols as $col_name ) {
				$rows = $db->get_results( $db->prepare(
					"SELECT `{$pk}`, `{$col_name}` FROM `{$table}` WHERE `{$col_name}` LIKE %s",
					'%' . $db->esc_like( $old_domain ) . '%'
				) );
				if ( empty( $rows ) ) {
					continue;
				}
				foreach ( $rows as $row ) {
					$original = $row->$col_name;
					$replaced = $this->replace_value( $original, $old_url, $new_url, $old_domain, $new_domain );
					if ( $replaced !== $original ) {
						$db->update( $table, [ $col_name => $replaced ], [ $pk => $row->$pk ] );
					}
				}
			}

			// BeTheme Muffin Builder: base64-encoded serialized (hidden from LIKE search).
			if ( 'postmeta' === substr( $table, -8 ) ) {
				$this->replace_muffin_builder_mysql( $db, $table, $old_url, $new_url, $old_domain, $new_domain );
			}
		}
	}

	/**
	 * BeTheme mfn-page-items: base64+serialized data not visible to LIKE searches.
	 *
	 * @param wpdb   $db         Database connection.
	 * @param string $table      Postmeta table name.
	 * @param string $old_url    Source URL.
	 * @param string $new_url    Target URL.
	 * @param string $old_domain Source domain.
	 * @param string $new_domain Target domain.
	 */
	private function replace_muffin_builder_mysql( $db, $table, $old_url, $new_url, $old_domain, $new_domain ) {
		$results = $db->get_results(
			"SELECT meta_id, post_id, meta_value FROM `{$table}` WHERE meta_key = 'mfn-page-items'"
		);
		if ( empty( $results ) ) {
			return;
		}

		foreach ( $results as $row ) {
			try {
				$raw        = $row->meta_value;
				$data       = @unserialize( $raw );
				$was_base64 = false;

				// Builder 2.0+: base64-encoded serialized.
				if ( false === $data ) {
					$decoded = base64_decode( $raw, true );
					if ( false !== $decoded ) {
						$data       = @unserialize( $decoded );
						$was_base64 = ( false !== $data );
					}
				}
				if ( false === $data || ! is_array( $data ) ) {
					continue;
				}

				$skip_url = ( $old_domain !== $new_domain && false !== strpos( $new_domain, $old_domain ) );
				if ( $old_domain !== $new_domain ) {
					$data = $this->recursive_replace( $data, $old_domain, $new_domain );
				}
				if ( ! $skip_url ) {
					$data = $this->recursive_replace( $data, $old_url, $new_url );
				}

				$new_meta = $was_base64 ? base64_encode( serialize( $data ) ) : serialize( $data );
				if ( $new_meta !== $raw ) {
					$db->update( $table, [ 'meta_value' => $new_meta ], [ 'meta_id' => $row->meta_id ] );
					$db->delete( $table, [ 'post_id' => $row->post_id, 'meta_key' => 'mfn-page-object' ] );
				}
			} catch ( \Error $e ) {
				error_log( '[GrabWP Clone] Muffin Builder replace error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Replace URLs in a single value, handling PHP-serialized data.
	 *
	 * @param string $value      Raw value.
	 * @param string $old_url    Source URL.
	 * @param string $new_url    Target URL.
	 * @param string $old_domain Source domain.
	 * @param string $new_domain Target domain.
	 * @return string
	 */
	private function replace_value( $value, $old_url, $new_url, $old_domain, $new_domain ) {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return $value;
		}

		// When old_domain is a substring of new_domain (e.g. subdomain cloning
		// base.dev → tenant.base.dev, or upload path rewrite), domain replacement
		// alone covers all cases. Running URL replacement after would double-replace
		// because str_replace finds old_domain inside the already-replaced new_domain.
		$skip_url_replace = ( $old_domain !== $new_domain && false !== strpos( $new_domain, $old_domain ) );

		if ( is_serialized( $value ) ) {
			$data = @unserialize( $value );
			if ( false !== $data ) {
				if ( $old_domain !== $new_domain ) {
					$data = $this->recursive_replace( $data, $old_domain, $new_domain );
				}
				if ( ! $skip_url_replace ) {
					$data = $this->recursive_replace( $data, $old_url, $new_url );
				}
				return serialize( $data );
			}
		}

		if ( $old_domain !== $new_domain ) {
			$value = str_replace( $old_domain, $new_domain, $value );
		}
		if ( ! $skip_url_replace ) {
			$value = str_replace( $old_url, $new_url, $value );
		}
		return $value;
	}

	/**
	 * Recursively replace strings in arrays, objects, and nested serialized data.
	 *
	 * @param mixed  $data    Data to process.
	 * @param string $search  Search string.
	 * @param string $replace Replacement string.
	 * @return mixed
	 */
	private function recursive_replace( $data, $search, $replace ) {
		if ( is_string( $data ) ) {
			return str_replace( $search, $replace, $data );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->recursive_replace( $value, $search, $replace );
			}
		} elseif ( is_object( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data->$key = $this->recursive_replace( $value, $search, $replace );
			}
		}
		return $data;
	}
}
