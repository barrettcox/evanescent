<?php
/*
 * Welcome form
 */

$err_overrides = [ 101 => $row['content_expired'] ];

?>

<div class="temporal-welcome-form">
  <?php if($sanitized['temporal-err']) : ?>
  <p><?php echo $this->temporal_errors($sanitized['temporal-err'], $err_overrides); ?></p>
  <?php endif; ?>
  <?php 
  if($sanitized['temporal-err'] && $sanitized['temporal-err'] == 101) :
    // If expired, no form
  else : ?>
  <form method="post" action="<?php the_permalink($sanitized['temporal-pid']); ?>">
    <div><label for="temporal-gate-pids">Username</label></div>
    <div>
    <input id="temporal-username" name="temporal_login[username]" size="25" value="" type="text">
    </div>
    <div><label for="temporal-gate-pids">Password</label></div>
    <div>
    <input id="temporal-pass" name="temporal_login[pass]" size="25" value="" type="password">
    </div>

    <?php echo $row['content_after_fields']; ?>

    <input type="submit" value="Go">
  </form>
  <?php
  endif;
  ?>
</div>