<?php
/*
 * Plugin Name: WC Recurring Invoice PDF
 * Description: WooCommerce invoice pdf plugin with recurring payments (scheduled)
 * Version: 1.5.23
 * Author: ole1986 <ole.koeckemann@gmail.com>
 * Author URI: https://github.com/ole1986/wc-invoice-pdf
 * Plugin URI: https://github.com/ole1986/wc-invoice-pdf/releases
 * Text Domain: wc-invoice-pdf
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 8.9
 */

namespace WCInvoicePdf;

defined('ABSPATH') or die('No script kiddies please!');

define('WCINVOICEPDF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCINVOICEPDF_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once 'menu/invoice-menu.php';
require_once 'menu/account-invoice.php';
require_once 'model/invoice.php';
require_once 'model/invoice-list.php';
require_once 'model/invoice-export.php';
require_once 'model/invoice-pdf.php';
require_once 'model/invoice-task.php';
require_once 'wc/wc_order_invoice_metabox.php';

add_action('init', ['\WCInvoicePdf\WCInvoicePdf', 'init']);
add_action('upgrader_process_complete', ['\WCInvoicePdf\WCInvoicePdf', 'upgrade'], 10, 2);
add_action('admin_notices', ['\WCInvoicePdf\WCInvoicePdf', 'migrate'], 10, 2);

add_filter('plugin_row_meta', array( '\WCInvoicePdf\WCInvoicePdf', 'plugin_meta' ), 10, 2);

register_activation_hook(plugin_basename(__FILE__), ['\WCInvoicePdf\WCInvoicePdf', 'install' ]);
register_deactivation_hook(plugin_basename(__FILE__), ['\WCInvoicePdf\WCInvoicePdf', 'deactivate' ]);
register_uninstall_hook(plugin_basename(__FILE__), ['\WCInvoicePdf\WCInvoicePdf', 'uninstall']);

class WCInvoicePdf
{

    const OPTION_KEY = 'wc-invoice-pdf';

    public static $OPTIONS = [
        'wc_payment_reminder' => 1,
        'wc_payment_message' => "Dear Administrator,\n\nThe following invoices are not being paid yet: %s\n\nPlease remind the customer(s) for payment",
        'wc_recur' => 0,
        'wc_recur_reminder' => 0,
        'wc_recur_reminder_age' => 2,
        'wc_recur_reminder_interval' => 2,
        'wc_recur_reminder_max' => 2,
        'wc_recur_message' => "Dear {CUSTOMER_NAME},\n\nattached you can find your invoice {INVOICE_NO}\nPlease pay until {DUE_DATE}\n\nKind Regards,\nYour Billing Team",
        'wc_recur_reminder_message' => "Dear {CUSTOMER_NAME},\n\nKindly be informed about the attached invoice {INVOICE_NO} not marked as paid in our system.\nPlease pay the invoice until the next {NEXT_DUE_DAYS} days.\n\nIf your payment has already been sent or remitted please ignore this email\n\nYour Billing Team",
        'wc_recur_test' => 0,
        'wc_mail_sender' => 'Invoice <invoice@domain.tld>',
        'wc_mail_reminder' => 'yourmail@domain.tld',
        'wc_pdf_title' => 'YourCompany - %s',
        'wc_invoice_due_days' => 14,
        'wc_pdf_logo' => '/plugins/wc-invoice-pdf/logo.png',
        'wc_pdf_addressline' => 'Your address in a single line',
        'wc_pdf_condition' => "Payment within %s days after invoice date.",
        'wc_pdf_condition_offer' => "This offer is valid for 2 weeks",
        'wc_pdf_info' => 'Info block containing created date here: %s',
        'wc_pdf_block1' => 'BLOCK #1',
        'wc_pdf_block2' => 'BLOCK #2',
        'wc_pdf_block3' => 'BLOCK #3',
        'wc_export_locale' => 'de_DE',
        'wc_export_notes' => 'Rechnung %s',
        'wc_export_account' => 'Erlöse u. Erträge 2/8:Erlöskonten 8:8400 Erlöse USt. 19%',
        'wc_export_account_posted' => 'Aktiva:Finanzkonten 1:1400 Ford. a. Lieferungen und Leistungen',
        'wc_export_account_tax' => 'Umsatzsteuer',
        'wc_order_subscriptions' => '',
        'wc_ispconfig_client_template' => 0
    ];

    public static $SUBSCRIPTIONS = [];

    /**
     * initialize the text domain and load the constructor
     */
    public static function init()
    {
        self::load_textdomain_file();

        self::$SUBSCRIPTIONS = ['m' => __('monthly', 'wc-invoice-pdf'), 'y' => __('yearly', 'wc-invoice-pdf') ];

        if (file_exists(WCINVOICEPDF_PLUGIN_DIR . 'wc/wc_product.php')) {
            include_once WCINVOICEPDF_PLUGIN_DIR . 'wc/wc_product.php';
            include_once WCINVOICEPDF_PLUGIN_DIR . 'wc/wc_product_webspace.php';
            include_once WCINVOICEPDF_PLUGIN_DIR . 'wc/wc_product_service.php';
        }

        self::load_options();

        // enable changing the due date through ajax
        add_action('wp_ajax_Invoice', ['\WCInvoicePdf\Model\Invoice', 'DoAjax']);
        // enable ajax requests for invoice tasks (admin-only)
        add_action('wp_ajax_InvoiceTask', ['WCInvoicePdf\Model\InvoiceTask', 'DoAjax']);
        // enable ajax request for the invoice metabox located in the edit order post
        add_action('wp_ajax_InvoiceMetabox', ['WCInvoicePdf\Metabox\InvoiceMetabox', 'DoAjax']);

        // register the scheduler action being used by wp_schedule_event
        add_action('invoice_reminder', ['\WCInvoicePdf\Model\InvoiceTask', 'Run']);

        // update the order period
        add_action('wcinvoicepdf_order_period', ['\WCInvoicePdf\WCInvoicePdf', 'order_period'], 10, 2);

        // the rest after this is for NON-AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // display the customer invoice menu
        new Menu\AccountInvoice();

        if (is_admin()) {
            // display invoice metabox in WC Order
            new Metabox\InvoiceMetabox();
            // display the admin menu
            new Menu\InvoiceMenu();

            add_action('admin_enqueue_scripts', ['\WCInvoicePdf\WCInvoicePdf', 'loadJS']);
        }
    }

    protected static function load_options()
    {
        $opt = get_option(self::OPTION_KEY);
        if (!empty($opt)) {
            self::$OPTIONS = array_replace(self::$OPTIONS, $opt);
        }
    }

    public static function save_options()
    {
        return update_option(self::OPTION_KEY, self::$OPTIONS);
    }

    public static function order_period($order_id, $period)
    {
        if (!is_int($order_id)) {
            return false;
        }

        $success = false;

        if (empty($period)) {
            $success = delete_post_meta($order_id, '_ispconfig_period');
        } else {
            $success = update_post_meta($order_id, '_ispconfig_period', \strtolower($period[0]));
        }

        return $success;
    }

    /**
     * load_textdomain_file
     *
     * @access protected
     * @return void
     */
    protected static function load_textdomain_file()
    {
        # load plugin textdomain
        load_plugin_textdomain('wc-invoice-pdf', false, basename(WCINVOICEPDF_PLUGIN_DIR) . '/lang');
    }

    public static function addField($name, $title, $type = 'text', $args = [])
    {
        $xargs = [  'container' => 'p',
                    'required' => false,
                    'attr' => [],
                    'label_attr' => ['style' => 'width: 220px; display:inline-block;vertical-align:top;'],
                    'input_attr' => ['style' => 'width: 340px'],
                    'value' => ''
                ];

        if ($type == null) {
            $type = 'text';
        }

        foreach ($xargs as $k => $v) {
            if (!empty($args[$k])) {
                $xargs[$k] = $args[$k];
            }
        }

        if ($type == 'media') {
            $xargs['container'] = 'div';
        }

        echo '<' . $xargs['container'];
        foreach ($xargs['attr'] as $k => $v) {
            echo ' '.$k.'="'.$v.'"';
        }
        echo '>';
        echo '<label';
        foreach ($xargs['label_attr'] as $k => $v) {
            echo ' '. $k . '="'.$v.'"';
        }

        echo '>';
        _e($title, 'wp-ispconfig3');
        if ($xargs['required']) {
            echo '<span style="color: red;"> *</span>';
        }
        echo '</label>';

        $attrStr = '';
        foreach ($xargs['input_attr'] as $k => $v) {
            $attrStr.= ' '.$k.'="'.$v.'"';
        }

        if (isset(self::$OPTIONS[$name])) {
            $optValue = self::$OPTIONS[$name];
        } else {
            $optValue = $xargs['value'];
        }

        if ($type == 'text' || $type == 'password' || $type == 'number') {
            echo '<input type="'.$type.'" class="regular-text" name="'.$name.'" value="'.$optValue.'"'.$attrStr.' />';
        } elseif ($type == 'email') {
            echo '<input type="'.$type.'" class="regular-text" name="'.$name.'" value="'.$optValue.'"'.$attrStr.' />';
        } elseif ($type == 'textarea') {
            echo '<textarea name="'.$name.'" '.$attrStr.'>'  . esc_attr($optValue) . '</textarea>';
        } elseif ($type == 'checkbox') {
            echo '<input type="'.$type.'" name="'.$name.'" value="1"' . (($optValue == '1')?'checked':'') .' />';
        } elseif ($type == 'rte') {
            echo '<div '.$attrStr.'>';
            wp_editor($optValue, $name, ['teeny' => true, 'editor_height'=>200, 'media_buttons' => false]);
            echo '</div>';
        } elseif ($type == 'media') {
            wp_enqueue_media();
            $url = '';
            if (intval($optValue) > 0) {
                $url = wp_get_attachment_url($optValue);
            }
            echo "<div class='image-preview-wrapper' style='display:inline-block;'>";
            echo "<img id='$name-preview' src=\"$url\" style='max-height: 100px;'><br />";
            echo "<input onclick=\"WCInvoicePdfAdmin.OpenMedia(this,'$name')\" type=\"button\" class=\"button\" value=\"" . __('Select image', 'wc-invoice-pdf') ."\" />";
            echo "<input onclick=\"WCInvoicePdfAdmin.ClearMedia(this,'$name')\" type=\"button\" class=\"button\" value=\"" . __('Clear image', 'wc-invoice-pdf') ."\" />";
            echo "<input type='hidden' name=\"".$name."\" id='$name' value=\"$optValue\" />";
            echo "</div>";
        }
        echo '</' . $xargs['container'] .'>';
    }

    public static function loadJS()
    {
        wp_enqueue_script('my_custom_script', WCINVOICEPDF_PLUGIN_URL . 'js/wc-invoice-pdf-admin.js?_' . time());
    }

    public static function plugin_meta($links, $file)
    {
        $l = strlen($file);

        if (substr(__FILE__, -$l) == $file) {
            $row_meta = array(
                'bug'    => '<a href="https://github.com/ole1986/wc-invoice-pdf/issues" style="color: #a00" target="_blank">Report Bug</a>',
                'donate'    => '<a href="https://www.paypal.com/cgi-bin/webscr?item_name=Donation+WC+Recurring+Invoice+Pdf&cmd=_donations&business=ole.k@web.de" target="_blank"><span class="dashicons dashicons-heart"></span> Donate</a>'
            );
            return array_merge($links, $row_meta);
        }
        return (array) $links;
    }

    public static function migrate()
    {
        global $wpdb;

        if (!get_transient('wc-invoice-pdf-migrate')) {
            return;
        }

        // increase when something to migration
        $migrationIncrement = 4;
        $plugin = get_plugin_data(__FILE__);
       
        // migration
        $version = intval(get_option('wc-invoice-pdf-version', $migrationIncrement));
        
        if ($version < $migrationIncrement) {
            $wpdb->query("UPDATE $wpdb->terms SET name = 'service', slug = 'service' WHERE term_id IN (SELECT t.term_id FROM $wpdb->terms t LEFT JOIN $wpdb->term_taxonomy tt ON (t.term_id = tt.term_id) WHERE name = 'hour')");
            $wpdb->query("UPDATE $wpdb->postmeta SET meta_key = '_qty_suffix', meta_value = 'min' WHERE meta_id IN (SELECT meta_id FROM $wpdb->postmeta pm LEFT JOIN $wpdb->posts p ON p.ID = pm.post_id WHERE p.post_type = 'product' AND pm.meta_key = '_hour_useminute' AND pm.meta_value = 'yes')");
            
            update_option('wc-invoice-pdf-version', $migrationIncrement);
            echo '<div class="notice notice-success"><p><strong>' . $plugin['Name'] .':</strong> Successfully migrated service product_type</p></div>';
        }

        delete_transient('wc-invoice-pdf-migrate');
    }

    public static function upgrade($upgrader_object, $options)
    {
        // The path to our plugin's main file
        $our_plugin = plugin_basename(__FILE__);
        // If an update has taken place and the updated type is plugins and the plugins element exists
        if ($options['action'] == 'update' && $options['type'] == 'plugin' && isset($options['plugins']) && \in_array($our_plugin, $options['plugins'])) {
            set_transient('wc-invoice-pdf-migrate', 1);
        }
    }

    /**
     * installation
     *
     * @access public
     * @static
     * @return void
     */
    public static function install()
    {
        set_transient('wc-invoice-pdf-migrate', 1);

        Model\Invoice::install();
        // setup the role
        // add cap allowing adminstrators to download invoices by default
        $role = get_role('administrator');
        $role->add_cap('wc_invoice_pdf');

        // add woocommerce customer role to the correct cap
        $role = get_role('customer');
        $role->add_cap('wc_invoice_pdf');

        // install WP schedule to remind due date
        if (! wp_next_scheduled('invoice_reminder')) {
            // install the invoice reminder schedule which runs on daily bases
            wp_schedule_event(time(), 'daily', 'invoice_reminder');
        }

        // add new permalinks and refresh the rewrite rules
        Menu\AccountInvoice::permalink();
        flush_rewrite_rules();
    }

    /**
     * when plugin gets deactivated
     *
     * @access public
     * @static
     * @return void
     */
    public static function deactivate()
    {
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
    public static function uninstall()
    {
    }
}
