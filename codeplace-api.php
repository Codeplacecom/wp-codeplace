<?php
/* Based off of code by Brian Fegter.  Improved upon by Connor Hood for Codeplace.com (authentication added) */
class Codeplace_API_Endpoint {

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

    $authentication = $this->authenticate_request();

    if($authentication)
      $this->$cp_api_method();
    else
      $this->send_response('Authentication failed.');
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

    if(empty($_REQUEST['username']))
      $this->send_response('username parameter is missing.');

    if(empty($_REQUEST['password']))
      $this->send_response('password parameter is missing.');

    $username = $_REQUEST['username'];
    $password = $_REQUEST['password'];

    $user = get_user_by( 'login', $username );


    if ( $user && wp_check_password( $password, $user->data->user_pass, $user->ID) )
      return true;
    else
      return false;
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
  * This method allows us to check if the blogger has our plugin installed.
  */
  protected function hello() {

    $this->send_response('success');
  }


  /**
  * This method returns info on the Codeplace.com WordPress plugin
  */
  protected function plugin_info() {

    global $codeplace_licensing_plugin;

    $version = get_bloginfo('version');

    $plugin_info['codeplace_version'] = get_option('cp_plugin_version_number');
    $plugin_info['codeplace_user_id'] = get_option("cp_user_id");
    $plugin_info['wp_version'] = $version;
    $plugin_info['piwik_id'] = get_option('cp_tracking_id');
    $plugin_info['cp_analytics_2'] = get_option('cp_analytics_2');


    $this->send_response('success',$plugin_info);

  }

  /**
  * This method updates the two analytics options
  * First option: cp_tracking_id.  This should be the piwik ID, or be empty.
  * Second option: cp_analytics_2.  This should be true or false.  If true, track.codeplace is outputted.
  */
  protected function analytics() {

    $piwik_id = $_POST['piwik_id'];

    if($piwik_id == 'delete')
      delete_option('cp_tracking_id');
    elseif(isset($piwik_id))
      update_option('cp_tracking_id',$piwik_id);


    $cp_analytics = $_POST['analytics_beta'];

    if(isset($cp_analytics))
      update_option('cp_analytics_2',$cp_analytics);


    $this->send_response('success',array('cp_analytics_2' => get_option('cp_analytics_2'),'cp_tracking_id' => get_option('cp_tracking_id')));

  }

  /**
  * This method returns the total number of posts and pages.
  */
  protected function count_posts() {

    $published_posts = wp_count_posts()->publish;
    $published_pages = wp_count_posts('page')->publish;
    $total = $published_pages + $published_posts;

    $this->send_response('success',array('posts' => $published_posts, 'pages' => $published_pages));

  }


  /**
  * This method updates XMLRPC auth on Codeplace api
  */
  protected function update_authentication(){

    global $codeplace_licensing_plugin;
    $send_auth = $codeplace_licensing_plugin->send_auth();

    $this->send_response($send_auth);

  }


  /**
  * This method clears the cache on a specific post.
  * Params: post_ID
  */
  protected function clear_post_cache() {

    if(empty($_REQUEST['post_id']))
      $this->send_response('Missing post_id param');

    $post_ID = $_REQUEST['post_id'];

    if(function_exists('wp_cache_post_change')) {
      $GLOBALS["super_cache_enabled"]=1;
      wp_cache_post_change($post_ID);
    }

    if(function_exists('hyper_cache_invalidate_post')) {
      hyper_cache_invalidate_post($post_ID);
    }

    if (function_exists('w3tc_pgcache_flush_post')){

      w3tc_pgcache_flush_post($post_ID);

    }

    if (function_exists('w3tc_flush_pgcache_purge_page')){

      w3tc_flush_pgcache_purge_page($post_ID);

    }

    $this->send_response('success',array('post_id' => $post_ID));

  }

  /**
  * This method returns a post ID based off the inputted URL
  * Arguments: url
  */
  protected function get_post_id_from_url() {

    if(empty($_REQUEST['url']))
      $this->send_response('Missing url param');

    $post_ID = url_to_postid($_REQUEST['url']);

    if($post_ID)
      $this->send_response('success',array('post_id' => $post_ID));
    else
      $this->send_response('error');
  }

  /**
  * This method returns the post content
  * Arguments: post_ID
  */
  protected function get_post_content() {

    if(empty($_REQUEST['post_id']))
      $this->send_response('Missing post_id parameter.');

    $post_ID = $_REQUEST['post_id'];

    $post_object = get_post( $post_ID );

    if($post_object) {
      $content = do_shortcode( $post_object->post_content );

      $this->send_response('success',array(
        'content' => wpautop($content),
        'post' => $post_object
      ));

    } else {
      $this->send_response('error');
    }

  }

  /**
  * This methods creates/updates/deletes a full traffic license.
  * @param temporary (boolean) - optional, default: false
  * @param post_id (integer) - required
  * @param redirect_location (string) - optional if deleting
  * @param status (create|update|delete) - optional, default: create
  */
  protected function full_traffic_license() {

    if(empty($_REQUEST['post_id']))
      $this->send_response('Missing post_id parameter.');

    if(empty($_REQUEST['status']))
      $status = 'create';
    else
      $status = $_REQUEST['status'];

    $post_id = $_REQUEST['post_id'];

    if(empty($_REQUEST['temporary']))
      $type = 1;
    else
      ($_REQUEST['temporary'] == 'true') ? $type = 4 : $type = 1;

    switch($status) {

      case 'create':

        if(empty($_REQUEST['redirect_location']))
          $this->send_response('Missing redirect_location parameter.');

        update_post_meta($post_id,'_cp_redirect_location',$_REQUEST['redirect_location']);
        $update = update_post_meta($post_id,'_cp_license_type',$type);

      break;

      case 'update':

        if(empty($_REQUEST['redirect_location']))
          $this->send_response('Missing redirect_location parameter.');

        update_post_meta($post_id,'_cp_redirect_location',$_REQUEST['redirect_location']);
        $update = update_post_meta($post_id,'_cp_license_type',$type);

      break;

      case 'delete':

        delete_post_meta($post_id,'_cp_redirect_location');
        $update = delete_post_meta($post_id,'_cp_license_type');

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

  /**
  * This methods returns an array of posts
  * @param args (array)
  */
  protected function get_posts() {

    if(empty($_REQUEST['args']))
      $this->send_response('Missing args array');

    $posts = get_posts($_REQUEST['args']);

    if($posts)
      foreach($posts as $post) {
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
  protected function draft_post() {


    if(empty($_REQUEST['status']))
      $status = 'create';
    else
      $status = $_REQUEST['status'];


    switch($status) {

      case 'create':

        if(empty($_REQUEST['post']))
          $this->send_response('Missing post array.');

        $post = $_REQUEST['post'];
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

        if(empty($_REQUEST['post']))
          $this->send_response('Missing post array.');

        $post = $_REQUEST['post'];

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
        if(empty($_REQUEST['post']))
          $this->send_response('Missing post array.');

        $post = $_REQUEST['post'];

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
