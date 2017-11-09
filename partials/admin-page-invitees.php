<?php
/*
 * Invitees admin page
 */

$this->add_data = $this->sanitize($_POST['evanescent_add_data']);
$this->settings_data = $this->sanitize($_POST['evanescent_settings']);

if (count($this->add_data)) {
  $data = $this->add_new($this->add_data);
  if ($data) $this->msg = 'New email added.';
}

if (count($this->settings_data)) {
  $data = $this->update_settings($this->settings_data);
  if ($data) $this->msg = 'Settings updated.';
}
?>
    
<div class="wrap">

  <?php
  // Notice box
  if ($this->msg != '') require_once 'notice-msg.php';
  ?>

  <h1>Evanescent</h1>
  <h2>Invitees</h2>
  <div id="poststuff">
    <div id="post-body" class="metabox-holder">
      <div id="post-body-content">
        <div class="meta-box-sortables ui-sortable">
          <form method="post">
            <?php
            $this->invitees_obj->prepare_items();
            $this->invitees_obj->display(); ?>
          </form>
        </div>
      </div>
    </div>
    <br class="clear">
  </div>

  <?php
  global $wpdb;
  $query = "SELECT * FROM {$this->table_gates} LIMIT 1";
  $gates_exist = $wpdb->get_row($query, ARRAY_A);
  ?>
    
  <div class="postbox postbox--basic">
    <h3><strong>Add New Invitee</strong></h3>

    <?php if ($gates_exist) :

    // Get gates results
    $query = "SELECT * FROM {$this->table_gates}";
    $this->gate_results = $wpdb->get_results($query, ARRAY_A); ?>

    <form method="post">
      <?php
      $this->first_name_cb();
      $this->last_name_cb();
      $this->email_cb();
      $this->email_gate_cb();
      submit_button('Add');
      ?>
    </form>

    <?php else : ?>
    <p>You must add at least one Gate before adding an invitee.</p>
    <?php endif; ?>
  </div>

  <div class="postbox postbox--basic">
    <h3><strong>Settings</strong></h3>
    <form method="post" action="#">
      <?php
      $this->duration_cb();
      submit_button('Save');
      ?>
    </form>
  </div>
</div><!-- /.wrap -->
