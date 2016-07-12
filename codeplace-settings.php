<?php
// Set the Options Page
function cp_display_settings(){
  $uuid = get_option('cp_uuid');
?>

<div class="wrap">
  <h2>Codeplace Settings</h2>
  <div style="padding-top: 20px; width: 100%;">

<?php
  if(!empty($uuid)){ // Remove !
?>


    <div style="display: inline-block; width:40%; margin-right: 10px; padding: 1em; background-color: white;">
      <h3>Writer</h3>
      <a class="button button-primary button-hero" href="http://writers.codeplace.com/connect/wordpress?blog=<?php echo urlencode(site_url()); ?>&return_url=<?php echo urlencode(menu_page_url("codeplace_settings", false)); ?>">Connect with your Codeplace Writer Account</a>

    </div>
    <div style="display: inline-block; width:40%; padding: 1em; background-color: white;">
      <h3>Company</h3>
      <a class="button button-primary button-hero" href="http://companies.codeplace.com/connect/wordpress?blog=<?php echo urlencode(site_url()); ?>&return_url=<?php echo urlencode(menu_page_url("codeplace_settings", false)); ?>">Connect with your Codeplace Company Account</a>

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
} // end function
?>


