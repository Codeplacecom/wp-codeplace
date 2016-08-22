<?php
/*
Plugin Name: Codeplace
Plugin URI: http://www.codeplace.com/
Description: Cool!
Version: 0.0.1
Author: Codeplace
Author URI: http://www.codeplace.com
*/

define("CP_API", "http://codeplace-core.herokuapp.com//v1/", true);// http://api.codeplace.com/v1/

class Codeplace_Plugin {
  var $version = '0.0.1';

  public function bootstrap() {
    /* Codeplace Options Panel */
    require_once(plugin_dir_path( __FILE__ ) . 'codeplace-settings.php');

    /* Codeplace API */
    require_once(plugin_dir_path( __FILE__ ) . 'codeplace-api.php');

    /* Codeplace License Types Functionality */
    require_once(plugin_dir_path( __FILE__ ) . 'codeplace-content-traffic.php');

    register_activation_hook(__FILE__, array($this,'activate'));
    register_deactivation_hook(__FILE__, array($this,'deactivate'));

    add_action('admin_menu', array($this,'plugin_settings'));
    add_action('init',array($this,'register_meta'));
    add_action('admin_init', array($this,'plugin_redirect'));

    add_action('activated_plugin','save_error');

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'my_plugin_action_links' );
    function my_plugin_action_links( $links ) {
      $links[] = '<a href="'. menu_page_url("codeplace", false) .'">Settings</a>';
      $links[] = '<a href="http://writers.codeplace.com" target="_blank">Visit Codeplace</a>';
      return $links;
    }

