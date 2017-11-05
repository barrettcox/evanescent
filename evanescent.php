<?php

/*
Plugin Name: Evanescent
Plugin URI:
Description: Provides time sensitive access to WordPress pages
Version: 0.1.0
Author: Barrett Cox
Author URI:  http://barrettcox.com
*/

// Plugin config
require_once 'config.php';

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

// Invitees_List class
require_once 'inc/class-invitees-list.php';
require_once 'inc/class-gates-list.php';


function evanescent_install() {

  global $wpdb;

  $table = EV_TABLE_NAME; 
  $table_gates = EV_TABLE_GATES_NAME;

  $charset_collate = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE $table (
         `id` mediumint(9) NOT NULL AUTO_INCREMENT,
         `timestamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
         `email` varchar(254) NOT NULL DEFAULT '',
         `first_name` varchar(255) NOT NULL DEFAULT '',
         `last_name` varchar(255) NOT NULL DEFAULT '',
         `pass` varchar(8) NOT NULL DEFAULT '',
         `gate` varchar(50) NOT NULL DEFAULT '',
         `viewed` tinyint(1) NOT NULL DEFAULT 0,
         `sent` tinyint(1) NOT NULL DEFAULT 0,
         `expired` tinyint(1) NOT NULL DEFAULT 0,
         PRIMARY KEY  (`id`)
         ) $charset_collate;";

  $sql_pages = "CREATE TABLE $table_gates (
               `id` mediumint(9) NOT NULL AUTO_INCREMENT,
               `name` varchar(50) NOT NULL DEFAULT '',
               `pids` varchar(255) NOT NULL DEFAULT '',
               `welcome_pid` int NOT NULL DEFAULT 0,
               PRIMARY KEY  (`id`)
               ) $charset_collate;";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

  dbDelta($sql);
  dbDelta($sql_pages);
}

/*
function evanescent_uninstall() {

  global $wpdb;

  // MySQL table name
  $table = EV_TABLE_NAME;

  $sql = "DROP TABLE IF EXISTS $table";

  $wpdb->query($sql);

}
*/

register_activation_hook(__FILE__, 'evanescent_install');

register_deactivation_hook(__FILE__, 'evanescent_uninstall');


class Evanescent {

	// class instance
	static $instance;

	// invitee WP_List_Table object
	public $invitees_obj;
  public $gates_obj;

  public $plugin_url;

  public $msg;

	// MySQL tables
	public $table = EV_TABLE_NAME; 
  public $table_gates = EV_TABLE_GATES_NAME;

	// class constructor
	public function __construct() {
		add_filter( 'set-screen-option', [ __CLASS__, 'set_screen' ], 10, 3 );
		add_action( 'admin_menu', [ $this, 'plugin_menu' ] );

    // Check for redirects just before page template loads
    add_action('template_redirect', [$this, 'set_up_redirects']);
   
    // Add form after content welcome pages
    add_filter('the_content', [$this, 'after_welcome_content']);

    // For session vars
    // Start session on init
    // End session if user logs in/out
    add_action('init', [$this, 'start_session'], 1);
    add_action('wp_logout', [$this, 'end_session']);
    add_action('wp_login', [$this, 'end_session']);

    // AJAX functions
    add_action('wp_ajax_evanescent_ajax_check_time', [$this, 'evanescent_ajax_check_time']);
    add_action('wp_ajax_nopriv_evanescent_ajax_check_time', [$this, 'evanescent_ajax_check_time']); // for non-logged in users

    // Plugin URL
    $this->plugin_url = plugin_dir_url( __FILE__ );

    // Enqueue scripts and styles
    $this->gate_scripts_and_styles();
	}


	public static function set_screen( $status, $option, $value ) {
		return $value;
	}

	public function plugin_menu() {

    // Main admin menu heading (links to Invitees submenu page)
		add_menu_page(
			'Evanescent',
			'Evanescent',
			'manage_options',
			'evanescent'
		);

    // Invitees admin page
    $hook = add_submenu_page(
      'evanescent',
      'Invitees',
      'Invitees',
      'manage_options',
      'evanescent', // Same as parent menu slug so both point to same place
      [ $this, 'invitees_settings_page' ]
    );
    add_action( 'load-' . $hook, [ $this, 'screen_option' ] );
    add_action('admin_print_scripts-' . $hook, [ $this, 'admin_scripts_and_styles']);

    // Gates admin page
    $hook_gates = add_submenu_page(
      'evanescent',
      'Gates',
      'Gates',
      'manage_options',
      'evanescent_gates',
      [ $this, 'gates_settings_page' ]
    );
    add_action( 'load-' . $hook_gates, [ $this, 'screen_option_gates' ] );
    add_action('admin_print_scripts-' . $hook_gates, [ $this, 'admin_scripts_and_styles']);

	}

  /**
   * Enqueue admin scripts and styles
   */
  public function admin_scripts_and_styles() {
    wp_enqueue_style('evanescent_admin', $this->plugin_url . 'css/evanescent-admin-v0.1.0.css' );
    wp_enqueue_script('jquery');
    wp_enqueue_script('evanescent_admin_script', $this->plugin_url . 'js/evanescent-admin-v0.1.0.js', false, null, true );
  }

  /**
   * Enqueue gate scripts and styles
   */
  public function gate_scripts_and_styles() {
    wp_enqueue_style('evanescent', $this->plugin_url . 'css/evanescent-v0.1.0.css' );
    wp_enqueue_script('jquery');
    wp_enqueue_script('evanescent_ajax_script', $this->plugin_url . 'js/evanescent-ajax-v0.1.0.js', false, null, true );
    wp_localize_script('evanescent_ajax_script', 'frontendajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
  }

  // Handles AJAX request for Find Food results
  public function evanescent_ajax_check_time() {

    global $wpdb;

    // The $_REQUEST contains all the data sent via ajax
    if ( isset($_REQUEST) ) {

      $sanitized = $this->sanitize($_REQUEST);
      $email = $sanitized['email'];
      $gate = $sanitized['gate'];

      // Query table_gates for welcome_pid
      $query = "SELECT * FROM {$this->table_gates} WHERE name = '$gate'";
      $row   = $wpdb->get_row($query, ARRAY_A);
      $welcome_pid = $row['welcome_pid'];

      // Query table for email
      $query = "SELECT * FROM {$this->table}  WHERE email = '$email' and gate = '$gate'";
      $row = $wpdb->get_row($query, ARRAY_A);

      if(!$this->timestamp_expired($row['timestamp'])) {
        // Timestamp not expired.
        echo 'authorized';
      }
      else {
        // Timestamp is expired.
        echo 'expired';
      }
    }
    // Always die in functions echoing ajax content
    die();
  }

  /**
   * Gates settings page
   */
  public function gates_settings_page() {
    require_once 'partials/admin-page-gates.php';
  }

	/**
	 * Plugin settings page
	 */
	public function invitees_settings_page() {
    require_once 'partials/admin-page-invitees.php';
	}

	/**
   * Invitees page options
   */
  public function screen_option() {

    $option = 'per_page';
    $args   = [
      'label'   => 'Invitees',
      'default' => 5,
      'option'  => 'invitees_per_page'
    ];

    add_screen_option( $option, $args );

    $this->invitees_obj = new Invitees_List();
  }

  /**
   * Gates page options
   */
  public function screen_option_gates() {

    $option = 'per_page';
    $args   = [
      'label'   => 'Gates',
      'default' => 5,
      'option'  => 'gates_per_page'
    ];

    add_screen_option( $option, $args );

    $this->gates_obj = new Gates_List();
  }

	// Enables session variables in WordPress
  private function start_session() {
    if(!session_id()) {
      session_start();
    }
  }

  // Destroys session variables in WordPress
  private function end_session() {
    session_destroy();
  }

  public function evanescent_errors($err) {
    if (100 == $err) {
      $err_message = 'Your email or password is incorrect.';
    }
    else
    if (101 == $err) {
      $err_message = 'Your login has expired.';
    }
    else {
      $err_message = false;
    }
    return $err_message;
  }

  public function sanitize($input) {

    $new_input = [];

    if (isset($input['email'])) {
      $new_input['email'] = sanitize_text_field($input['email']);
    }
    if (isset($input['pass'])) {
      $new_input['pass'] = sanitize_text_field($input['pass']);
    }
    if (isset($input['first-name'])) {
      $new_input['first-name'] = sanitize_text_field($input['first-name']);
    }
    if (isset($input['last-name'])) {
      $new_input['last-name'] = sanitize_text_field($input['last-name']);
    }
    if (isset($input['gate'])) {
      $new_input['gate'] = sanitize_text_field($input['gate']);
    }
    if (isset($input['evanescent-pid'])) {
      $new_input['evanescent-pid'] = sanitize_text_field(intval($input['evanescent-pid'])); // int values only
    }
    if (isset($input['pids'])) {
      $new_input['pids'] = sanitize_text_field($input['pids']);
    }
    if (isset($input['welcome-pid'])) {
      $new_input['welcome-pid'] = sanitize_text_field(intval($input['welcome-pid']));
    }
    if (isset($input['evanescent-err'])) {
      $new_input['evanescent-err'] = sanitize_text_field(intval($input['evanescent-err'])); // int values only
    }
    return $new_input;
  }

  public function email_cb() {
    echo '<div>';
    echo '<label for="evanescent-email">Email</label>';
    printf(
      '<input id="evanescent-email" name="evanescent_add_data[email]" size="50" value="%s" >',
      isset($this->add_data['email']) ? esc_attr($this->add_data['email']) : ''
    );
    echo '</div>';
  }

  public function first_name_cb() {
    echo '<div>';
    echo '<label for="evanescent-first-name">First Name</label>';
    printf(
      '<input id="evanescent-first-name" name="evanescent_add_data[first-name]" size="50" value="%s" >',
      isset($this->add_data['first-name']) ? esc_attr($this->add_data['first-name']) : ''
    );
    echo '</div>';
  }

  public function last_name_cb() {
    echo '<div>';
    echo '<label for="evanescent-last-name">Last Name</label>';
    printf(
      '<input id="evanescent-last-name" name="evanescent_add_data[last-name]" size="50" value="%s" >',
      isset($this->add_data['last-name']) ? esc_attr($this->add_data['last-name']) : ''
    );
    echo '</div>';
  }
  
  public function email_gate_cb() {
    echo '<div>';
    echo '<label for="evanescent-gate-select">Gate</label>';
    printf(
      '<select id="evanescent-gate-select" name="evanescent_add_data[gate]" value="%s" >',
      isset($this->add_data['gate']) ? esc_attr($this->add_data['gate']) : ''
    );
    foreach ($this->gate_results as $gate) {
      $name = isset($gate['name']) ? esc_attr($gate['name']) : '';
      echo sprintf('<option value="%s">%s</option>', $name, $name);
    }
    print('</select>');
    echo '</div>';
  }

  public function gate_name_cb() {
    echo '<div>';
    echo '<label for="evanescent-gate-name">Gate Name</label>';
    printf(
      '<input id="evanescent-gate-name" name="evanescent_add_gate_data[gate]" size="50" value="%s" >',
      isset($this->add_gate_data['gate']) ? esc_attr($this->add_gate_data['gate']) : ''
    );
    echo '</div>';
  }

  public function gate_pids_cb() {
    echo '<div>';
    echo '<label for="evanescent-gate-pids">Gated Page/Post IDs</label>';
    printf(
      '<input id="evanescent-gate-pids" name="evanescent_add_gate_data[pids]" size="50" value="%s" >',
      isset($this->add_gate_data['pids']) ? esc_attr($this->add_gate_data['pids']) : ''
    );
    echo '</div>';
  }

  public function gate_welcome_pid_cb() {
    echo '<div>';
    echo '<label for="evanescent-welcome-pid">Welcome Page ID</label>';
    printf(
      '<input id="evanescent-welcome-pid" name="evanescent_add_gate_data[welcome-pid]" size="50" value="%s" >',
      isset($this->add_gate_data['welcome-pid']) ? esc_attr($this->add_gate_data['welcome-pid']) : ''
    );
    echo '</div>';
  }

  public function evanescent_add_new($input) {

    global $wpdb;
    //$d    = date('0000-00-00 00:00:00');
    $pwd  = bin2hex(openssl_random_pseudo_bytes(4)); // Generate an 8 character string

    $data = $wpdb->insert(
      EV_TABLE_NAME,
      [
        // MySQL default for timestamp when added
        //'timestamp'  => $d,
        'email'      => $input['email'],
        'first_name' => $input['first-name'],
        'last_name'  => $input['last-name'],
        'gate'       => $input['gate'],
        'pass'       => $pwd,
        'viewed'     => 0,
        'expired'    => 0
      ],
      ['%s', '%s', '%s', '%s', '%s', '%d', '%d']
    );

    return $data;
  }

  public function evanescent_add_new_gate($input) {

    global $wpdb;

    $data = $wpdb->insert(
      EV_TABLE_GATES_NAME,
      [
        'name'        => $input['gate'],
        'pids'        => $input['pids'],
        'welcome_pid' => $input['welcome-pid']
      ],
      ['%s', '%s']
    );

    return $data;
  }

  public function timestamp_expired($timestamp) {
    $time = strtotime($timestamp);
    $curtime = time();
    if(($curtime - $time) <= 3600) {  // 3600 seconds (1 hour
      return false; // Not expired
    }
    else {
      return true; // Expired
    }
  }

  public function output_data_atts($email, $gate, $pids, $welcome_pid) {
    $welcome_url = get_the_permalink($welcome_pid);
    return '<div id="evanescent-invitee" style="visibility:hidden!important;height:0!important;" data-evanescent-email="' . $email . '" data-evanescent-gate="' . $gate . '" data-evanescent-url="' . $welcome_url . '?evanescent-pid=' . $pids . '&evanescent-err=101"></div>';
  }

  public function create_session_vars($email) {
    $_SESSION['evanescent_auth'] = true;
    $_SESSION['evanescent_email'] = $email;
    return;
  }

  public function destroy_session_vars() {
    unset($_SESSION['evanescent_auth']);
    unset($_SESSION['evanescent_email']);
    return;
  }

  public function redirect($welcome_pid, $pid, $err = false) {
    $url  = get_the_permalink($welcome_pid) . '?evanescent-pid=' . $pid;
    //Add any error codes to the URL
    $url .= $err ? '&evanescent-err=' . intval($err) : ''; 
    // Redirect
    if (isset($welcome_pid)) {
      wp_redirect($url);
    }
    else {
      wp_redirect(home_url( '/' ));
    }
    die;
  }

  public function set_expired_and_redirect($email, $gate, $welcome_pid) {

    global $post;
    global $wpdb;

    $query = "UPDATE {$this->table}  SET expired = 1 WHERE email = '$email' and gate = '$gate'";
    $row   = $wpdb->get_row($query, ARRAY_A);
    $this->destroy_session_vars();
    // Redirect and return.
    $this->redirect($welcome_pid, $post->ID, 101); // 101 (expired)
    return;
  }

  // The template_redirect for the gated pids
  public function set_up_redirects() {

    session_start();

    global $post;
    global $wpdb;

     // Get gates results
    $query   = "SELECT * FROM {$this->table_gates}";
    $results = $wpdb->get_results($query, ARRAY_A);

    // Check pids and set up redirect
    foreach ($results as $gate) :
      if ($post->ID == $gate['pids']) :
        // Gate found for this post
        if (isset($_POST['evanescent_login'])) :
          // Login data exists
          $sanitized = $this->sanitize($_POST['evanescent_login']);
          $email     = isset($sanitized['email']) ? $sanitized['email'] : '';
          $pass      = isset($sanitized['pass']) ? $sanitized['pass'] : '';
          
          $query = "SELECT * FROM {$this->table}  WHERE email = '$email' and pass = '$pass'";
          $row = $wpdb->get_row($query, ARRAY_A);
          if ($row && count($row) > 1) :
            // Login matches, keep going...
            $viewed  = boolval($row['viewed']) ? true : false;
            $expired = boolval($row['expired']) ? true : false;
            $dt      = date('Y-m-d H:i:s'); // Current date/time

            if ($expired) {
              // Timestamp is expired.
              // Redirect and return.
              $this->redirect($gate['welcome_pid'], $post->ID, 101); // 101 (expired)
              return;
            }
            else
            if (!$viewed) {
              // Not previously viewed, so mark the video as 'viewed' and update the timestamp
              $query = "UPDATE {$this->table}  SET viewed = 1, timestamp = '$dt' WHERE email = '$email' and gate = '" . $row['gate'] . "'";
              $row   = $wpdb->get_row($query, ARRAY_A);
              // User has permission.
              // No redirect.
              //echo 'Success';
              $this->create_session_vars($email);
              echo $this->output_data_atts($email, $gate['name'], $gate['pids'], $gate['welcome_pid']); // Add data atts to page
              return;
            }
            else {
              // Previously logged in and viewed, so let's check the timestamp
              if(!$this->timestamp_expired($row['timestamp'])) {  // 3600 seconds (1 hour
                // Timestamp not expired. User has permission.
                // No redirect.
                //echo 'Success';
                //$this->create_session_vars($email);
                echo $this->output_data_atts($email, $gate['name'], $gate['pids'], $gate['welcome_pid']); // Add data atts to page
                return;
              }
              else {
                // Timestamp is expired.
                // Set expired to 1, redirect and return.
                $this->set_expired_and_redirect($email, $row['gate'], $gate['welcome_pid']);
                return;
              }
            }
          endif; // Login matches
        elseif(isset($_SESSION['evanescent_auth']) && $_SESSION['evanescent_auth'] && isset($_SESSION['evanescent_email'])) :
          // No login data, but session vars already exists.
          // Query the db for timestamp
          $email     = $_SESSION['evanescent_email'];
          $gate_name = $gate['name'];
          $query = "SELECT * FROM {$this->table}  WHERE email = '$email' and gate = '$gate_name'";
          $row = $wpdb->get_row($query, ARRAY_A);

          if(!$this->timestamp_expired($row['timestamp'])) {
            // Timestamp not expired.
            // No redirect.
            echo $this->output_data_atts($email, $gate_name, $gate['pids'], $gate['welcome_pid']); // Add data atts to page
            echo 'Success: session vars good!';
            return;
          }
          else {
            // Timestamp is expired.
            // Set expired to 1, redirect and return.
            $this->set_expired_and_redirect($email, $row['gate'], $gate['welcome_pid']);
            return;
          }
        else :
          // No $_POST vars. No session vars. This is likely a direct link.
          // Redirect and return.
          $this->redirect($gate['welcome_pid'], $post->ID);
          return;
        endif;
      endif; // Gate found
    endforeach;
  }

  public function after_welcome_content($content) {
    global $post;
    global $wpdb;

    $fullcontent = $content;

    if (isset($_GET['evanescent-pid'])) {

      $sanitized = $this->sanitize($_GET);
      
      // Get gates results
      $query = "SELECT * FROM {$this->table_gates} WHERE welcome_pid = $post->ID";
      $row   = $wpdb->get_row($query, ARRAY_A);

      if ($row) {
        // Begin output buffering
        ob_start();
        require_once 'partials/form-welcome.php';
        $after = ob_get_contents();
        ob_end_clean();
        // End output buffering
        $fullcontent .= $after;
      }
    }
    return $fullcontent;
  }
  
	/** Singleton instance */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}


add_action( 'plugins_loaded', function () {
	Evanescent::get_instance();
} );
