<?php
/*
Plugin Name:       Easy WPML Copy
Description:       Easier way to copy/duplicate WPML posts between different site languages
Version:           0.1
Author:            Paul Huisman
Author URI:        https://www.paulhuisman.com/
License:           GPLv2 or later
*/

include(__DIR__ . '/src/Plugin.php');

add_action( 'plugins_loaded', 'e_wpml_initialize_plugin' );
function e_wpml_initialize_plugin() {
  new \EasyWpmlCopy\Plugin;
}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'ez_wpml_duplicate_connect_action_links' );
function ez_wpml_duplicate_connect_action_links( $links ) {
  $links[] = '<a href="/wp-admin/admin.php?page=easy-wpml-copy">Plugin</a>';
  return $links;
}
