<?php
/**
 * GrabWP Tenancy List Table Class
 *
 * Handles the display of tenants in a WordPress admin list table with pagination.
 *
 * @package GrabWP_Tenancy
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * GrabWP Tenancy List Table Class
 *
 * @since 1.0.0
 */
class GrabWP_Tenancy_List_Table extends WP_List_Table {

	/**
	 * Plugin instance
	 *
	 * @var GrabWP_Tenancy
	 */
	private $plugin;

	/**
	 * Constructor
	 *
	 * @param GrabWP_Tenancy $plugin Plugin instance
	 */
	public function __construct( $plugin ) {
		parent::__construct(
			array(
				'singular' => 'tenant',
				'plural'   => 'tenants',
				'ajax'     => false,
			)
		);

		$this->plugin = $plugin;
		$this->add_screen_options();
	}

	/**
	 * Add screen options for items per page
	 */
	private function add_screen_options() {
		$screen = get_current_screen();
		if ( $screen && 'toplevel_page_grabwp-tenancy' === $screen->id ) {
			add_screen_option(
				'per_page',
				array(
					'label'   => __( 'Tenants per page', 'grabwp-tenancy' ),
					'default' => 20,
					'option'  => 'tenants_per_page',
				)
			);
		}
	}

	/**
	 * Get columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'tenant_id' => __( 'Tenant ID', 'grabwp-tenancy' ),
			'domains'   => __( 'Domains', 'grabwp-tenancy' ),
			'actions'   => __( 'Actions', 'grabwp-tenancy' ),
		);

		/**
		 * Filter list table columns
		 *
		 * Allows pro plugin and other extensions to add custom columns
		 *
		 * @since 1.0.4
		 * @param array $columns Array of column definitions
		 */
		return apply_filters( 'grabwp_tenancy_list_table_columns', $columns );
	}

	/**
	 * Get sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable = array(
			'tenant_id' => array( 'tenant_id', false ),
			//'status'    => array( 'status', false ),
			'domains'   => array( 'domains', false ),
		);

		/**
		 * Filter sortable columns
		 *
		 * Allows pro plugin and other extensions to add custom sortable columns
		 *
		 * @since 1.0.4
		 * @param array $sortable Array of sortable column definitions
		 */
		return apply_filters( 'grabwp_tenancy_list_table_sortable_columns', $sortable );
	}

	/**
	 * Get bulk actions
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array();

		/**
		 * Filter bulk actions for tenants
		 *
		 * Allows pro plugin and other extensions to add custom bulk actions
		 *
		 * @since 1.0.4
		 * @param array $actions Array of bulk actions
		 */
		return apply_filters( 'grabwp_tenancy_bulk_actions', $actions );
	}

	/**
	 * Get hidden columns
	 *
	 * @return array
	 */
	public function get_hidden_columns() {
		return array();
	}

	/**
	 * Prepare items for display
	 */
	public function prepare_items() {
		// Get columns
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Get pagination parameters
		$per_page = $this->get_items_per_page( 'tenants_per_page', 20 );
		$current_page = $this->get_pagenum();

		// Get search term
		$search_term = $this->get_search_term();

		// Get raw tenant mappings data (more efficient)
		$tenant_mappings = $this->get_tenant_mappings();
		
		if ( empty( $tenant_mappings ) ) {
			$this->items = array();
			$this->set_pagination_args(
				array(
					'total_items' => 0,
					'per_page'    => $per_page,
					'total_pages' => 0,
				)
			);
			return;
		}

		// Reverse for newest first (like original behavior)
		$tenant_mappings = array_reverse( $tenant_mappings, true );

		// Apply search filter on raw data (more efficient)
		if ( ! empty( $search_term ) ) {
			$tenant_mappings = $this->filter_mappings_by_search( $tenant_mappings, $search_term );
		}

		// Apply sorting
		$tenant_mappings = $this->sort_mappings( $tenant_mappings );

		$total_items = count( $tenant_mappings );

		// Calculate pagination
		$start = ( $current_page - 1 ) * $per_page;

		// Get only the slice we need for current page
		$current_page_mappings = array_slice( $tenant_mappings, $start, $per_page, true );

		// Create tenant objects only for current page items
		$this->items = array();
		foreach ( $current_page_mappings as $tenant_id => $domains ) {
			$tenant = new GrabWP_Tenancy_Tenant(
				$tenant_id,
				array(
					'domains' => $domains,
				)
			);
			$this->items[] = $tenant;
		}

		// Set pagination args
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Get raw tenant mappings data
	 *
	 * @return array Raw tenant mappings array
	 */
	private function get_tenant_mappings() {
		$mappings_file = GrabWP_Tenancy_Path_Manager::get_tenants_file_path();

		if ( file_exists( $mappings_file ) && is_readable( $mappings_file ) ) {
			// Clear any file system cache
			clearstatcache( true, $mappings_file );

			// Create a safe execution environment
			$tenant_mappings = array();

			// Use include instead of eval for safer execution
			ob_start();
			include $mappings_file;
			ob_end_clean();

			if ( is_array( $tenant_mappings ) ) {
				return $tenant_mappings;
			}
		}

		return array();
	}

	/**
	 * Get tenants data (optimized for pagination)
	 *
	 * @return array
	 */
	private function get_tenants() {
		// Get raw mappings data
		$tenant_mappings = $this->get_tenant_mappings();
		
		if ( empty( $tenant_mappings ) ) {
			return array();
		}

		// Convert to tenant objects only for current page
		$tenants = array();
		foreach ( array_reverse( $tenant_mappings, true ) as $tenant_id => $domains ) {
			$tenant = new GrabWP_Tenancy_Tenant(
				$tenant_id,
				array(
					'domains' => $domains,
				)
			);
			$tenants[] = $tenant;
		}

		return $tenants;
	}

	/**
	 * Display tenant ID column
	 *
	 * @param object $item Tenant object
	 * @return string
	 */
	public function column_tenant_id( $item ) {
		return '<code>' . esc_html( $item->get_id() ) . '</code>';
	}



	/**
	 * Display domains column
	 *
	 * @param object $item Tenant object
	 * @return string
	 */
	public function column_domains( $item ) {
		$domains = $item->get_domains();
		$output  = '';

		if ( ! empty( $domains ) ) {
			foreach ( $domains as $domain ) {
				$output .= '<code style="margin: 2px; padding: 2px 4px; background: #f0f0f0;">' . esc_html( $domain ) . '</code>';
			}
		} else {
			$output = '<em>' . esc_html__( 'No domains assigned', 'grabwp-tenancy' ) . '</em>';
		}

		return $output;
	}

	/**
	 * Display actions column
	 *
	 * @param object $item Tenant object
	 * @return string
	 */
	public function column_actions( $item ) {
		$actions = array();

		// Visit Site button
		$domains = $item->get_domains();
		if ( ! empty( $domains ) ) {
			$site_url = ( is_ssl() ? 'https://' : 'http://' ) . $domains[0];
			$actions[] = '<a href="' . esc_url( $site_url ) . '" target="_blank" class="button button-primary">' . esc_html__( 'Visit Site', 'grabwp-tenancy' ) . '</a>';

			// Visit Admin button
			$admin_url = null;
			if ( method_exists( $item, 'get_admin_access_url' ) ) {
				$admin_url = $item->get_admin_access_url();
			}
			if ( $admin_url ) {
				$actions[] = '<a href="' . esc_url( $admin_url ) . '" target="_blank" class="button">' . esc_html__( 'Visit Admin', 'grabwp-tenancy' ) . '</a>';
			} else {
				$admin_url = ( is_ssl() ? 'https://' : 'http://' ) . $domains[0] . '/wp-admin/';
				$actions[] = '<a href="' . esc_url( $admin_url ) . '" target="_blank" class="button">' . esc_html__( 'Visit Admin', 'grabwp-tenancy' ) . '</a>';
			}
		}

		// Edit button
		$edit_url = admin_url( 'admin.php?page=grabwp-tenancy-edit&tenant_id=' . urlencode( $item->get_id() ) . '&_wpnonce=' . urlencode( wp_create_nonce( 'grabwp_tenancy_edit' ) ) );
		$actions[] = '<a href="' . esc_url( $edit_url ) . '" class="button">' . esc_html__( 'Edit', 'grabwp-tenancy' ) . '</a>';

		// Delete button (POST form to avoid destructive GET requests).
		$delete_form  = '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=grabwp-tenancy' ) ) . '" style="display:inline">';
		$delete_form .= wp_nonce_field( 'grabwp_tenancy_delete', '_wpnonce', true, false );
		$delete_form .= '<input type="hidden" name="action" value="delete_tenant" />';
		$delete_form .= '<input type="hidden" name="tenant_id" value="' . esc_attr( $item->get_id() ) . '" />';
		$delete_form .= '<button type="submit" class="button button-link-delete" onclick="return grabwpTenancyConfirmDelete(\'' . esc_js( $item->get_id() ) . '\')">' . esc_html__( 'Delete', 'grabwp-tenancy' ) . '</button>';
		$delete_form .= '</form>';
		$actions[]    = $delete_form;

		/**
		 * Filter tenant row actions
		 *
		 * Allows pro plugin and other extensions to add custom actions
		 *
		 * @since 1.0.4
		 * @param array  $actions Array of action HTML strings
		 * @param object $item    Tenant object
		 */
		$actions = apply_filters( 'grabwp_tenancy_tenant_row_actions', $actions, $item );

		return implode( ' ', $actions );
	}

	/**
	 * Default column display
	 *
	 * @param object $item Tenant object
	 * @param string $column_name Column name
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		/**
		 * Filter column content for custom columns
		 *
		 * Allows pro plugin and other extensions to display custom column content
		 *
		 * @since 1.0.4
		 * @param string $content Column content
		 * @param object $item Tenant object
		 * @param string $column_name Column name
		 */
		return apply_filters( 'grabwp_tenancy_list_table_column_content', '', $item, $column_name );
	}

	/**
	 * Display when no items found
	 */
	public function no_items() {
		$search_term = $this->get_search_term();
		if ( ! empty( $search_term ) ) {
			printf(
				/* translators: %s: search term */
				esc_html__( 'No tenants found matching "%s".', 'grabwp-tenancy' ),
				esc_html( $search_term )
			);
		} else {
			esc_html_e( 'No tenants found.', 'grabwp-tenancy' );
		}
	}

	/**
	 * Get search term from request
	 *
	 * @return string
	 */
	private function get_search_term() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search is read-only operation
		return isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
	}

	/**
	 * Sort tenant mappings based on current sort order
	 *
	 * @param array $tenant_mappings Array of tenant mappings
	 * @return array Sorted tenant mappings
	 */
	private function sort_mappings( $tenant_mappings ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sorting is read-only operation
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sorting is read-only operation
		$order = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'desc';

		if ( empty( $orderby ) || ! in_array( $orderby, array( 'tenant_id', 'status', 'domains' ), true ) ) {
			return $tenant_mappings;
		}

		$order = ( 'asc' === $order ) ? SORT_ASC : SORT_DESC;

		// Prepare sort arrays
		$sort_values = array();
		foreach ( $tenant_mappings as $tenant_id => $domains ) {
			if ( 'tenant_id' === $orderby ) {
				$sort_values[ $tenant_id ] = $tenant_id;
			} elseif ( 'status' === $orderby ) {
				// Create a temporary tenant object to get status
				$temp_tenant = new GrabWP_Tenancy_Tenant( $tenant_id, array( 'domains' => $domains ) );
				$sort_values[ $tenant_id ] = $temp_tenant->get_status();
			} elseif ( 'domains' === $orderby ) {
				// Sort by primary domain (first domain in the array)
				$primary_domain = is_array( $domains ) && ! empty( $domains ) ? $domains[0] : '';
				$sort_values[ $tenant_id ] = strtolower( $primary_domain );
			}
		}

		// Sort the mappings
		if ( SORT_ASC === $order ) {
			asort( $sort_values );
		} else {
			arsort( $sort_values );
		}

		// Rebuild mappings array in sorted order
		$sorted_mappings = array();
		foreach ( $sort_values as $tenant_id => $sort_value ) {
			$sorted_mappings[ $tenant_id ] = $tenant_mappings[ $tenant_id ];
		}

		return $sorted_mappings;
	}

	/**
	 * Filter tenant mappings by search term (optimized for raw data)
	 *
	 * @param array  $tenant_mappings Array of raw tenant mappings
	 * @param string $search_term Search term
	 * @return array Filtered tenant mappings
	 */
	private function filter_mappings_by_search( $tenant_mappings, $search_term ) {
		$filtered_mappings = array();
		$search_term_lower = strtolower( $search_term );

		foreach ( $tenant_mappings as $tenant_id => $domains ) {
			$match = false;

			// Search by tenant ID (exact match)
			if ( strtolower( $tenant_id ) === $search_term_lower ) {
				$match = true;
			}

			// Search by domain (partial match)
			if ( ! $match && is_array( $domains ) ) {
				foreach ( $domains as $domain ) {
					if ( false !== strpos( strtolower( $domain ), $search_term_lower ) ) {
						$match = true;
						break;
					}
				}
			}

			if ( $match ) {
				$filtered_mappings[ $tenant_id ] = $domains;
			}
		}

		return $filtered_mappings;
	}

	/**
	 * Filter tenants by search term (legacy method for backward compatibility)
	 *
	 * @param array  $tenants Array of tenant objects
	 * @param string $search_term Search term
	 * @return array Filtered tenants
	 */
	private function filter_tenants_by_search( $tenants, $search_term ) {
		$filtered_tenants = array();
		$search_term_lower = strtolower( $search_term );

		foreach ( $tenants as $tenant ) {
			$match = false;

			// Search by tenant ID (exact match)
			if ( strtolower( $tenant->get_id() ) === $search_term_lower ) {
				$match = true;
			}

			// Search by domain (partial match)
			if ( ! $match ) {
				$domains = $tenant->get_domains();
				foreach ( $domains as $domain ) {
					if ( false !== strpos( strtolower( $domain ), $search_term_lower ) ) {
						$match = true;
						break;
					}
				}
			}

			if ( $match ) {
				$filtered_tenants[] = $tenant;
			}
		}

		return $filtered_tenants;
	}

	/**
	 * Display extra tablenav (search box in bulk actions area)
	 *
	 * @param string $which Which tablenav (top or bottom)
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			?>
			<div class="alignleft actions">
				<form method="get">
					<input type="hidden" name="page" value="grabwp-tenancy" />
					<label for="tenant-search-input" class="screen-reader-text"><?php esc_html_e( 'Search Tenants', 'grabwp-tenancy' ); ?>:</label>
					<input type="search" id="tenant-search-input" name="s" value="<?php _admin_search_query(); ?>" placeholder="<?php esc_attr_e( 'Search by ID or domain...', 'grabwp-tenancy' ); ?>" />
					<?php submit_button( __( 'Search', 'grabwp-tenancy' ), '', '', false, array( 'id' => 'search-submit' ) ); ?>
				</form>
			</div>
			<?php
		}
	}

}
