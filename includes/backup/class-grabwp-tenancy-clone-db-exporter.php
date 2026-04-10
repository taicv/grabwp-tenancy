<?php
/**
 * Clone Database Exporter
 *
 * Exports a tenant's shared MySQL database to database.sql + metadata.json.
 * Simplified from Pro's GrabWP_Tenancy_Pro_Backup_Db_Exporter — shared MySQL only,
 * no isolated MySQL or SQLite support.
 *
 * @package GrabWP_Tenancy
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database exporter for tenant clone (shared MySQL only).
 */
class GrabWP_Tenancy_Clone_Db_Exporter {

	/** Rows fetched per SELECT chunk (avoids OOM on large tables). */
	const EXPORT_CHUNK_SIZE = 500;

	/** INSERTs wrapped in a single transaction batch. */
	const EXPORT_TX_BATCH = 100;

	/**
	 * Export tenant database to $tmp_dir/database.sql and $tmp_dir/metadata.json.
	 *
	 * @param string $tenant_id Sanitized tenant ID.
	 * @param string $tmp_dir   Absolute path to the staging directory.
	 * @return bool|WP_Error
	 */
	public function export( $tenant_id, $tmp_dir ) {
		$sql_file = $tmp_dir . '/database.sql';
		global $wpdb;

		// Mainsite: use $wpdb->prefix (e.g. wp_).
		if ( defined( 'GRABWP_MAINSITE_ID' ) && GRABWP_MAINSITE_ID === $tenant_id ) {
			$tenant_prefix = $wpdb->prefix;
		} else {
			$tenant_prefix = $tenant_id . '_';
		}

		$result = $this->export_shared( $tenant_id, $sql_file, $tenant_prefix );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$meta = [
			'db_type'       => 'shared',
			'tenant_prefix' => $tenant_prefix,
			'created_at'    => gmdate( 'Y-m-d H:i:s' ),
			'tenant_id'     => $tenant_id,
		];
		file_put_contents( $tmp_dir . '/metadata.json', wp_json_encode( $meta, JSON_PRETTY_PRINT ) );
		return true;
	}

