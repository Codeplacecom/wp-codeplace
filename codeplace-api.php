<?php
class Codeplace_API {
  /** Hook WordPress
  * @return void
  */
  public function __construct(){
    add_filter('query_vars', array($this, 'add_query_vars'), 0);
    add_action('parse_request', array($this, 'sniff_requests'), 0);
    add_action('init', array($this, 'add_endpoint'), 0);
  }

  /** Add public query vars
  * @param array $vars List of current public query vars
  * @return array $vars
  */
  public function add_query_vars($vars){
    $vars[] = '__cp_api';
    $vars[] = 'method';
    return $vars;
  }

  /** Add API Endpoint
  * This is where the magic happens.  Codeplace API endpoint is: /codeplace_api/[method_name]/
  * @return void
  */
  public function add_endpoint(){
    add_rewrite_rule('^codeplace_api/?(.+)?/?','index.php?__cp_api=1&method=$matches[1]','top');
  }

  /** Sniff Requests
  * This is where we hijack all API requests
  *   If $_GET['__cp_api'] is set, we kill WP and serve up Codeplace.com API awesomeness
  * @return die if API request
  */
  public function sniff_requests(){
    global $wp;
    if(isset($wp->query_vars['__cp_api'])){
      $this->handle_request();
      exit;
    }
  }

  /** Handle Requests
  * This is where we handle all the API requests
  * @return void
  */
  protected function handle_request(){
    global $wp;
    $cp_api_method = $wp->query_vars['method'];

    if(!$cp_api_method)
      $this->send_response('Please define the api method.');

    $valid_method = $this->method_exists($cp_api_method);

    if(!$valid_method)
      $this->send_response('That method could not be found.');

    $request_data = $this->authenticate_request();

    $this->$cp_api_method($request_data);
  }

  /** Check if a method exists
  * This checks to see if the inputted method exists
  * @return true if method exists
  * @return false if method does not eixst
  */
  protected function method_exists($method_name) {
    return method_exists($this,$method_name);
  }

  /** Authenticate Requests
  *   This is where we authenticate the requests.
  *   @return true is valid credentials
  *   @return error if invalid credentials
  */
  protected function authenticate_request() {

    if ($_SERVER['REQUEST_METHOD'] != 'POST')
      $this->send_response('This endpoint accepts POST requests only.');

    if(empty($_REQUEST['data']))
      $this->send_response('data parameter is missing.');

    $data = base64_decode($_REQUEST['data']);
    $cp_public = get_option("cp_public_key");

    if(!openssl_public_decrypt($data, &$decrypted_data, $cp_public))
      $this->send_response('Decryption failed');

    return json_decode($decrypted_data);
  }

  /** Response Handler
  * This sends a JSON response to the browser
  */
  protected function send_response($msg, $data = ''){
    $response['message'] = $msg;
    if($data)
      $response['data'] = $data;
    header('content-type: application/json; charset=utf-8');
      echo json_encode($response)."\n";
      exit;
  }

  /**
  * Setup Codeplace authentication
  */
  protected function complete_registration($data){
    if($data->blog->url == site_url()) {
      update_option('cp_uuid',$data->blog->uuid);
      update_option('cp_user_email',$data->user->email);
      update_option('cp_user_name',$data->user->name);

      $this->send_response('success');
    }
    $this->send_response('wrong url: '.$data->blog->url.' - '.site_url());


    // $cp_public = get_option("cp_public_key");

    // $data = array('domain' => site_url());
    // $data = json_encode($data);
    // openssl_public_encrypt($data, &$encrypted_data, $cp_public);

    // $post_data = array('encrypted' => $encrypted_data);

    // $this->add_endpoint();
    // flush_rewrite_rules();

    // $request = wp_remote_post( CP_API.'connect/wordpress', array(
    //   'method' => 'POST',
    //   'sslverify' => false,
    //   'body' => $post_data
    // ));

    // if(is_wp_error($request))
    //   $this->send_response($request->get_error_message());

    // $response = json_decode($request['body']);

    // if($response->status == 'success') {
    //   $encrypted = base64_decode($response->data);
    //   if(openssl_public_decrypt($encrypted, &$decrypted_data, $cp_public)){

    //     if($decrypted_data->blog->url == site_url()) {
    //       update_option('cp_uuid',$decrypted_data->blog->uuid);
    //       update_option('cp_user_email',$decrypted_data->user->email);
    //       update_option('cp_user_name',$decrypted_data->user->name);

    //       $this->send_response('success');
    //     }
    //     $this->send_response('urls dont match');

    //   }
    //   $this->send_response('decryption failed');
    // }
    // $this->send_response('cp server did not return status success');
  }

  /**
  * This method allows us to check if the blogger has our plugin installed.
  */
  protected function hello($data) {

    $this->send_response('success');
  }

