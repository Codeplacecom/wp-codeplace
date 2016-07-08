<?php
// Set the Options Page

function cp_display_settings(){
  global $codeplace_licensing_plugin;
  $send_auth = '';

  $uuid = get_option('cp_uuid');
  if(empty($uuid)){
?>

<div class="wrap">
  <h2>Codeplace Setup</h2>

  <a href="http://writers.codeplace.com/connect/wordpress?blog=<?php echo urlencode(site_url()); ?>&return_url=<?php echo urlencode(menu_page_url("codeplace_settings", false)); ?>">Connect with your Codeplace Writer Account</a>
  <br>
  <br>
  <a href="http://companies.codeplace.com/connect/wordpress?blog=<?php echo urlencode(site_url()); ?>&return_url=<?php echo urlencode(menu_page_url("codeplace_settings", false)); ?>">Connect with your Codeplace Company Account</a>

</div>

<?php
  } else {
?>
  <div class="wrap">
    <h2>Codeplace Setup</h2>

    <p>Connectd with account: <?php echo get_option('cp_user_name'); ?> (<?php echo get_option('cp_user_email'); ?>)</p>
    <br>
    <br>

  </div>
<?php
  }
}

?>
