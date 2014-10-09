<?php

/**
 * Plugin Name: Theme Plugin Loader
 * Description: Loads plugins from [themedir]/plugins/, child theme will load parent plugins
 * Version: 0.2
 * Author: Michael Pollett props Sebastien Lavoie, Automattic, GigaOm
 * Author URI: http://www.coreware.co.uk
 */

class ThemePluginLoader
{
 private static $dirname = 'plugins/';
 private static $plugincount = 0;

 public static function init() {
  add_filter( 'plugins_url', array( __CLASS__, 'plugins_url' ), 10, 3 );
  add_action( 'muplugins_loaded', array( __CLASS__, 'load_plugins' ) );

  add_action( 'admin_init', function() {
   add_action( 'after_plugin_row_theme-plugins.php', array( __CLASS__, 'show_loaded_plugins' ) );
   add_filter( 'views_plugins-network', array( __CLASS__, 'update_plugin_count' ) );
   // clear cache when visit admin plugin page
   add_action( 'admin_print_scripts-plugins.php', array( __CLASS__, 'clear_cache' ) );
  });
 }
 
 public static function plugin_dir( $name = '' ){
  $fulldir = trailingslashit( get_template_directory() ) . self::$dirname . $name;
  return substr( $fulldir, strpos( $fulldir, '/themes' ) );
 }
 
 public static function get_theme_plugins(){
  $plugins = get_site_transient( 'theme-plugins-' . self::get_theme() );

  if ( $plugins !== false ) {
   // Validate plugins still exist
   // If not, invalidate cache
   foreach ( $plugins as $plugin_file ) {
    if ( ! is_readable( WP_CONTENT_DIR . self::plugin_dir( $plugin_file ) ) ) {
     $plugins = false;
     break;
    }
   }
   if ( $plugins !== false )
    return $plugins;
  }
 
  if ( ! function_exists( 'get_plugins' ) ) {
   require ABSPATH . 'wp-admin/includes/plugin.php';
  }
 
  $plugins = array();
  foreach ( get_plugins( '/..' . self::plugin_dir() ) as $plugin_file => $data ) {
   if ( dirname( $plugin_file ) != '.' ) { // skip files directly at root
    $plugins[] = $plugin_file;
   }
  }
 
  set_site_transient( 'theme-plugins-' . self::get_theme(), $plugins );
  return $plugins;
 }
 
 public static function clear_cache(){
  delete_site_transient( 'theme-plugins-' . self::get_theme() );
 }

 public static function load_plugins(){
  foreach ( self::get_theme_plugins() as $plugin_file ) {
   $pre_include_variables = get_defined_vars();

   require WP_CONTENT_DIR . self::plugin_dir( $plugin_file );

   $blacklist = array( 'blacklist' => 0, 'pre_include_variables' => 0, 'new_variables' => 0 );
   $new_variables = array_diff_key( get_defined_vars(), $GLOBALS, $blacklist, $pre_include_variables );

   foreach ( $new_variables AS $new_variable => $devnull )
    global $$new_variable;

   extract( $new_variables );
   self::$plugincount++;
  }
 }

 public static function increment_plugin_count( $match ){
  $ret = $match[0] + self::$plugincount;
  return $ret;
 }

 public static function update_plugin_count( $views ){
  $views[ 'mustuse' ] = preg_replace_callback( '/[0-9]+/', array( __CLASS__, 'increment_plugin_count' ), $views['mustuse'] );
  return $views;
 }
 
 public static function show_loaded_plugins() {
 
  foreach ( self::get_theme_plugins() as $plugin_file ) {
            // Strip down version of WP_Plugins_List_Table
 
            $data = get_plugin_data( WP_CONTENT_DIR . self::plugin_dir( $plugin_file ) );
            $name = empty( $data[ 'Name' ] ) ? $plugin_file : $data[ 'Name' ];
            $desc = empty( $data[ 'Description' ] ) ? '&nbsp;' : $data[ 'Description' ];
            $id = sanitize_title( $name );
 
            printf( '
            <tr id="%s" class="active">
                <th scope="row" class="check-column"></th>
                <td class="plugin-title"><strong style="padding-left: 10px;"> - &nbsp;&nbsp;%s</strong></td>
                <td class="column-description desc">
                    <div class="plugin-description"><p>%s</p></div>
                </td>
            </tr>', esc_html( $id ), esc_html( $name ), ( $desc ) );
        }
    }

 public static function get_theme(){
  static $theme_name;
  if( isset( $theme_name ) )
    return $theme_name;
  $theme = wp_get_theme();
  $theme_name = $theme->template;
  return $theme_name;
 }

 /**
  * Filter plugins_url() so that it works for plugins inside the shared VIP plugins directory or a theme directory.
  *
  * Props to the GigaOm dev team for coming up with this method.
  *
  * @param string $url Optional. Absolute URL to the plugins directory.
  * @param string $path Optional. Path relative to the plugins URL.
  * @param string $plugin Optional. The plugin file that you want the URL to be relative to.
  * @return string
  */
 public static function plugins_url( $url = '', $path = '', $plugin = '' ) {
  static $content_dir, $theme_dir, $theme_url;

  if ( ! isset( $content_dir ) ) {
   // Be gentle on Windows, borrowed from core, see plugin_basename
   $content_dir = str_replace( '\\','/', WP_CONTENT_DIR ); // sanitize for Win32 installs
   $content_dir = preg_replace( '|/+|','/', $content_dir ); // remove any duplicate slash
  }

  $theme = wp_get_theme();

  if ( ! isset( $theme_dir ) ) {
   $theme_dir = $content_dir . '/themes/' . self::get_theme();
  }

  if ( ! isset( $theme_url ) ) {
   $theme_url = content_url( '/themes/' . self::get_theme());
  }

  // Don't bother with non-VIP or non-path URLs
  if ( ! $plugin || 0 !== strpos( $plugin, $theme_dir ) ) {
   return $url;
  }

  if ( 0 === strpos( $plugin, $theme_dir ) )
   $url_override = str_replace( $theme_dir, $theme_url, dirname( $plugin ) );
  elseif  ( 0 === strpos( $plugin, get_stylesheet_directory() ) )
   $url_override = str_replace( get_stylesheet_directory(), get_stylesheet_directory_uri(), dirname( $plugin ) );

  if ( isset( $url_override ) )
   $url = trailingslashit( $url_override ) . $path;

  return $url;
  }
}
ThemePluginLoader::init();
