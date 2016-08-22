<?php
// Set the Options Page
function cp_display_settings(){
  global $codeplace_plugin;
  $uuid = get_option('cp_uuid');

?>

<div class="wrap">
  <h2>Codeplace Settings</h2>
  <div style="padding-top: 20px; width: 100%;">

<?php
  if(empty($uuid)){ // Remove !
?>

    <div style="display: inline-block; width:40%; margin-right: 10px; padding: 1em; background-color: white;">
      <h3>Writer</h3>
      <a class="button button-primary button-hero" href="http://writers.codeplace.com/app/connect?blog=<?php echo urlencode(site_url()); ?>&auth=<?php echo urlencode($codeplace_plugin->generate_registration_token()); ?>&return_url=<?php echo urlencode(menu_page_url("codeplace", false)); ?>">Connect with your Codeplace Writer Account</a>

    </div>

<?php
  } else { // ------------------------------
?>

    <p>Connectd with account: <?php echo get_option('cp_user_name'); ?> (<?php echo get_option('cp_user_email'); ?>)</p>

<?php
  } // end if
?>

  </div>
</div>


<?php


echo 'Debug';
echo '<br>';
echo 'cp_plugin_version_number: '.get_option('cp_plugin_version_number');
echo '<br>';
echo 'cp_activation_redirect: '.get_option('cp_activation_redirect');
echo '<br>';
echo 'cp_public_key: '.get_option('cp_public_key');
echo '<br>';
echo 'cp_uuid: '.get_option('cp_uuid');
echo '<br>';
echo 'cp_user_email: '.get_option('cp_user_email');
echo '<br>';
echo 'cp_user_name: '.get_option('cp_user_name');
echo '<br>';
echo 'cp_activation_error: '.get_option('cp_activation_error');

// End debug

} // end function
?>