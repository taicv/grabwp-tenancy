<?php
/**
 * Clone Database Importer
 *
 * Imports a tenant's database.sql into the shared MySQL database with prefix replacement.
 * Simplified from Pro's GrabWP_Tenancy_Pro_Restore_Db_Importer — shared MySQL only,
 * no isolated MySQL or SQLite support.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database importer for tenant clone (shared MySQL only).
 */
class GrabWP_Tenancy_Clone_Db_Importer {

	/** Files above this size trigger extended time limit. */
	const LARGE_FILE_THRESHOLD = 5242880; // 5 MB.

	/** @var string Target tenant prefix. */
	private $dst_prefix = '';

	/**
	 * Import database.sql into the target tenant's shared MySQL tables.
	 *
	 * @param string $tenant_id Sanitized target tenant ID.
	 * @param string $sql_file  Absolute path to database.sql.
	 * @param array  $meta      Decoded metadata.json array.
	 * @return bool|WP_Error
	 */
	public function import( $tenant_id, $sql_file, $meta ) {
		$src_prefix       = $meta['tenant_prefix'] ?? ( $tenant_id . '_' );
		$this->dst_prefix = $tenant_id . '_';
		$this->maybe_extend_time_limit( $sql_file );
		return $this->import_shared( $tenant_id, $sql_file, $src_prefix );
	}

