<?php
/*
 * Plugin Name: WC Recurring Invoice PDF
 * Description: WooCommerce invoice pdf plugin with recurring payments (scheduled)
 * Version: 1.0.0
 * Author: ole1986 <ole.k@web.de>
 * Author URI: https://github.com/ole1986/wc-invoice-pdf
 * Text Domain: wc-invoice-pdf
 */

namespace WCInvoicePdf;

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'WCINVOICEPDF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCINVOICEPDF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once 'menu/invoice-menu.php';
require_once 'menu/account-invoice.php';
require_once 'model/invoice.php';
require_once 'model/invoice-list.php';
require_once 'model/invoice-pdf.php';
require_once 'model/invoice-task.php';
require_once 'metabox/invoice-metabox.php';

add_action('init', ['\WCInvoicePdf\WCInvoicePdf', 'init'] );

register_activation_hook( plugin_basename( __FILE__ ), ['\WCInvoicePdf\WCInvoicePdf', 'install' ] );
register_deactivation_hook(plugin_basename( __FILE__ ), ['\WCInvoicePdf\WCInvoicePdf', 'deactivate' ]);
register_uninstall_hook( plugin_basename( __FILE__ ), ['\WCInvoicePdf\WCInvoicePdf', 'uninstall'] );

class WCInvoicePdf {

    const OPTION_KEY = 'wc-invoice-pdf';

    public static $OPTIONS = [
        'wc_enable' => 0,
        'wc_payment_reminder' => 1,
        'wc_payment_message' => "Dear Administrator,\n\nThe following invoices are not being paid yet: %s\n\nPlease remind the customer(s) for payment",
        'wc_recur' => 0,
        'wc_recur_reminder' => 0,
        'wc_recur_reminder_age' => 2,
        'wc_recur_reminder_interval' => 2,
        'wc_recur_reminder_max' => 2,
        'wc_recur_message' => "Dear Customer,\n\nattached you can find your invoice %s\n\nKind Regards,\n Your hosting Team",
        'wc_recur_reminder_message' => "Dear Customer,\n\nKindly be informed about the attached invoice %s not marked as paid in our system. If your payment has already been sent or remitted please ignore this email\n\nYour hosting Team",
        'wc_recur_test' => 0,
        'wc_mail_sender' => 'Invoice <invoice@domain.tld>',
        'wc_mail_reminder' => 'yourmail@domain.tld',
        'wc_pdf_title' => 'YourCompany - %s',
        'wc_pdf_logo' => '/plugins/wc-invoice-pdf/logo.png',
        'wc_pdf_addressline' => 'Your address in a single line',
        'wc_pdf_condition' => "Some conditional things related to invoices\nLine breaks supported",
        'wc_pdf_info' => 'Info block containing created date here: %s',
        'wc_pdf_block1' => 'BLOCK #1',
        'wc_pdf_block2' => 'BLOCK #2',
        'wc_pdf_block3' => 'BLOCK #3'
    ];

    /**
     * initialize the text domain and load the constructor
     */
    public static function init() {
        self::load_textdomain_file();

        self::load_options();

        // enable changing the due date through ajax
        add_action( 'wp_ajax_invoicepdf', ['\WCInvoicePdf\WCInvoicePdf', 'doAjax'] );
        add_action( 'invoice_reminder', ['\WCInvoicePdf\Model\InvoiceTask', 'Run'] );

        // update the order period
        add_action('wcinvoicepdf_order_period', ['\WCInvoicePdf\WCInvoicePdf', 'order_period'], 10, 2);

        // the rest after this is for NON-AJAX requests
        if(defined('DOING_AJAX') && DOING_AJAX) return;

        // display the customer invoice menu
        new Menu\AccountInvoice();

        if(is_admin()) {
            // used to trigger on invoice creation located in ispconfig_create_pdf.php
            $invoicePdf = new Model\InvoicePdf();
            $invoicePdf->Trigger();
            // display invoice metabox in WC Order
            new Metabox\InvoiceMetabox();
            // display the admin menu
            new Menu\InvoiceMenu();

            add_action( 'admin_enqueue_scripts', ['\WCInvoicePdf\WCInvoicePdf', 'loadJS'] );
        }
    }

    protected static function load_options(){
        $opt = get_option( self::OPTION_KEY );
        if(!empty($opt)) {
            self::$OPTIONS = $opt;
        }
    }

    public static function save_options(){
        return update_option( self::OPTION_KEY, self::$OPTIONS );
    }

    public static function order_period($order_id, $period){
        if(!is_int($order_id)) return false;

        $success = false;

        if(empty($period))
            $success = delete_post_meta( $order_id, '_ispconfig_period');
        else if($period[0] == 'm')
            $success = update_post_meta($order_id, '_ispconfig_period', 'm');
        else if($period[0] == 'y')
            $success = update_post_meta($order_id, '_ispconfig_period', 'y');

        return $success;
    }