    $codeplace_api = new Codeplace_API();
  }

  /**
  * This the Codeplace activation process.
  * Finally, redirect the user to the Codeplace options panel upon activation.
  */
  public function activate() {
    if(!$this->update_cp_public_key()) {
      deactivate_plugins(basename(__FILE__)); // Deactivate ourself
      wp_die("Sorry, this plugin could not be activated: it was not possible to connect to Codeplace.");
    }
    update_option('cp_activation_redirect' ,true);
    update_option('cp_plugin_version_number' ,$this->version);
  }

  public function save_error(){
    update_option('cp_activation_error', ob_get_contents());
  }

  /**
  * This is the deactivation process which removes the custom user role and deletes the Codeplace user we created.
  */
  public function deactivate() {
    if($this->number_of_active_licences() > 0){
      wp_die('<b style="font-size: 1.5em;">You can\'t deactivate this plugin!</b></p>
        <p>You currently have licenced post on Codeplace.</p>
        <p>Deactivating the Codeplace plugin would violate your agreement with codeplace.</p>
        <p>If you need help visit our <a href="http://writers.codeplace.com/support">support page</a>.</p>
        <p><a href="http://writers.codeplace.com">Go to Codeplace</a> | <a href="'.menu_page_url("codeplace", false).'">Codeplace plugin settings</a>',
        "Codeplace Plugin Deactivation", array('back_link' => true));
    } else {
      $this->send_deactivation_notice();
      delete_option('cp_plugin_version_number');
      delete_option('cp_activation_redirect');
      delete_option('cp_public_key');
      delete_option('cp_uuid');
      delete_option('cp_user_email');
      delete_option('cp_user_name');
    }
    // metas?
  }

  public function plugin_settings() {
    add_menu_page('Codeplace', 'Codeplace', 'activate_plugins', 'codeplace', 'cp_display_settings', 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA4Ni42NSA5OS4xMSI+PGRlZnM+PHN0eWxlPi5jbHMtMXtmaWxsOiNhMGE1YWE7fTwvc3R5bGU+PC9kZWZzPjx0aXRsZT5Bc3NldCAxPC90aXRsZT48ZyBpZD0iTGF5ZXJfMiIgZGF0YS1uYW1lPSJMYXllciAyIj48ZyBpZD0iTGF5ZXJfMS0yIiBkYXRhLW5hbWU9IkxheWVyIDEiPjxwYXRoIGlkPSJGaWxsLTEiIGNsYXNzPSJjbHMtMSIgZD0iTTg0LjU0LDIzLjMzLDQ1LjI1LjU0YTQsNCwwLDAsMC00LDBMMiwyMy4zM2E0LDQsMCwwLDAtMiwzLjQ0VjcyLjMyYTQsNCwwLDAsMCwyLDMuNDZMNDEuMjMsOTguNTdhNC41NSw0LjU1LDAsMCwwLC43NC4zNWwuNTEuMTdhMy43MywzLjczLDAsMCwwLC43NywwLDQsNCwwLDAsMCwyLS41NGwyLTEuMTVWNTEuODJMNjUuMyw0MS4zNHYyMWwtMTIsNi45MnY5LjIzTDcxLjIsNjguMDdhNCw0LDAsMCwwLDItMy40NlYzNC40NUE0LDQsMCwwLDAsNjcuMjQsMzFsLTI2LDE1LjA5YTQsNCwwLDAsMC0yLDMuNDZ2MzAuMWEuNzkuNzksMCwwLDAsMCwuMTR2OC4zOEw3Ljg4LDcwVjI5LjA3TDQzLjI4LDguNjIsNzguNTksMjkuMDhWNzBMNTMuMzQsODQuNjR2OS4yM0w4NC42Niw3NS43OWE0LDQsMCwwLDAsMi0zLjQ2VjI2Ljc2YTQsNCwwLDAsMC0yLTMuNDYiLz48cGF0aCBpZD0iRmlsbC00IiBjbGFzcz0iY2xzLTEiIGQ9Ik0zMy4yMyw2OS4yN2wtMTItNi45NVYzNi43bDIyLTEyLjcsMTIsNi45Miw3Ljk0LTQuNjRMNDUuMjgsMTUuOWE0LDQsMCwwLDAtNCwwTDE1LjI3LDMxYTQsNCwwLDAsMC0yLDMuNDZWNjQuNjNhNCw0LDAsMCwwLDIsMy40NmwxOCwxMC40MlY2OS4yN1oiLz48L2c+PC9nPjwvc3ZnPg==');
  }

  /**
  * This is plugin redirect hook.  If the redirect option is present, the user is redirected and the option is deleted.
  */
  public function plugin_redirect() {

    if(get_option('cp_activation_redirect'))
    {
      delete_option('cp_activation_redirect');
      wp_redirect(menu_page_url("codeplace", false));
    }
  }

  public function register_meta() {
    $remove_stack = array(
      '_cp_has_active_licence',
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
    $request = wp_remote_get(CP_API.'keys/public', array(
      'sslverify' => true
    ));
    if(is_wp_error($request))
      return false;

    $response = json_decode($request['body']);
    if($response->status == 'success') {
      update_option('cp_public_key',$response->data);
      return true;
    }
    return false;
  }

  public function generate_registration_token() {
    $token = base64_encode(random_bytes(32));
    update_option('cp_registration_token', $token);
    return $token;
  }

  public function send_deactivation_notice()
  {

    $uuid = get_option('cp_uuid');
    $cp_public = get_option("cp_public_key");

    $data = array('blog' => array('uuid' => get_option('cp_uuid')));
    $data = json_encode($data);
    openssl_public_encrypt($data, $encrypted_data, $cp_public);
    $encrypted_data = base64_encode($encrypted_data);

    $post_data = array('data' => $encrypted_data);

    $request = wp_remote_post(CP_API.'disconnect/wordpress', array(
      'method' => 'DELETE',
      'sslverify' => true,
      'body' => $post_data
    ));
    if(is_wp_error($request))
      return $request->get_error_message();

    $response = json_decode($request['body']);
    if($response->status !== 'success') {
      update_option('cp_failed_deactivation_notice', $uuid);
    }
  }

  public function number_of_active_licences()
  {
    $query = new WP_Query( array( 'meta_key' => '_cp_has_active_licence', 'meta_value' => 'true' ) );
    return $query->found_posts;
  }
}

global $codeplace_plugin;
$codeplace_plugin = new Codeplace_Plugin();
$codeplace_plugin->bootstrap();