	/**
	 * Restore into shared MySQL database (drop existing tenant tables, re-import).
	 *
	 * @param string $tenant_id  Target tenant ID.
	 * @param string $sql_file   Absolute path to SQL file.
	 * @param string $src_prefix Original backup prefix.
	 * @return bool
	 */
	private function import_shared( $tenant_id, $sql_file, $src_prefix ) {
		global $wpdb;

		$dst_prefix = $this->dst_prefix;

		// Suppress errors during restore to avoid breaking JSON responses.
		$suppress = $wpdb->suppress_errors( true );

		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
		$wpdb->query( "SET SESSION sql_mode = ''" );

		// Drop existing tables for this tenant.
		$like   = $wpdb->esc_like( $dst_prefix );
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like . '%' ) );
		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}

		// Execute statements with prefix replacement.
		$errors = $this->execute_sql_file_mysql_streaming( $wpdb, $sql_file, $src_prefix, $dst_prefix );

		// Rewrite WP-internal prefix references in data values.
		$this->rewrite_wp_prefix_data( $wpdb, $src_prefix, $dst_prefix );

		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
		$wpdb->suppress_errors( $suppress );

		if ( ! empty( $errors ) ) {
			error_log( '[GrabWP Clone] Shared import completed with ' . count( $errors ) . ' SQL warning(s): ' . implode( ' | ', array_slice( $errors, 0, 10 ) ) );
		}
		return true;
	}

	/**
	 * Stream a SQL file line-by-line against a MySQL-compatible connection.
	 *
	 * @param wpdb   $db         Database connection.
	 * @param string $sql_file   Absolute path to SQL file.
	 * @param string $src_prefix Original backup prefix.
	 * @param string $dst_prefix Target tenant prefix.
	 * @return array List of SQL error messages.
	 */
	private function execute_sql_file_mysql_streaming( $db, $sql_file, $src_prefix, $dst_prefix ) {
		$handle = fopen( $sql_file, 'r' );
		if ( ! $handle ) {
			return [ 'Could not open SQL file for streaming: ' . $sql_file ];
		}

		$errors            = [];
		$current_statement = '';
		$max_packet        = $this->get_max_allowed_packet( $db );

		while ( ( $line = fgets( $handle ) ) !== false ) {
			$line = rtrim( $line, "\r\n" );

			if ( '' === $line || 0 === strpos( ltrim( $line ), '--' ) ) {
				continue;
			}

			$current_statement .= $line . "\n";

			if ( ';' === substr( rtrim( $line ), -1 ) ) {
				$statement = $this->replace_prefixes( $current_statement, $src_prefix, $dst_prefix );
				$statement = $this->replace_collations( $db, $statement );
				$statement = trim( $statement );

				if ( '' !== $statement ) {
					if ( strlen( $statement ) > $max_packet ) {
						error_log( '[GrabWP Clone] Skipping oversized statement (' . strlen( $statement ) . ' bytes > max_allowed_packet ' . $max_packet . ')' );
					} else {
						$db->query( $statement );
						if ( ! empty( $db->last_error ) ) {
							$errors[] = $db->last_error . ' [SQL: ' . substr( $statement, 0, 120 ) . ']';
						}
					}
				}

				$current_statement = '';
			}
		}

		// Execute any trailing statement without a final newline.
		if ( '' !== trim( $current_statement ) ) {
			$statement = $this->replace_prefixes( $current_statement, $src_prefix, $dst_prefix );
			$statement = $this->replace_collations( $db, $statement );
			$statement = trim( $statement );
			if ( '' !== $statement ) {
				if ( strlen( $statement ) <= $max_packet ) {
					$db->query( $statement );
					if ( ! empty( $db->last_error ) ) {
						$errors[] = $db->last_error . ' [SQL: ' . substr( $statement, 0, 120 ) . ']';
					}
				}
			}
		}

		fclose( $handle );
		return $errors;
	}

	/**
	 * Replace table-prefix occurrences inside backtick-quoted identifiers only.
	 *
	 * A blanket str_replace() would mangle data values that happen to contain
	 * the prefix string (e.g. meta_key "_wp_attached_file" when the source
	 * prefix is "wp_"). By anchoring the replacement to a leading backtick we
	 * limit changes to SQL identifiers (table / column names) produced by
	 * mysqldump, leaving literal data intact.
	 *
	 * @param string $sql        SQL content.
	 * @param string $src_prefix Original prefix.
	 * @param string $dst_prefix Target prefix.
	 * @return string
	 */
	private function replace_prefixes( $sql, $src_prefix, $dst_prefix ) {
		if ( $src_prefix === $dst_prefix ) {
			return $sql;
		}
		return preg_replace(
			'/`' . preg_quote( $src_prefix, '/' ) . '/',
			'`' . $dst_prefix,
			$sql
		);
	}

	/**
	 * Adjust collations for target server compatibility.
	 *
	 * @param wpdb   $db  Database connection.
	 * @param string $sql SQL statement.
	 * @return string Adjusted SQL.
	 */
	private function replace_collations( $db, $sql ) {
		if ( $db->has_cap( 'utf8mb4_520' ) ) {
			return str_replace( 'utf8mb4_0900_ai_ci', 'utf8mb4_unicode_520_ci', $sql );
		}
		if ( $db->has_cap( 'utf8mb4' ) ) {
			return str_replace(
				[ 'utf8mb4_0900_ai_ci', 'utf8mb4_unicode_520_ci' ],
				[ 'utf8mb4_unicode_ci', 'utf8mb4_unicode_ci' ],
				$sql
			);
		}
		return str_replace(
			[ 'utf8mb4_0900_ai_ci', 'utf8mb4_unicode_520_ci', 'utf8mb4' ],
			[ 'utf8_unicode_ci',    'utf8_unicode_ci',         'utf8' ],
			$sql
		);
	}

	/**
	 * Extend PHP time limit for large SQL files.
	 *
	 * @param string $sql_file Absolute path to the SQL file.
	 */
	private function maybe_extend_time_limit( $sql_file ) {
		if ( ! file_exists( $sql_file ) ) {
			return;
		}
		if ( filesize( $sql_file ) > 1048576 && function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}
	}

	/**
	 * Get MySQL max_allowed_packet value in bytes.
	 *
	 * @param wpdb $db Database connection.
	 * @return int
	 */
	private function get_max_allowed_packet( $db ) {
		$row = $db->get_row( "SHOW VARIABLES LIKE 'max_allowed_packet'", ARRAY_A );
		return isset( $row['Value'] ) ? (int) $row['Value'] : PHP_INT_MAX;
	}

	/**
	 * Rewrite source prefix references in known WordPress columns that embed
	 * the table prefix inside their values.
	 *
	 * WordPress stores the table prefix in exactly two places:
	 *   - {prefix}options.option_name  (e.g. wp_user_roles)
	 *   - {prefix}usermeta.meta_key    (e.g. wp_capabilities, wp_user_level)
	 *
	 * Scanning every text column would mangle core meta_keys such as
	 * _wp_attached_file whose "_wp_" prefix is NOT a table prefix.
	 *
	 * @param wpdb   $db         Database connection.
	 * @param string $src_prefix Original prefix.
	 * @param string $dst_prefix Target prefix.
	 */
	private function rewrite_wp_prefix_data( $db, $src_prefix, $dst_prefix ) {
		if ( $src_prefix === $dst_prefix ) {
			return;
		}

		// Map: table name => columns whose values embed the table prefix.
		$prefix_columns = [
			$dst_prefix . 'options'  => [ 'option_name' ],
			$dst_prefix . 'usermeta' => [ 'meta_key' ],
		];

		foreach ( $prefix_columns as $table => $columns ) {
			$exists = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) );
			if ( ! $exists ) {
				continue;
			}
			foreach ( $columns as $col_name ) {
				$db->query( $db->prepare(
					"UPDATE `{$table}` SET `{$col_name}` = REPLACE(`{$col_name}`, %s, %s) WHERE `{$col_name}` LIKE %s",
					$src_prefix,
					$dst_prefix,
					$db->esc_like( $src_prefix ) . '%'
				) );
			}
		}
	}
}