    /**
     * load_textdomain_file
     *
     * @access protected
     * @return void
     */
    protected static function load_textdomain_file() {
        # load plugin textdomain
        load_plugin_textdomain('wc-invoice-pdf', false, basename(WCINVOICEPDF_PLUGIN_DIR) . '/lang' );
    }

    public static function addField($name, $title, $type = 'text', $args = []){
        $xargs = [  'container' => 'p', 
                    'required' => false,
                    'attr' => [], 
                    'label_attr' => ['style' => 'width: 220px; display:inline-block;vertical-align:top;'], 
                    'input_attr' => ['style' => 'width: 340px'],
                    'value' => ''
                ];

        if($type == null) $type = 'text';

        foreach ($xargs as $k => $v) {
            if(!empty($args[$k])) $xargs[$k] = $args[$k];
        }

        echo '<' . $xargs['container'];
        foreach ($xargs['attr'] as $k => $v) {
            echo ' '.$k.'="'.$v.'"';
        }
        echo '>';
        echo '<label';
        foreach ($xargs['label_attr'] as $k => $v)
            echo ' '. $k . '="'.$v.'"';

        echo '>';
        _e($title, 'wp-ispconfig3');
        if($xargs['required']) echo '<span style="color: red;"> *</span>';
        echo '</label>';

        $attrStr = '';
        foreach ($xargs['input_attr'] as $k => $v)
            $attrStr.= ' '.$k.'="'.$v.'"';

        if(isset(self::$OPTIONS[$name]))
            $optValue = self::$OPTIONS[$name];
        else
            $optValue = $xargs['value'];

        if($type == 'text' || $type == 'password')
            echo '<input type="'.$type.'" class="regular-text" name="'.$name.'" value="'.$optValue.'"'.$attrStr.' />';
        else if($type == 'email')
            echo '<input type="'.$type.'" class="regular-text" name="'.$name.'" value="'.$optValue.'"'.$attrStr.' />';
        else if($type == 'textarea') 
            echo '<textarea name="'.$name.'" '.$attrStr.'>'  . strip_tags($optValue) . '</textarea>';
        else if($type == 'checkbox')
            echo '<input type="'.$type.'" name="'.$name.'" value="1"' . (($optValue == '1')?'checked':'') .' />';
        else if($type == 'rte') {
            echo '<div '.$attrStr.'>';
            wp_editor($optValue, $name, ['teeny' => true, 'editor_height'=>200, 'media_buttons' => false]);
            echo '</div>'; 
        }           
        echo '</' . $xargs['container'] .'>';
    }

    public static function loadJS(){
        wp_enqueue_script( 'my_custom_script', WCINVOICEPDF_PLUGIN_URL . 'js/wc-invoice-pdf-admin.js?_' . time() );
    }

    public static function doAjax(){
        global $wpdb;
        
        $result = '';
        if(!empty($_POST['invoice_id'])) {
            $invoice = new Model\Invoice(intval($_POST['invoice_id']));
            if(!empty($_POST['due_date']))
                $invoice->due_date = $result = date('Y-m-d H:i:s', strtotime($_POST['due_date']));
            if(!empty($_POST['paid_date']))
                $invoice->paid_date = $result = date('Y-m-d H:i:s', strtotime($_POST['paid_date']));

            $invoice->Save();
        } else if(!empty($_POST['order_id']) && isset($_POST['period'])) {
            $period = esc_attr($_POST['period']);
            do_action('wcinvoicepdf_order_period', intval($_POST['order_id']), $period);
            $result = $period;
        } else if(!empty($_POST['payment_reminder'])) {
            $taskPlaner = new Model\InvoiceTask();
            $result = $taskPlaner->payment_reminder();
        } else if(!empty($_POST['recurr'])) {
            if(!empty(WPISPConfig3::$OPTIONS['wc_recur_test'])) {
                $taskPlaner = new Model\InvoiceTask();
                $result = $taskPlaner->payment_recur();
            } else
                $result = -2;
        } else if(!empty($_POST['recurr_reminder'])) {
            $taskPlaner = new Model\InvoiceTask();
            $result = $taskPlaner->payment_recur_reminder();
        }

        echo json_encode($result);
        wp_die();
    }

    /**
     * installation
     *
     * @access public
     * @static
     * @return void
     */
    public static function install() {
        global $wpdb;

        Model\Invoice::install();

        // setup the role
        // add cap allowing adminstrators to download invoices by default
        $role = get_role('administrator');
        // TODO: Rename the cap accordingly
        $role->add_cap('ispconfig_invoice');

        // install WP schedule to remind due date
        if (! wp_next_scheduled ( 'invoice_reminder' )) {
            // install the invoice reminder schedule which runs on daily bases
	        wp_schedule_event(time(), 'daily', 'invoice_reminder');
        }

        // refresh rewrite rules
        flush_rewrite_rules();
    }

    /**
     * when plugin gets deactivated
     *
     * @access public
     * @static
     * @return void
     */
    public static function deactivate(){
        // turn off the invoice reminder schedule when plugin is disabled
        wp_clear_scheduled_hook('invoice_reminder');
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