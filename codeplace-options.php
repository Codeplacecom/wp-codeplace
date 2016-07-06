<?php
// Set the Options Page

function cp_display_settings(){
  global $codeplace_licensing_plugin;
  $send_auth = '';
?>

<div class="wrap">
  <h2>Codeplace Settings</h2>

  <?php

  if (isset($_POST["update_settings"])) {
    if($_POST["cp_email"]==='' || !is_email($_POST['cp_email'])){
      echo '<div id="setting-error-settings_updated" class="error settings-error"> <p><strong>You did not enter a valid email address.  Please enter your email address.</strong></p></div>';
    } else {

      $send_auth = $codeplace_licensing_plugin->send_auth();

      if($send_auth) {

        switch($send_auth) {

          case 'success':

            echo '<div id="setting-error-settings_updated" class="updated settings-error"> <p><strong>Success!  Your plugin has been installed.  Please clear the cache on your blog.</strong></p></div>';

          break;

          default:

            echo '<div id="setting-error-settings_updated" class="error settings-error"> <p><strong>'.$send_auth.'</strong></p></div>';

          break;
        }


      } else {

        echo '<div id="setting-error-settings_updated" class="error settings-error"> <p><strong>Something went wrong.  Please try again.</strong></p></div>';

      }
    }

  }
  ?>
  <form action="" method="POST">
    <input type="hidden" name="update_settings" value="Y" />
    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="cp_email" value="<?php echo get_option('cp_email'); ?>" />
    <input type="hidden" name="cp_api_key" value="<?php echo get_option('cp_api_key'); ?>" />

    <?php if(get_option('cp_email') || $send_auth == 'success'): ?>

    <p><strong>Your blog is connected to Codeplace!  You can login to your Codeplace account at <a href="https://codeplace.com/auth/login">https://codeplace.com/auth/login</a>.<br /><br />Your login email is <?php echo get_option('cp_email'); ?>.</strong></p>

    <div style="display: inline">
    <?php submit_button('Resync with Codeplace');  ?>
    </div>
    <?php else: ?>
    <p>To add your blog to Codeplace, please enter your email and click "Connect to Codeplace".<br />If you already created an account on Codeplace, please enter the email you signed up with below.</p>
    <table class="form-table">
      <tr valign="top">
        <th scope="row">
          <label for="cp_email">Email Address:</label>
        </th>
        <td>
          <input type="text" id="cp_email" name="cp_email" value="" class="regular-text" />
        </td>
      </tr>

      <tr valign="top">
        <th scope="row">
          <label for="cp_wp_address">WP Address:</label>
        </th>
        <td>
          <input type="text" id="cp_wp_address" disabled="disabled" name="cp_wp_address" value="<?php echo site_url(); ?>" class="regular-text" />
        </td>
      </tr>
    </table>
    <?php submit_button('Connect to Codeplace');  ?>

    <p><small><a href="https://codeplace.com/bloggers/" target="_blank">What is Codeplace?</a></small></p>
    <?php endif; ?>
  </form>
</div>

<?php

}

?>
