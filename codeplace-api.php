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
    $signature = base64_decode($_REQUEST['signature']);

    $cp_public = get_option("cp_public_key");
    if(openssl_verify($data, $signature, $cp_public, 'SHA256') !== 1)
      $this->send_response('Signature verification failed');

    return json_decode($data); // json_decode($decrypted_data);
  }

  /** Response Handler
  * This sends a JSON response to the browser
  */
  protected function send_response($msg, $data = ''){
    $response['message'] = $msg;
    if($data) {
      // AES
      // Generate a 256-bit encryption key and an initialization vector
      $encryption_key = openssl_random_pseudo_bytes(32);
      $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

      // Encrypt $data using aes-256-cbc cipher
      $data = json_encode($data);
      $encrypted_data = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, OPENSSL_RAW_DATA, $iv);
      $encrypted_data = base64_encode($encrypted_data);
      // end AES

      // RSA
      // Get key
      $cp_public = get_option("cp_public_key");

      // Encrypt secrets
      $secrets = json_encode(array('key' => base64_encode($encryption_key), 'iv' => base64_encode($iv)));
      openssl_public_encrypt($secrets, $encrypted_secrets, $cp_public);
      $encrypted_secrets = base64_encode($encrypted_secrets);
      // end RSA

      // Build response
      $response['data'] = $encrypted_data;
      $response['secret'] = $encrypted_secrets;
    }
    header('content-type: application/json; charset=utf-8');
    echo json_encode($response)."\n";
    exit;
  }

  /**
  * Setup Codeplace authentication
  */
  protected function complete_registration($data){
    if($data->blog->url == site_url() &&
      $data->token == get_option('cp_registration_token')) {
      update_option('cp_uuid',$data->blog->uuid);
      update_option('cp_user_email',$data->user->email);
      update_option('cp_user_name',$data->user->name);

      $blogdata = array('blog' => array(
        'name' => get_bloginfo('name'),
        'description' => get_bloginfo('description'),
        'admin_email' => get_bloginfo('admin_email'),
        'wp_version' => get_bloginfo('version'),
        'plugin_version' => get_option('cp_plugin_version_number'))
      );

      $this->send_response('success', $blogdata);
    }
    $this->send_response('Bad token or url');
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

  protected function get_post_ids($data) {
    $args = array(
        'nopaging' => true
    );
    $latest = new WP_Query($args);
    $data = wp_list_pluck( $latest->posts, 'post_modified_gmt', 'ID' );

    $this->send_response('success',$data);
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
  protected function create_license($data) {

    if(empty($data->post->id))
      $this->send_response('Missing post_id parameter.');

    $post_id = $data->post->id;

    if(empty($data->redirect_location))
      $this->send_response('Missing redirect_location parameter.');

    ($data->temporary === 'true') ? $type = 302 : $type = 301;

    update_post_meta($post_id,'_cp_has_active_licence','true');
    update_post_meta($post_id,'_cp_redirect_location',$data->redirect_location);
    $update = update_post_meta($post_id,'_cp_license_type',$type);

    if($update)
      $this->send_response('success');
    else
      $this->send_response('error');

  }

  protected function delete_license($data) {

    if(empty($data->post->id))
      $this->send_response('Missing post_id parameter.');

    $post_id = $data->post->id;

    delete_post_meta($post_id,'_cp_has_active_licence');
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
    // if(empty($data->ids))
    //   $this->send_response('missing ids');

    $args = array('post__in' => $data->ids);
    $posts = query_posts( $args );

    if($posts) {
      foreach($posts as $post) {
        $post->post_author = get_userdata($post->post_author)->display_name;
        $post->processed_content = do_shortcode( wpautop($post->post_content) );
        $post->permalink = get_permalink($post->ID);
      }
    }
    // var_dump($posts);
    // echo json_encode($posts);
    // exit;
    if($posts)
      $this->send_response('success',$posts);
    else
      $this->send_response('error');
  }
}
