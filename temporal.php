<?php

/*
Plugin Name: Temporal
Plugin URI:
Description: Provides time sensitive access to WordPress pages
Version: 0.1.4
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


function temporal_install() {

  global $wpdb;

  $table = EV_TABLE_NAME; 
  $table_gates = EV_TABLE_GATES_NAME;

  $charset_collate = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE $table (
         `id` mediumint(9) NOT NULL AUTO_INCREMENT,
         `timestamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
         `username` varchar(254) NOT NULL DEFAULT '',
         `first_name` varchar(255) NOT NULL DEFAULT '',
         `last_name` varchar(255) NOT NULL DEFAULT '',
         `pass` varchar(8) NOT NULL DEFAULT '',
         `gate` varchar(50) NOT NULL DEFAULT '',
         `viewed` tinyint(1) NOT NULL DEFAULT 0,
         `init_secondary` tinyint(1) NOT NULL DEFAULT 0,
         `sent` tinyint(1) NOT NULL DEFAULT 0,
         `expired` tinyint(1) NOT NULL DEFAULT 0,
         PRIMARY KEY  (`id`)
         ) $charset_collate;";

  $sql_pages = "CREATE TABLE $table_gates (
               `id` mediumint(9) NOT NULL AUTO_INCREMENT,
               `name` varchar(50) NOT NULL DEFAULT '',
               `pids` varchar(255) NOT NULL DEFAULT '',
               `welcome_pid` int NOT NULL DEFAULT 0,
               `content_after_fields` varchar(510) NOT NULL DEFAULT '',
               `content_expired` varchar(510) NOT NULL DEFAULT '',
               PRIMARY KEY  (`id`)
               ) $charset_collate;";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

  dbDelta($sql);
  dbDelta($sql_pages);

  // Create options
  add_option( 'temporal_settings', ['duration' => 3600,
                                      'duration-secondary' => 0 ], '', 'yes' );

}

/*
function temporal_uninstall() {

  global $wpdb;

  // MySQL table name
  $table = EV_TABLE_NAME;

  $sql = "DROP TABLE IF EXISTS $table";

  $wpdb->query($sql);

}
*/

register_activation_hook(__FILE__, 'temporal_install');

register_deactivation_hook(__FILE__, 'temporal_uninstall');


class Temporal {

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
    add_filter('the_content', [$this, 'after_welcome_content'], 9000);

    // Add form after content welcome pages
    add_filter('the_content', [$this, 'after_gate_content'], 9001);

    // For session vars
    // Start session on init
    // End session if user logs in/out
    add_action('init', [$this, 'start_session'], 1);
    add_action('wp_logout', [$this, 'end_session']);
    add_action('wp_login', [$this, 'end_session']);

    // AJAX functions
    add_action('wp_ajax_temporal_ajax_check_time', [$this, 'temporal_ajax_check_time']);
    add_action('wp_ajax_nopriv_temporal_ajax_check_time', [$this, 'temporal_ajax_check_time']); // for non-logged in users
    add_action('wp_ajax_temporal_ajax_init_secondary', [$this, 'temporal_ajax_init_secondary']);
    add_action('wp_ajax_nopriv_temporal_ajax_init_secondary', [$this, 'temporal_ajax_init_secondary']); // for non-logged in users

