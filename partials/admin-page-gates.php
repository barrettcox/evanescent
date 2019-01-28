<?php
/*
 * Gates admin page
 */

$this->add_gate_data = $this->sanitize($_POST['temporal_add_gate_data']);

if (count($this->add_gate_data)) {

  $data = $this->add_new_gate($this->add_gate_data);
  if ($data) $this->msg = 'New gate added.';
}
?>

<div class="wrap">

  <?php
  // Notice box
  if ($this->msg != '') require_once 'notice-msg.php';
  ?>

  <h1>Temporal</h1>
  <h2>Gates</h2>
  <div id="poststuff">
    <div id="post-body" class="metabox-holder">
      <div id="post-body-content">
        <div class="meta-box-sortables ui-sortable">
          <form method="post">
            <?php
            $this->gates_obj->prepare_items();
            $this->gates_obj->display(); ?>
          </form>
        </div>
      </div>
    </div>
    <br class="clear">
  </div>

  <div class="postbox postbox--basic">
    <h3><strong>Add New Gate</strong></h3>
    <form method="post" action="#">
      <?php
      $this->gate_name_cb();
      $this->gate_pids_cb();
      $this->gate_welcome_pid_cb();
      $this->gate_content_after_fields_cb();
      $this->gate_content_expired_cb();
      submit_button('Add');
      ?>
    </form>
  </div>

</div><!-- /.wrap -->