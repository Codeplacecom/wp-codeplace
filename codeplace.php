<?php
/*
Plugin Name: Codeplace
Plugin URI: http://www.codeplace.com/
Description: Cool!
Version: 0.0.1
Author: Codeplace
Author URI: http://www.codeplace.com
*/

define("CP_API", "http://192.168.1.102:3000/v1/", true);// http://api.codeplace.com/v1/
define("CP_EMAIL", "api@codeplace.com", true);
define("CP_USER", "codeplaceapi_", true);

class Codeplace_Licensing_Plugin {

  var $version = '0.0.1';

  var $redirect_key = 'cp_plugin_do_activation_redirect';
  var $plugin_version_key = 'cp_plugin_version_number';

  public function bootstrap() {
    /* Codeplace Options Panel */
    require_once(plugin_dir_path( __FILE__ ) . 'codeplace-options.php');

    /* Codeplace API */
    require_once(plugin_dir_path( __FILE__ ) . 'codeplace-api.php');

    /* Codeplace License Types Functionality */
    require_once(plugin_dir_path( __FILE__ ) . 'codeplace-content-traffic.php');

    register_activation_hook(__FILE__, array($this,'activate'));
    register_deactivation_hook(__FILE__, array($this,'deactivate'));

    add_action('admin_menu', array($this,'plugin_settings'));
    add_action('init',array($this,'register_meta'));
    add_action('admin_init', array($this,'plugin_redirect'));
    add_action( 'wp_enqueue_scripts', array($this,'codeplace_styles') ,1);

    $curr_ver = get_option($this->plugin_version_key, null);
    if($curr_ver != null && $curr_ver != $this->version)
    {
      $this->activate();
    }


    $codeplace_api = new Codeplace_API_Endpoint();
  }

  /**
  * This the Codeplace activation process.
  * Finally, redirect the user to the Codeplace options panel upon activation.
  */
  public function activate() {
    update_option( $this->redirect_key ,true);
    update_option($this->plugin_version_key ,$this->version);
    if(!$this->update_cp_public_key()) {
      deactivate_plugins(basename(__FILE__)); // Deactivate ourself
      wp_die("Sorry, this plugin could not be activated: it was not possible to connect to Codeplace.");
    }
  }

  /**
  * This is the deactivation process which removes the custom user role and deletes the Codeplace user we created.
  */
  public function deactivate() {
    delete_option( $this->plugin_version_key );
  }

  public function plugin_settings() {
    add_menu_page('Codeplace Settings', 'Codeplace Settings', 'activate_plugins', 'codeplace_settings', 'cp_display_settings',plugins_url('img/codeplace-icon.png', __FILE__ ));
  }

  /**
  * This is plugin redirect hook.  If the redirect option is present, the user is redirected and the option is deleted.
  */
  public function plugin_redirect() {

    if(get_option( $this->redirect_key ))
    {
      delete_option( $this->redirect_key );
      wp_redirect(admin_url('admin.php?page=codeplace_settings'));
    }
  }

  public function register_meta() {
    $remove_stack = array(
      '_cp_license_type',
      '_cp_redirect_location',
      '_cp_canonical',
      '_cp_is_codeplace_post'
    );

    foreach($remove_stack as $key)
      register_meta( 'post', $key , array($this,'sanitize_cb'), array($this,'yes_you_can'));
  }

  public function sanitize_cb ( $meta_value, $meta_key, $meta_type ) {
    return $meta_value;
  }

  public function yes_you_can ( $can, $key, $post_id, $user_id, $cap, $caps ) {
    return true;
  }

  public function update_cp_public_key() {
    $request = wp_remote_post( CP_API.'keys/public', array(
      'method' => 'GET',
      'sslverify' => false
    ));
    if(is_wp_error($request))
      return $request->get_error_message();

    $response = json_decode($request['body']);
    if($response->status == 'success') {
      update_option('cp_public_key',$response->data);
      return true;
    }
    return false;
  }
}

global $codeplace_licensing_plugin;
$codeplace_licensing_plugin = new Codeplace_Licensing_Plugin();
$codeplace_licensing_plugin->bootstrap();
