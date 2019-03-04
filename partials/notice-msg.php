<?php
/*
 * Message notice box
 */

$notice_class = $this->msg['success'] ? 'updated' : 'error'; ?>

<div id="msg" class="<?php echo $notice_class; ?> fade notice is-dismissible">
  <p><?php echo $this->msg['message']; ?></p>
</div>