<?php

/*
  Plugin Name: Airsuggest for Store
  Plugin URI:  https://wordpress.org/plugins/woo-airsuggest/
  Description: This Plugin provides you feature for custom checkout option for all types of discount.
  Version:     1.3
  Author:      Ronak Joshi
  Author URI:  https://twitter.com/RonakJoshiio
  License:     GPL2
  License URI: https://www.gnu.org/licenses/gpl-2.0.html
  WC requires at least: 2.0.0
  WC tested up to: 3.6.4
 */

add_action('plugins_loaded', 'wpl_airsuggest_init_gateway', 0);

/**
 * wpl_airsuggest_init_gateway function.
 *
 * @description Initializes the gateway.
 * @access public
 * @return void
 */
function wpl_airsuggest_init_gateway() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_REQUEST['blogkey'])) {
        $_SESSION['blogkey'] = trim(strip_tags($_REQUEST['blogkey']));
//        echo "Key=".$_SESSION['blogkey'];
    }
    
//    echo"<pre>";
//    print_r($_REQUEST);
//    print_r($_SESSION);
//    echo"</pre>";
//    echo "status=" . session_status();
    // If the WooCommerce payment gateway class is not available nothing will return
    if (!class_exists('WC_Payment_Gateway'))
        return;

    // WooCommerce payment gateway class to hook Payment gateway
    require_once( plugin_basename('lab-inc/pay.functions.php') );

    ///////// Add to woocommerce gateway wpl_add_airsuggest_gateway 

    add_filter('woocommerce_payment_gateways', 'wpl_add_airsuggest_gateway');

    function wpl_add_airsuggest_gateway($methods) {
        $methods[] = 'Wpl_airsuggest_WC';
        return $methods;
    }

}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wpl_airsuggest_add_action_links');

function wpl_airsuggest_add_action_links($links) {
    $mylinks = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wpl_airsuggest') . '"><b>Settings</b></a>'
    );
    return array_merge($mylinks, $links);
}

//////// Uninstall full plugins from folder and data from database
if (function_exists('register_uninstall_hook'))
    register_uninstall_hook(__FILE__, 'wpl_airsuggest_uninstall');

function wpl_airsuggest_uninstall() {
    delete_option('woocommerce_wpl_airsuggest');
    delete_option('woocommerce_wpl_airsuggest_settings');
}