	/**
	 * Export shared MySQL tables matching {tenant_prefix}%.
	 *
	 * @param string $tenant_id      Tenant ID.
	 * @param string $sql_file       Absolute path to output SQL file.
	 * @param string $tenant_prefix  Table prefix to match.
	 * @return bool|WP_Error
	 */
	private function export_shared( $tenant_id, $sql_file, $tenant_prefix = '' ) {
		global $wpdb;
		$effective_prefix = ! empty( $tenant_prefix ) ? $tenant_prefix : $tenant_id . '_';
		$prefix           = $wpdb->esc_like( $effective_prefix );
		$tables           = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . '%' ) );

		if ( empty( $tables ) ) {
			file_put_contents( $sql_file, "-- No tables found for tenant {$tenant_id}\n" );
			return true;
		}

		$fh = fopen( $sql_file, 'w' );
		if ( ! $fh ) {
			return new WP_Error( 'file_open', __( 'Cannot open SQL output file.', 'grabwp-tenancy' ) );
		}

		// Disable strict sql_mode so INSERTs are more permissive on import.
		$wpdb->query( "SET SESSION sql_mode = ''" );

		foreach ( $tables as $table ) {
			$this->dump_mysql_table( $wpdb, $table, $fh );
		}
		fclose( $fh );
		return true;
	}

	/**
	 * Write CREATE TABLE + INSERT statements for a MySQL table to file handle.
	 *
	 * Uses chunked SELECT (LIMIT/OFFSET) to avoid OOM on large tables.
	 * Wraps every EXPORT_TX_BATCH INSERTs in a START TRANSACTION / COMMIT pair.
	 *
	 * @param wpdb     $db    Database connection.
	 * @param string   $table Table name.
	 * @param resource $fh    Open file handle.
	 */
	private function dump_mysql_table( $db, $table, $fh ) {
		fwrite( $fh, "\n-- Table: `{$table}`\n" );
		fwrite( $fh, "DROP TABLE IF EXISTS `{$table}`;\n" );

		$create = $db->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
		if ( $create && isset( $create[1] ) ) {
			fwrite( $fh, $create[1] . ";\n\n" );
		}

		$col_types = $this->get_column_types( $db, $table );

		$offset    = 0;
		$row_count = 0;
		do {
			$rows = $db->get_results(
				$db->prepare( 'SELECT * FROM `' . $table . '` LIMIT %d OFFSET %d', self::EXPORT_CHUNK_SIZE, $offset ),
				ARRAY_A
			);
			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				if ( 0 === $row_count % self::EXPORT_TX_BATCH ) {
					fwrite( $fh, "START TRANSACTION;\n" );
				}

				$cols   = '`' . implode( '`, `', array_keys( $row ) ) . '`';
				$values = implode( ', ', array_map(
					function ( $k, $v ) use ( $col_types ) {
						return $this->format_value( $v, $col_types[ strtolower( $k ) ] ?? 'text' );
					},
					array_keys( $row ),
					array_values( $row )
				) );
				fwrite( $fh, "INSERT INTO `{$table}` ({$cols}) VALUES ({$values});\n" );

				$row_count++;

				if ( 0 === $row_count % self::EXPORT_TX_BATCH ) {
					fwrite( $fh, "COMMIT;\n" );
				}
			}

			$offset += self::EXPORT_CHUNK_SIZE;
		} while ( count( $rows ) === self::EXPORT_CHUNK_SIZE );

		// Close any open transaction from the last partial batch.
		if ( $row_count > 0 && 0 !== $row_count % self::EXPORT_TX_BATCH ) {
			fwrite( $fh, "COMMIT;\n" );
		}
	}

	/**
	 * Get column name -> type map for a table.
	 *
	 * @param wpdb   $db    Database connection.
	 * @param string $table Table name.
	 * @return array Lowercase column name -> lowercase MySQL type string.
	 */
	private function get_column_types( $db, $table ) {
		$columns = $db->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
		$types   = [];
		foreach ( $columns as $col ) {
			$types[ strtolower( $col['Field'] ) ] = strtolower( $col['Type'] );
		}
		return $types;
	}

	/**
	 * Format a column value as a SQL literal based on its MySQL type.
	 *
	 * Uses raw MySQL string escaping instead of esc_sql() / $wpdb->prepare()
	 * to avoid WordPress placeholder injection corrupting '%' in serialized data.
	 *
	 * @param mixed  $value Column value (may be null).
	 * @param string $type  Lowercase MySQL type string.
	 * @return string SQL-safe literal.
	 */
	private function format_value( $value, $type ) {
		if ( is_null( $value ) ) {
			return 'NULL';
		}

		$numeric_prefixes = [ 'tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double', 'decimal', 'numeric', 'real', 'bit' ];
		foreach ( $numeric_prefixes as $prefix ) {
			if ( 0 === strpos( $type, $prefix ) ) {
				return (string) $value;
			}
		}

		$binary_prefixes = [ 'binary', 'varbinary', 'tinyblob', 'mediumblob', 'longblob', 'blob' ];
		foreach ( $binary_prefixes as $prefix ) {
			if ( 0 === strpos( $type, $prefix ) ) {
				return '0x' . bin2hex( $value );
			}
		}

		return "'" . $this->escape_string_for_sql( $value ) . "'";
	}

	/**
	 * Escape a string value for safe embedding in a MySQL single-quoted string literal.
	 *
	 * @param string $value Raw string value from the database.
	 * @return string Escaped string (without surrounding quotes).
	 */
	private function escape_string_for_sql( $value ) {
		$search  = [ "\\",    "\0",   "\n",   "\r",   "\x1a", "'"    ];
		$replace = [ '\\\\',  '\\0',  '\\n',  '\\r',  '\\Z',  "\\'"  ];
		return str_replace( $search, $replace, $value );
	}
}
