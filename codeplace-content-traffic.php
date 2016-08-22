<?php

/**
* This is the content and traffic license functionality.
* http://codeplace.com/content-licensing/
*/
function cp_redirect_license(){
  global $post;
  if(is_singular()){
    $license_type = get_post_meta($post->ID, '_cp_license_type', true);
    if($license_type == '301' || $license_type == '302'){

      $redirect_url = get_post_meta($post->ID, '_cp_redirect_location', true);

      /* make sure there is no cache being generated on these pages so that the redirect stays put */
      if($redirect_url) {

        cp_w3tc_force_cache_empty();

        define('DONOTCACHEPAGE',true);

        global $hyper_cache_stop;

        $hyper_cache_stop = true;

        if($license_type == '301')
          wp_redirect($redirect_url, 301);

        if($license_type == '302')
          wp_redirect($redirect_url, 302);
      }

    }
  }
}
add_action('template_redirect', 'cp_redirect_license',1);


/**
* This function clears out the annoying .old file that the W3TC plugin developers forgot to delete.
*/
function cp_w3tc_force_cache_empty() {

  if(defined('W3TC_CACHE_PAGE_ENHANCED_DIR'))
  {
    $cache_route = W3TC_CACHE_PAGE_ENHANCED_DIR.'/'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'].'_index.html.old';

    if(file_exists($cache_route)) unlink($cache_route);
  }

  if(defined('W3TC_CACHE_PAGE_ENHANCED_DIR'))
  {
    $cache_route_2 = W3TC_CACHE_PAGE_ENHANCED_DIR.'/'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'].'_index.html_gzip.old';

    if(file_exists($cache_route_2)) unlink($cache_route_2);
  }
}
