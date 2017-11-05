<?php
/*
 * Welcome form
 */
?>

<div class="evanescent-welcome-form">
  <?php if($sanitized['evanescent-err']) : ?>
  <p><?php echo $this->evanescent_errors($sanitized['evanescent-err']); ?></p>
  <?php endif; ?>
  <?php 
  if($sanitized['evanescent-err'] && $sanitized['evanescent-err'] == 101) :
    // If expired, no form
  else : ?>
  <form method="post" action="<?php the_permalink($sanitized['evanescent-pid']); ?>">
    <div><label for="evanescent-gate-pids">Email</label></div>
    <div>
    <input id="evanescent-email" name="evanescent_login[email]" size="25" value="">
    </div>
    <div><label for="evanescent-gate-pids">Password</label></div>
    <div>
    <input id="evanescent-pass" name="evanescent_login[pass]" size="25" value="" type="password">
    </div>
    <p>You will have one-time access for just 24 hours after you begin watching the video.
    Do you want to begin watching the video now?</p>
    <input type="submit" value="Yes">
  </form>
  <?php
  endif;
  ?>
</div>