<?php
/*
 * Invitees admin page
 */

$this->add_data = $this->sanitize($_POST['evanescent_add_data']);

if (count($this->add_data)) {
  $data = $this->evanescent_add_new($this->add_data);
  if ($data) $this->msg = 'New email added.';
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
    
  <div class="postbox postbox--add">
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
</div><!-- /.wrap -->
