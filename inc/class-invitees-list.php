<?php
/**
 * Invitees_List Class
 *
 * Child class of WP_List_Table located in /wp-admin/includes/class-wp-list-table.php
 *
 */

class Invitees_List extends WP_List_Table {

	/** Class constructor */
	public function __construct() {

		parent::__construct( [
			'singular' => __( 'Invitee', 'temporal' ), //singular name of the listed records
			'plural'   => __( 'Invitees', 'temporal' ), //plural name of the listed records
			'ajax'     => false //does this table support ajax?
		] );

	}

	/**
	 * Retrieve invitees data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public static function get_invitees( $per_page = 5, $page_number = 1 ) {

		global $wpdb;

		$sql = "SELECT * FROM {$wpdb->prefix}temporal";

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;

		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}


	/**
	 * Delete a invitee record.
	 *
	 * @param int $id invitee ID
	 */
	public static function delete_invitee( $id ) {
		global $wpdb;

		$wpdb->delete(
			"{$wpdb->prefix}temporal",
			[ 'id' => $id ],
			[ '%d' ]
		);
	}


	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count() {
		global $wpdb;

		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}temporal";

		return $wpdb->get_var( $sql );
	}


	/** Text displayed when no invitee data is available */
	public function no_items() {
		_e( 'No invitees avaliable.', 'temporal' );
	}


	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {

      case 'username':
        return $item[ $column_name ];
	    case 'viewed':
	      if (boolval($item[ $column_name ])) {
	        $datetime = $item[ 'timestamp' ];//strtotime('M DD, YY at g:i A', $item[ 'timestamp' ]);
	        return $datetime;
	      }
	      else {
	      	return 'Not viewed';
	      }
	    case 'actions':
	      return '<button class="temporal-admin__show" type="button">Show Login Info</button>';
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
		);
	}

	/**
   * Method for actions column
   *
   * @param array $item an array of DB data
   *
   * @return string
   */
  function column_full_name( $item ) {

    $delete_nonce = wp_create_nonce( 'temporal_delete_invitee' );

    $title = '<strong>' . $item['last_name'] . ', ' . $item['first_name'] . '</strong>';
    //$actions = sprintf( '<a href="?page=%s&action=%s&gate=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce );
    
    /*
    $actions = [
      'delete' => sprintf( '<a href="?page=%s&action=%s&invitee=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce )
    ];
    */

    return $title;// . $this->row_actions( $actions );
  }

	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = [
			'cb'         => '<input type="checkbox" />',
			'full_name'  => __( 'First Name', 'temporal' ),
			//'first_name' => __( 'First Name', 'temporal' ),
			//'last_name'  => __( 'Last Name', 'temporal' ),
			'username'      => __( 'Username', 'temporal' ),
			'viewed'     => __( 'Viewed', 'temporal' ),
			'actions'    => __( 'Actions', 'temporal' )
		];

		return $columns;
	}


	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'first_name' => array( 'first_name', true ),
			'last_name'  => array( 'last_name', true ),
      'username'      => array( 'username', true ),
      'viewed'     => array( 'viewed', true )
		);

		return $sortable_columns;
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = [
			'bulk-delete' => 'Delete'
		];

		return $actions;
	}


	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		/** Process bulk action */
		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'invitees_per_page', 5 );
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		] );

		$this->items = self::get_invitees( $per_page, $current_page );

	}

	public function process_bulk_action() {

		//Detect when a bulk action is being triggered...
		if ( 'delete' === $this->current_action() ) {

			// In our file that handles the request, verify the nonce.
			$nonce = esc_attr( $_REQUEST['_wpnonce'] );

			if ( ! wp_verify_nonce( $nonce, 'temporal_delete_invitee' ) ) {
				die( 'Go get a life script kiddies' );
			}
			else {
				self::delete_invitee( absint( $_GET['invitee'] ) );

        // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
        // add_query_arg() return the current url
        wp_redirect( esc_url_raw(add_query_arg()) );
				exit;
			}

		}

		// If the delete bulk action is triggered
		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
		     || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
		) {

			$delete_ids = esc_sql( $_POST['bulk-delete'] );

			// loop over the array of record IDs and delete them
			foreach ( $delete_ids as $id ) {
				self::delete_invitee( $id );
			}

			// esc_url_raw() is used to prevent converting ampersand in url to "#038;"
      // add_query_arg() return the current url
      wp_redirect( esc_url_raw(add_query_arg()) );
			exit;
		}
	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @param object $item The current item
	 */
	public function single_row( $item ) {

		global $wpdb;

		// Get the number of columns
		$colspan = count($this->get_columns());
		$table_gates = EV_TABLE_GATES_NAME;
		$gate = $item['gate'];

		// Query wp_temporal_gates for welcome_pid
    $query = "SELECT * FROM $table_gates WHERE name = '$gate'";
    $row   = $wpdb->get_row($query, ARRAY_A);
    $pid   = $row['pids'];
    $welcome_pid = $row['welcome_pid'];

		echo '<tr>';
		$this->single_row_columns( $item );
		echo '</tr>';
		echo '<tr class="temporal-username-row">';
		echo   '<td colspan="' . $colspan . '">';
		echo     "<textarea>";
		echo     "Hi " . $item['first_name'] . ",\n\n";
		echo     "Here is your login info:\n\n";
		echo     "Username: " . $item['username'] . "\n";
		echo     "Password: " . $item['pass'] . "\n";
		echo     "Link: " . get_the_permalink($welcome_pid) . "?temporal-pid=" . $pid . "\n\n";
		echo     "</textarea>";
		echo   '</td>';
		echo '</tr>';
	}
}