    // Shortcodes
    add_shortcode( 'temporal_welcome_form', [$this, 'welcome_form_shortcode_init']);

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
			'Temporal',
			'Temporal',
			'manage_options',
			'temporal',
      '', //$callable function
      'none' // $icon_url added via CSS
		);

    // Invitees admin page
    $hook = add_submenu_page(
      'temporal',
      'Invitees',
      'Invitees',
      'manage_options',
      'temporal', // Same as parent menu slug so both point to same place
      [ $this, 'invitees_settings_page' ]
    );
    add_action( 'load-' . $hook, [ $this, 'screen_option' ] );
    add_action('admin_print_scripts-' . $hook, [ $this, 'admin_scripts_and_styles']);

    // Gates admin page
    $hook_gates = add_submenu_page(
      'temporal',
      'Gates',
      'Gates',
      'manage_options',
      'temporal_gates',
      [ $this, 'gates_settings_page' ]
    );
    add_action( 'load-' . $hook_gates, [ $this, 'screen_option_gates' ] );
    add_action('admin_print_scripts-' . $hook_gates, [ $this, 'admin_scripts_and_styles']);

	}

  /**
   * Enqueue admin scripts and styles
   */
  public function admin_scripts_and_styles() {
    wp_enqueue_style('temporal_admin', $this->plugin_url . 'css/temporal-admin-v0.1.4.css' );
    wp_enqueue_script('jquery');
    wp_enqueue_script('temporal_admin_script', $this->plugin_url . 'js/temporal-admin-v0.1.4.js', false, null, true );
  }

  /**
   * Enqueue gate scripts and styles
   */
  public function gate_scripts_and_styles() {
    wp_enqueue_style('temporal', $this->plugin_url . 'css/temporal-v0.1.4.css' );
    wp_enqueue_script('jquery');
    wp_enqueue_script('temporal_ajax_script', $this->plugin_url . 'js/temporal-ajax-v0.1.4.js', false, null, true );
    wp_localize_script('temporal_ajax_script', 'frontendajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
  }

  // Handles AJAX request for timestamp comparison
  public function temporal_ajax_check_time() {

    global $wpdb;

    // The $_REQUEST contains all the data sent via ajax
    if ( isset($_REQUEST) ) {

      $sanitized = $this->sanitize($_REQUEST);
      $username = $sanitized['username'];
      $gate = $sanitized['gate'];

      // Query table_gates for welcome_pid
      $query = "SELECT * FROM {$this->table_gates} WHERE name = '$gate'";
      $row   = $wpdb->get_row($query, ARRAY_A);
      $welcome_pid = $row['welcome_pid'];

      // Query table for username
      $query = "SELECT * FROM {$this->table}  WHERE username = '$username' and gate = '$gate'";
      $row = $wpdb->get_row($query, ARRAY_A);
      $settings = get_option('temporal_settings');

      // If secondary has been initiated, use the secondary duration
      $duration = boolval($row['init_secondary']) ? intval($settings['duration-secondary']) : intval($settings['duration']);

      if(!$this->timestamp_expired($row['timestamp'], $duration)) {
        // Timestamp not expired.
        $remaining = $this->get_remaining_time($row['timestamp'], $duration);
        $result = [ "remaining" => $remaining, "secondary" => intval($row['init_secondary']) ];
        echo json_encode($result); // Output for JS
      }
      else {
        // Timestamp is expired.
        set_expired($username, $gate, $welcome_pid);
        echo 'expired';
      }
    }
    // Always die in functions echoing ajax content
    die();
  }

  // Handles AJAX request for starting secondary timer
  public function temporal_ajax_init_secondary() {

    global $wpdb;

    // The $_REQUEST contains all the data sent via ajax
    if ( isset($_REQUEST) ) {

      $sanitized = $this->sanitize($_REQUEST);
      $username = $sanitized['username'];
      $gate = $sanitized['gate'];
      $dt = date('Y-m-d H:i:s'); // Current date/time

      $query = "SELECT * FROM {$this->table} WHERE username = '$username' and gate = '$gate'";
      $row   = $wpdb->get_row($query, ARRAY_A);

      if (boolval($row['init_secondary'])) {
        echo 'Secondary already initiated';
      }
      else {
        // Update secondary value and timestamp in db
        $query = "UPDATE {$this->table}  SET init_secondary = 1, timestamp = '$dt' WHERE username = '$username' and gate = '$gate'";
        $result = $wpdb->query($query);

        if($result) {
          echo 'success';
        }
        else {
          echo 'There was a problem updating the database.';
        }
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

  public function temporal_errors($err, $err_overrides=false) {
    if (!empty($err_overrides[$err])) {
      $err_message = $err_overrides[$err];
    }
    else
    if (100 == $err) {
      $err_message = 'Your username or password is incorrect.';
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

    if (isset($input['username'])) {
      $new_input['username'] = sanitize_text_field($input['username']);
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
    if (isset($input['duration'])) {
      $new_input['duration'] = sanitize_text_field(intval($input['duration'])); // int values only
    }
    if (isset($input['duration-secondary'])) {
      $new_input['duration-secondary'] = sanitize_text_field(intval($input['duration-secondary'])); // int values only
    }
    if (isset($input['gate'])) {
      $new_input['gate'] = sanitize_text_field($input['gate']);
    }
    if (isset($input['content-after-fields'])) {
      $new_input['content-after-fields'] = wp_kses_post($input['content-after-fields']);
    }
    if (isset($input['content-expired'])) {
      $new_input['content-expired'] = wp_kses_post($input['content-expired']);
    }
    if (isset($input['temporal-pid'])) {
      $new_input['temporal-pid'] = sanitize_text_field(intval($input['temporal-pid'])); // int values only
    }
    if (isset($input['pids'])) {
      $new_input['pids'] = sanitize_text_field($input['pids']);
    }
    if (isset($input['welcome-pid'])) {
      $new_input['welcome-pid'] = sanitize_text_field(intval($input['welcome-pid']));
    }
    if (isset($input['temporal-err'])) {
      $new_input['temporal-err'] = sanitize_text_field(intval($input['temporal-err'])); // int values only
    }
    return $new_input;
  }

  public function username_cb() {
    echo '<div>';
    echo '<label for="temporal-username">username</label>';
    printf(
      '<input id="temporal-username" name="temporal_add_data[username]" size="50" value="%s" >',
      isset($this->add_data['username']) ? esc_attr($this->add_data['username']) : ''
    );
    echo '</div>';
  }

  public function first_name_cb() {
    echo '<div>';
    echo '<label for="temporal-first-name">First Name</label>';
    printf(
      '<input id="temporal-first-name" name="temporal_add_data[first-name]" size="50" value="%s" >',
      isset($this->add_data['first-name']) ? esc_attr($this->add_data['first-name']) : ''
    );
    echo '</div>';
  }

  public function last_name_cb() {
    echo '<div>';
    echo '<label for="temporal-last-name">Last Name</label>';
    printf(
      '<input id="temporal-last-name" name="temporal_add_data[last-name]" size="50" value="%s" >',
      isset($this->add_data['last-name']) ? esc_attr($this->add_data['last-name']) : ''
    );
    echo '</div>';
  }
  
  public function username_gate_cb() {
    echo '<div>';
    echo '<label for="temporal-gate-select">Gate</label>';
    printf(
      '<select id="temporal-gate-select" name="temporal_add_data[gate]" value="%s" >',
      isset($this->add_data['gate']) ? esc_attr($this->add_data['gate']) : ''
    );
    foreach ($this->gate_results as $gate) {
      $name = isset($gate['name']) ? esc_attr($gate['name']) : '';
      echo sprintf('<option value="%s">%s</option>', $name, $name);
    }
    print('</select>');
    echo '</div>';
  }

  public function duration_cb() {
    $settings = get_option('temporal_settings');
    echo '<div>';
    echo '<label for="temporal-duration">Login Duration (seconds)</label>';
    printf(
      '<input id="temporal-duration" name="temporal_settings[duration]" size="15" value="%s" >',
      isset($settings['duration']) ? $settings['duration'] : 3600
      // Default to 3600 seconds (1 hour) if the option does not exist
    );
    echo '</div>';
  }

  public function duration_secondary_cb() {
    $settings = get_option('temporal_settings');
    echo '<div>';
    echo '<label for="temporal-duration-secondary">Secondary Duration (seconds)</label>';
    echo '<p><em>The countdown for the Secondary Duration occurs after some triggering event.</em></p>';
    printf(
      '<input id="temporal-duration-secondary" name="temporal_settings[duration-secondary]" size="15" value="%s" >',
      isset($settings['duration-secondary']) ? $settings['duration-secondary'] : 0 // Default to 0 seconds if the option does not exist
    );
    echo '</div>';
  }

  public function gate_name_cb() {
    echo '<div>';
    echo '<label for="temporal-gate-name">Gate Name</label>';
    printf(
      '<input id="temporal-gate-name" name="temporal_add_gate_data[gate]" size="50" value="%s" >',
      isset($this->add_gate_data['gate']) ? esc_attr($this->add_gate_data['gate']) : ''
    );
    echo '</div>';
  }


  public function gate_pids_cb() {
    echo '<div>';
    echo '<label for="temporal-gate-pids">Gated Page/Post IDs</label>';
    printf(
      '<input id="temporal-gate-pids" name="temporal_add_gate_data[pids]" size="50" value="%s" >',
      isset($this->add_gate_data['pids']) ? esc_attr($this->add_gate_data['pids']) : ''
    );
    echo '</div>';
  }

  public function gate_welcome_pid_cb() {
    echo '<div>';
    echo '<label for="temporal-welcome-pid">Welcome Page ID</label>';
    printf(
      '<input id="temporal-welcome-pid" name="temporal_add_gate_data[welcome-pid]" size="50" value="%s" >',
      isset($this->add_gate_data['welcome-pid']) ? esc_attr($this->add_gate_data['welcome-pid']) : ''
    );
    echo '</div>';
  }

  public function gate_content_after_fields_cb() {
    echo '<div>';
    echo '<label for="temporal-content-after-fields">Message to be displayed after welcome form fields</label>';
    printf(
      '<textarea id="temporal-content-after-fields" name="temporal_add_gate_data[content-after-fields]">%s</textarea>',
      isset($this->add_gate_data['content-after-fields']) ? $this->add_gate_data['content-after-fields'] : ''
    );
    echo '</div>';
  }

  public function gate_content_expired_cb() {
    echo '<div>';
    echo '<label for="temporal-content-expired">Expiration message</label>';
    printf(
      '<textarea id="temporal-content-expired" name="temporal_add_gate_data[content-expired]">%s</textarea>',
      isset($this->add_gate_data['content-expired']) ? $this->add_gate_data['content-expired'] : ''
    );
    echo '</div>';
  }

  public function update_settings($input) {
    update_option('temporal_settings', [ 'duration' => $input['duration'],
                                           'duration-secondary' => $input['duration-secondary'] ]);
  }

  public function add_new($input) {

    global $wpdb;
    //$d    = date('0000-00-00 00:00:00');
    $pwd  = bin2hex(openssl_random_pseudo_bytes(4)); // Generate an 8 character string

    $data = $wpdb->insert(
      EV_TABLE_NAME,
      [
        // MySQL default for timestamp when added
        //'timestamp'  => $d,
        'username'   => $input['username'],
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

  public function add_new_gate($input) {

    global $wpdb;

    $data = $wpdb->insert(
      EV_TABLE_GATES_NAME,
      [
        'name'                 => $input['gate'],
        'pids'                 => $input['pids'],
        'welcome_pid'          => $input['welcome-pid'],
        'content_after_fields' => $input['content-after-fields'],
        'content_expired'      => $input['content-expired']
      ],
      ['%s', '%s']
    );

    return $data;
  }

  public function timestamp_expired($timestamp, $duration) {
    $time = strtotime($timestamp);
    $curtime = time();
    if(($curtime - $time) <= $duration) {
      return false; // Not expired
    }
    else {
      return true; // Expired
    }
  }

  public function get_remaining_time($timestamp, $duration) {
    $time = strtotime($timestamp);
    $curtime = time();
    $elapsed = $curtime - $time;
    $remaining = $duration - $elapsed;
    return $remaining;
  }

  public function output_data_atts($username, $gate, $pids, $welcome_pid) {
    $welcome_url = get_the_permalink($welcome_pid);
    return '<div id="temporal-invitee" style="display:none;" data-temporal-username="' . $username . '" data-temporal-gate="' . $gate . '" data-temporal-url="' . $welcome_url . '?temporal-pid=' . $pids . '&temporal-err=101"></div>';
  }

  public function create_session_vars($username) {
    $_SESSION['temporal_auth'] = true;
    $_SESSION['temporal_username'] = $username;
    return;
  }

  public function destroy_session_vars() {
    unset($_SESSION['temporal_auth']);
    unset($_SESSION['temporal_username']);
    return;
  }

  public function redirect($welcome_pid, $pid, $err = false) {
    $url  = get_the_permalink($welcome_pid) . '?temporal-pid=' . $pid;
    //Add any error codes to the URL
    $url .= $err ? '&temporal-err=' . intval($err) : ''; 
    // Redirect
    if (isset($welcome_pid)) {
      wp_redirect($url);
    }
    else {
      wp_redirect(home_url( '/' ));
    }
    die;
  }

  public function set_expired($username, $gate, $welcome_pid) {
    global $post;
    global $wpdb;

    $query = "UPDATE {$this->table}  SET expired = 1 WHERE username = '$username' and gate = '$gate'";
    $result = $wpdb->query($query);
    $this->destroy_session_vars();
    return;
  }

  public function set_expired_and_redirect($username, $gate, $welcome_pid) {

    global $post;
    /*
    global $wpdb;

    $query = "UPDATE {$this->table}  SET expired = 1 WHERE username = '$username' and gate = '$gate'";
    $result = $wpdb->query($query);
    $this->destroy_session_vars();
    // Redirect and return.
    */
    $this->set_expired($username, $gate, $welcome_pid);
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

      $gates = explode(',', $gate['pids']);

      $gates = ! empty($gates) ? $gates : [ intval($gate['pids']) ]; // Convert to array

      if (in_array($post->ID, $gates)) :
        // Gate found for this post

        if (isset($_POST['temporal_login'])) :
          // Login data exists
          $sanitized = $this->sanitize($_POST['temporal_login']);
          $username  = isset($sanitized['username']) ? $sanitized['username'] : '';
          $pass      = isset($sanitized['pass']) ? $sanitized['pass'] : '';
          
          $query = "SELECT * FROM {$this->table}  WHERE username = '$username' and pass = '$pass'";
          $row = $wpdb->get_row($query, ARRAY_A);

          // Login matches
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
              $query = "UPDATE {$this->table}  SET viewed = 1, timestamp = '$dt' WHERE username = '$username' and gate = '" . $row['gate'] . "'";
              $result = $wpdb->query($query);
              // User has permission.
              // No redirect.
              //echo 'Success';
              $this->create_session_vars($username);
              echo $this->output_data_atts($username, $gate['name'], $gate['pids'], $gate['welcome_pid']); // Add data atts to page
              return;
            }

            else {
              // Previously logged in and viewed, so let's check the timestamp
              $settings = get_option('temporal_settings');
              $duration = $settings ? $settings['duration'] : 3600; // Default to 3600 seconds (1 hour) if the option does not exist
              if(!$this->timestamp_expired($row['timestamp'], $duration)) { 
                // Timestamp not expired. User has permission.
                // No redirect.
                //echo 'Success';
                //$this->create_session_vars($username);
                echo $this->output_data_atts($username, $gate['name'], $gate['pids'], $gate['welcome_pid']); // Add data atts to page
                return;
              }

              else {
                // Timestamp is expired.
                // Set expired to 1, redirect and return.
                $this->set_expired_and_redirect($username, $row['gate'], $gate['welcome_pid']);
                return;
              }
            }

          // Login incorrect, so redirect
          else :
            $this->redirect($gate['welcome_pid'], $post->ID);
            return;
          endif;

        elseif(isset($_SESSION['temporal_auth']) && $_SESSION['temporal_auth'] && isset($_SESSION['temporal_username'])) :
          // No login data, but session vars already exists.


          // Query the db for timestamp
          $username  = $_SESSION['temporal_username'];
          $gate_name = $gate['name'];
          $query     = "SELECT * FROM {$this->table}  WHERE username = '$username' and gate = '$gate_name'";
          $row       = $wpdb->get_row($query, ARRAY_A);

          $settings = get_option('temporal_settings');
          $duration = $settings ? $settings['duration'] : 3600; // Default to 3600 seconds (1 hour) if the option does not exist


          if(!$this->timestamp_expired($row['timestamp'], $duration)) {
          //echo 'duration';
          //var_dump('');
          //die();
            // Timestamp not expired.
            // No redirect.
            echo $this->output_data_atts($username, $gate_name, $gate['pids'], $gate['welcome_pid']); // Add data atts to page
            //echo 'Success: session vars good!';
            return;
          }

          else {
            // Timestamp is expired.
            // Set expired to 1, redirect and return.
            $this->set_expired_and_redirect($username, $row['gate'], $gate['welcome_pid']);
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

    if (isset($_GET['temporal-pid'])) {

      $sanitized = $this->sanitize($_GET);
      
      // Get gates results
      $query = "SELECT * FROM {$this->table_gates} WHERE welcome_pid = $post->ID";
      $row   = $wpdb->get_row($query, ARRAY_A);

      if ($row) {
        // Begin output buffering
        ob_start();
        require 'partials/form-welcome.php';
        $after = ob_get_contents();
        ob_end_clean();
        // End output buffering
        $fullcontent .= $after;
      }
    }
    return $fullcontent;
  }

  public function after_gate_content($content) {
    global $post;
    global $wpdb;

    $fullcontent = $content;

    // Get gates results
    $query = "SELECT * FROM {$this->table_gates} WHERE pids = $post->ID LIMIT 1";
    $row   = $wpdb->get_row($query, ARRAY_A);

    if ($row) {
      $fullcontent .= '<div id="temporal-timer" class="temporal-timer" data-temporal-remaining style="display:none;">Time Remaining: <span class="temporal-timer__time"></span></div>';
    }

    return $fullcontent;
  }

  // Form shortcode
  public function welcome_form_shortcode_init( $atts ){

    global $post;
    global $wpdb;
    //$a = shortcode_atts( array(
      //    'url'        => '#', // Default is #
      //   ), $atts );

    $output = '';

    if (isset($_GET['temporal-pid'])) {

      $sanitized = $this->sanitize($_GET);
      
      // Get gates results
      $query = "SELECT * FROM {$this->table_gates} WHERE welcome_pid = $post->ID";
      $row   = $wpdb->get_row($query, ARRAY_A);

      if ($row) {

        // Begin output buffering
        ob_start();
        require 'partials/form-welcome.php';
        $form = ob_get_contents();
        ob_end_clean();
        // End output buffering
        $output .= $form;
      }
    }
    return $output;
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
	Temporal::get_instance();
} );
