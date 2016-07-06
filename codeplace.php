<?php
/*
Plugin Name: Codeplace
Plugin URI: http://www.codeplace.com/
Description: Cool!
Version: 0.0.1
Author: Codeplace
Author URI: http://www.codeplace.com
*/

define("CP_API", "http://localhost/api/", true);
define("CP_EMAIL", "api@codeplace.com", true);
define("CP_USER", "codeplaceapi_", true);

class Codeplace_Licensing_Plugin {

  var $version = '3.0.8';

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

    if(get_option( $this->plugin_version_key ) != $this->version)
    {
      $this->activate();
    }


    $codeplace_api = new Codeplace_API_Endpoint();
  }

  /**
  * This the Codeplace.com activation process.
  * In this activation process, a role called Codeplace User is created.
  * This user allows us to interact with only the posts that you choose to license through Codeplace.
  * This also remove any previously added API key.  API key not needed.
  * Finally, redirect the user to the Codeplace options panel upon activation.
  */
  public function activate() {

    remove_role('cp_user');
    $result = add_role(
      'cp_user',
      'Codeplace User',
      array(
        'delete_pages'   => false,
        'delete_published_posts' => false,
        'delete_posts'   => false,
        'edit_pages'   => false,
        'edit_posts'   => false,
        'read'       => false,
        'publish_posts'  => false,
        'publish_pages'  => false,
        'edit_others_posts' => false,
        'edit_published_posts' => false,
        'edit_others_pages' => false,
        'edit_published_pages' => false,
        'upload_files' => false,
      )
    );

    add_option( $this->redirect_key ,true);
    update_option($this->plugin_version_key ,$this->version);
  }

  /**
  * This is the deactivation process which removes the custom user role and deletes the Codeplace user we created.
  */
  public function deactivate() {
    remove_role( 'cp_user' );
    wp_delete_user( get_option("cp_user_id") );

    delete_option('cp_tracking_id');
    delete_option('cp_api_key');
    delete_option('cp_email');
    delete_option( $this->plugin_version_key );
  }



  public function plugin_settings() {

    add_menu_page('Codeplace Settings', 'Codeplace Settings', 'activate_plugins', 'codeplace_settings', 'cp_display_settings',plugins_url('img/codeplace-icon.png', __FILE__ ));

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


  /**
  * This adds the Codeplace takeover stylesheet to the head of all pages.
  */
  public function codeplace_styles() {
    wp_register_style('cp_takeover_css', plugins_url('css/codeplace-template.css',__FILE__ ));
  }

  /**
  * This creates the XMLRPC user and returns the username and password as an array.
  * @return array with user_login,user_password values
  */
  public function create_xml_user() {

    $wp_username = CP_USER.rand(1000, 9999);

    $user_id = username_exists( $wp_username );
    $random_password = wp_generate_password( 12, false );

    if ( !$user_id  and email_exists(CP_EMAIL) == false ) {

      $user_id = wp_insert_user(
        array (
          'user_login' => $wp_username,
          'user_email' => CP_EMAIL,
          'user_pass'  => $random_password,
          'role'     => 'cp_user'
        )
      );
      update_option("cp_user_id", $user_id);

      $output = array(
        'user_login' => $wp_username,
        'user_password' => $random_password
      );

    }  else {
      // this means that we already have a cp_user and just need to change password and resend.
      update_option("cp_user_id", email_exists(CP_EMAIL));
      $user_data = get_userdata(get_option('cp_user_id'));

      wp_update_user(array(
        'ID' => get_option('cp_user_id'),
        'user_pass' => $random_password
      ));
      $output = array(
        'user_login' => $user_data->user_login,
        'user_password' => $random_password
      );

    }

    return $output;
  }

  /**
  * This function sends the username and password to the codeplace api.
  * @return 'success' or 'error'
  */
  public function send_auth(){
    $domain = site_url();
    $create_user = $this->create_xml_user();

    $email = $_POST['cp_email'];

    $data = array(
      'domain' => $domain,
      'codeplace_username' => $create_user['user_login'],
      'codeplace_password' => $create_user['user_password']
    );

    if($email) {
      $data['email'] = $email;
    }


    $codeplace_api = new Codeplace_API_Endpoint();
    $codeplace_api->add_endpoint();
    flush_rewrite_rules();

    $request = wp_remote_post( CP_API.'blogs/plugin_install', array(
      'method' => 'POST',
      'sslverify' => false,
      'body' => $data
    ));



    if(is_wp_error($request))
      return $request->get_error_message();

    /* maybe need more error handling here */
    $response = json_decode($request['body']);

    if($response->status == 'success') {
      update_option('cp_email',$response->blog_user_email);
      return 'success';
    }


    return 'Something went wrong.  Please try again.';

  }

}


global $codeplace_licensing_plugin;
$codeplace_licensing_plugin = new Codeplace_Licensing_Plugin();
$codeplace_licensing_plugin->bootstrap();
