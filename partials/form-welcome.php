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
    <ul>
      <li>The duration of this video is one hour and 20 minutes</li>
      <li>Upon clicking the “Yes” button below you will have access to his video for up to <strong>7 hours</strong></li>
      <li>Although recommended to do so, you do not need to watch the video in one sitting. You may start and stop the presentation as many times as you wish or need to during those seven hours.</li>
    </ul>
    <p>Thanks and may you have a blessed day.</p>
    <input type="submit" value="Yes">
  </form>
  <?php
  endif;
  ?>
</div>