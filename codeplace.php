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

    // Test if this is necessary!
    // $curr_ver = get_option('cp_plugin_version_number', null);
    // if($curr_ver != null && $curr_ver != $this->version)
    // {
    //   $this->activate();
    // }

    $codeplace_api = new Codeplace_API();
  }

  /**
  * This the Codeplace activation process.
  * Finally, redirect the user to the Codeplace options panel upon activation.
  */
  public function activate() {
    update_option('cp_activation_redirect' ,true);
    update_option('cp_plugin_version_number' ,$this->version);
    if(!$this->update_cp_public_key()) {
      deactivate_plugins(basename(__FILE__)); // Deactivate ourself
      wp_die("Sorry, this plugin could not be activated: it was not possible to connect to Codeplace.");
    }
  }

  /**
  * This is the deactivation process which removes the custom user role and deletes the Codeplace user we created.
  */
  public function deactivate() {
    delete_option( 'cp_plugin_version_number' );
  }

  public function plugin_settings() {
    add_menu_page('Codeplace', 'Codeplace', 'activate_plugins', 'codeplace', 'cp_display_settings', 'data:image/svg+xml;base64,PHN2ZyBpZD0iTGF5ZXJfMSIgZGF0YS1uYW1lPSJMYXllciAxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNDUuNjcgMjgxIj48ZGVmcz48c3R5bGU+LmNscy0xe2ZpbGw6IzFmYmFkYTt9PC9zdHlsZT48L2RlZnM+PHRpdGxlPmNvZGVwbGFjZV9pY29uPC90aXRsZT48cGF0aCBpZD0iRmlsbC0xIiBjbGFzcz0iY2xzLTEiIGQ9Ik0yODguMzIsMzQyLjgyTDE3Ni45MSwyNzguMjNhMTEuMjIsMTEuMjIsMCwwLDAtMTEuMywwTDU0LjE5LDM0Mi44MmExMS4zLDExLjMsMCwwLDAtNS41Nyw5Ljc3VjQ4MS43NmExMS4zOCwxMS4zOCwwLDAsMCw1LjY1LDkuODVMMTY1LjUyLDU1Ni4yYTEyLjkyLDEyLjkyLDAsMCwwLDIuMSwxbDEuNDUsMC40OGExMC41OCwxMC41OCwwLDAsMCwyLjE4LDAsMTEuMjIsMTEuMjIsMCwwLDAsNS42NS0xLjUzbDUuNjUtMy4zMVY0MjMuNjNsNTEuMTktMjkuNzF2NTkuNDJsLTM0LDE5LjdWNDk5LjJsNTAuNy0yOS40N2ExMS4zLDExLjMsMCwwLDAsNS42NS05Ljg1di04NS41YTExLjMsMTEuMywwLDAsMC0xNi44Ny05Ljg1bC03My43OSw0Mi43OWExMS4zLDExLjMsMCwwLDAtNS42NSw5Ljc3djg1LjQyYTIuMjYsMi4yNiwwLDAsMCwwLC40djIzLjc0TDcxLDQ3NS4xNHYtMTE2bDEwMC4zNS01OCwxMDAuMTEsNTh2MTE2LjFsLTcxLjYxLDQxLjV2MjYuMTZsODguODEtNTEuMzVhMTEuMywxMS4zLDAsMCwwLDUuNjUtOS44NVYzNTIuNTlhMTEuMywxMS4zLDAsMCwwLTUuNjUtOS44NSIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoLTQ4LjYyIC0yNzYuNzEpIi8+PHBhdGggaWQ9IkZpbGwtNCIgY2xhc3M9ImNscy0xIiBkPSJNMTQyLjg0LDQ3My4xMmwtMzQtMTkuN1YzODAuNzZsNjIuNDktMzYsMzQsMTkuNywyMi41Mi0xMy4xNkwxNzcsMzIxLjgzYTExLjMsMTEuMywwLDAsMC0xMS4zLDBMOTEuOSwzNjQuNjJhMTEuMywxMS4zLDAsMCwwLTUuNjUsOS44NVY0NjBhMTEuMywxMS4zLDAsMCwwLDUuNjUsOS44NWw1MC45NCwyOS41NVY0NzMuMTJoMFoiIHRyYW5zZm9ybT0idHJhbnNsYXRlKC00OC42MiAtMjc2LjcxKSIvPjwvc3ZnPg==');
  }

  /**
  * This is plugin redirect hook.  If the redirect option is present, the user is redirected and the option is deleted.
  */
  public function plugin_redirect() {

    if(get_option( 'cp_activation_redirect' ))
    {
      delete_option( 'cp_activation_redirect' );
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

global $codeplace_plugin;
$codeplace_plugin = new Codeplace_Plugin();
$codeplace_plugin->bootstrap();