  /**
  * This method returns info on the Codeplace.com WordPress plugin
  */
  protected function plugin_info($data) {

    global $codeplace_plugin;

    $version = get_bloginfo('version');

    $plugin_info['plugin_version'] = get_option('cp_plugin_version_number');
    $plugin_info['uuid'] = get_option("cp_uuid");
    $plugin_info['wp_version'] = get_bloginfo('version');



    $this->send_response('success',$plugin_info);

  }

  /**
  * This method clears the cache on a specific post.
  * Params: post_ID
  */
  protected function clear_post_cache($data) {

    if(empty($data->post_id))
      $this->send_response('Missing post_id param');

    $post_ID = $data->post_id;

    if(function_exists('wp_cache_post_change')) {
      $GLOBALS["super_cache_enabled"]=1;
      wp_cache_post_change($post_ID);
    }

    if(function_exists('hyper_cache_invalidate_post')) {
      hyper_cache_invalidate_post($post_ID);
    }

    if(function_exists('w3tc_pgcache_flush_post')){
      w3tc_pgcache_flush_post($post_ID);
    }

    if(function_exists('w3tc_flush_pgcache_purge_page')){
      w3tc_flush_pgcache_purge_page($post_ID);
    }

    $this->send_response('success',array('post_id' => $post_ID));

  }

  /**
  * This methods creates/updates/deletes a full traffic license.
  * @param temporary (boolean) - optional, default: false
  * @param post_id (integer) - required
  * @param redirect_location (string)
  */
  protected function create_full_traffic_license($data) {

    if(empty($data->post_id))
      $this->send_response('Missing post_id parameter.');

    $post_id = $data->post_id;

    if(empty($data->redirect_location))
      $this->send_response('Missing redirect_location parameter.');

    ($data->temporary === 'true') ? $type = 302 : $type = 1;

    update_post_meta($post_id,'_cp_redirect_location',$data->redirect_location);
    $update = update_post_meta($post_id,'_cp_license_type',$type);

    if($update)
      $this->send_response('success');
    else
      $this->send_response('error');

  }

  protected function delete_full_traffic_license($data) {

    if(empty($data->post_id))
      $this->send_response('Missing post_id parameter.');

    $post_id = $data->post_id;

    delete_post_meta($post_id,'_cp_redirect_location');
    $update = delete_post_meta($post_id,'_cp_license_type');

    if($update)
      $this->send_response('success');
    else
      $this->send_response('error');
  }

  /**
  * This methods returns an array of posts
  * @param args (array)
  */
  protected function get_posts($data) {

    if(empty($data->args))
      $posts = get_posts();
    else
      $posts = get_posts($data->args);

    if($posts)
      foreach($posts as $post) {
        $post->post_author = get_userdata($post->post_author)->display_name;
        $post->processed_content = do_shortcode( wpautop($post->post_content) );
        $post->permalink = get_permalink($post->ID);
      }



    if($posts)
      $this->send_response('success',$posts);
    else
      $this->send_response('error');
  }

  /**
  * This methods creates/updates/deletes a full traffic license.
  * @param post (array)
  * @param status (create|update|delete) - optional, default: create
  */
  protected function draft_post($data) {


    if(empty($data->status))
      $status = 'create';
    else
      $status = $data->status;


    switch($status) {

      case 'create':

        if(empty($data->post))
          $this->send_response('Missing post array.');

        $post = $data->post;
        $post['post_status'] = 'draft';

        $create_post = wp_insert_post($post);

        if(is_wp_error($create_post)) {

          $this->send_response($create_post->get_error_message());

        } else {

          $update = update_post_meta($create_post,'_cp_is_codeplace_post','true');

          $this->send_response('success',array('post_id' => $create_post));
        }


      break;

      case 'update':

        if(empty($data->post))
          $this->send_response('Missing post array.');

        $post = $data->post;

        $check_for_codeplace_post = get_post_meta($post['ID'],'_cp_is_codeplace_post',true);

        if(!$check_for_codeplace_post)
          $this->send_response('This is not a codeplace post.  You cannot edit this post.');

        unset($post['post_status']);

        $update_post = wp_update_post($post);

        if(is_wp_error($create_post)) {

          $this->send_response($update_post->get_error_message());

        } else {

          $update = update_post_meta($update_post,'_cp_is_codeplace_post','true');

          $this->send_response('success',array('post_id' => $update_post));
        }

      break;

      case 'delete':
        if(empty($data->post))
          $this->send_response('Missing post array.');

        $post = $data->post;

        $check_for_codeplace_post = get_post_meta($post['ID'],'_cp_is_codeplace_post',true);

        if(!$check_for_codeplace_post)
          $this->send_response('This is not a codeplace post.  You cannot edit this post.');


        $update = wp_delete_post($post['ID']);


      break;

      default:
        $this->send_response('status not found.');
      break;


    }

    if($update)
      $this->send_response('success');
    else
      $this->send_response('error');

  }

}
