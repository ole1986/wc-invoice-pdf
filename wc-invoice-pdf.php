<?php
/*
 * Plugin Name: WC Invoice PDF
 * Description: WooCommerce invoice pdf plugin with recurring payments (scheduled)
 * Version: 1.0.0
 * Author: ole1986 <ole.k@web.de>
 * Author URI: https://github.com/ole1986/wc-invoice-pdf
 * Text Domain: wc-invoice-pdf
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if(class_exists( 'WCInvoicePdf' ) ) exit;

define( 'WCINVOICEPDF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCINVOICEPDF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action('init', array( 'WCInvoicePdf', 'init' ) );

register_activation_hook( plugin_basename( __FILE__ ), array( 'WCInvoicePdf', 'install' ) );
register_deactivation_hook(plugin_basename( __FILE__ ), array( 'WCInvoicePdf', 'deactivate' ));
register_uninstall_hook( plugin_basename( __FILE__ ), array( 'WCInvoicePdf', 'uninstall' ) );

class WCInvoicePdf {
    /**
     * installation
     *
     * @access public
     * @static
     * @return void
     */
    public static function install() {
        // run the installer if ISPConfig invoicing module (if available)
        
    }

    /**
     * when plugin gets deactivated
     *
     * @access public
     * @static
     * @return void
     */
    public static function deactivate(){
        // run the deactivate method from ISPConfig invoicing module (if available)
        
    }

    /**
        * uninstallation
        *
        * @access public
        * @static
        * @global $wpdb, $blog_id
        * @return void
        */
    public static function uninstall() {
        
    }
}