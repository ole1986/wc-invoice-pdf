<?php
/*
 * Plugin Name: WC Recurring Invoice PDF
 * Description: WooCommerce invoice pdf plugin with recurring payments (scheduled)
 * Version: 1.7.0
 * Author: ole1986 <ole.koeckemann@gmail.com>
 * Author URI: https://github.com/ole1986/wc-invoice-pdf
 * Plugin URI: https://github.com/ole1986/wc-invoice-pdf/releases
 * Text Domain: wc-invoice-pdf
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 8.9
 */

namespace WcRecurring;

defined('ABSPATH') or die('No script kiddies please!');

define('WCRECURRING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCRECURRING_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once 'vendor/autoload.php';

if (file_exists(WCRECURRING_PLUGIN_DIR. '/vendor_build/autoload.php')) {
    require_once WCRECURRING_PLUGIN_DIR .'/vendor_build/autoload.php';
}

add_action('init', ['\WcRecurring\WcRecurringIndex', 'init']);
add_action('admin_notices', ['\WcRecurring\WcRecurringIndex', 'migrate_notice'], 10, 2);

add_filter('plugin_row_meta', array( '\WcRecurring\WcRecurringIndex', 'plugin_meta' ), 10, 2);

register_activation_hook(plugin_basename(__FILE__), ['\WcRecurring\WcRecurringIndex', 'install' ]);
register_deactivation_hook(plugin_basename(__FILE__), ['\WcRecurring\WcRecurringIndex', 'deactivate' ]);
register_uninstall_hook(plugin_basename(__FILE__), ['\WcRecurring\WcRecurringIndex', 'uninstall']);

class WcRecurringIndex
{
    const MIGRATE_KEY = 'wc-invoice-pdf-version';
    const OPTION_KEY = 'wc-invoice-pdf';

    public static $MIGRATE_VERSION = 6;

    public static $OPTIONS = [
        'wc_payment_reminder' => 1,
        'wc_payment_message' => "Dear Administrator,\n\nThe following invoices are not being paid yet: %s\n\nPlease remind the customer(s) for payment",
        'wc_recur' => 0,
        'wc_recur_reminder' => 0,
        'wc_recur_reminder_age' => 2,
        'wc_recur_reminder_interval' => 2,
        'wc_recur_reminder_max' => 2,
        'wc_recur_message' => "Dear {CUSTOMER_NAME},\n\nattached you can find your invoice {INVOICE_NUMBER}\nPlease pay until {DUE_DATE}\n\nKind Regards,\n{COMPANY_NAME}",
        'wc_recur_reminder_message' => "Dear {CUSTOMER_NAME},\n\nKindly be informed about the attached invoice {INVOICE_NUMBER} is not marked as paid in our system.\nPlease pay the invoice until the next {NEXT_DUE_DAYS} days.\n\nIf your payment has already been sent or remitted please ignore this email\n\n{COMPANY_NAME}",
        'wc_recur_test' => 0,
        'wc_mail_sender' => 'Invoice <invoice@domain.tld>',
        'wc_mail_reminder' => 'yourmail@domain.tld',
        'wc_pdf_title' => '{COMPANY_NAME} - {INVOICE_NUMBER}',
        'wc_invoice_due_days' => 14,
        'wc_pdf_template' => null,
        'wc_pdf_condition' => "Payment within {DUE_DAYS} days after {DUE_DATE}.",
        'wc_pdf_condition_offer' => "This offer is valid for 2 weeks",
        'wc_pdf_info' => 'Created at: {INVOICE_CREATED}',
        'wc_export_locale' => 'de_DE',
        'wc_export_notes' => 'Rechnung %s',
        'wc_export_account' => 'Erlöse u. Erträge 2/8:Erlöskonten 8:8400 Erlöse USt. 19%',
        'wc_export_account_posted' => 'Aktiva:Finanzkonten 1:1400 Ford. a. Lieferungen und Leistungen',
        'wc_export_account_tax' => 'Umsatzsteuer',
        'wc_order_subscriptions' => '',
        'wc_company_name' => 'Your Company',
        'wc_company_email' => 'some@mail.tld',
        'wc_company_vat' => 'DE123456',
        'wc_pdf_xinvoice' => 0,
        'wc_order_show_completed' => 0,
        'wc_customer_login_gdpr' => 0
    ];

    public static $SUBSCRIPTIONS = [];

    /**
     * initialize the text domain and load the constructor
     */
    public static function init()
    {
        require_once 'src/WcProduct/wc_init.php';

        self::load_textdomain_file();

        self::$SUBSCRIPTIONS = ['m' => __('monthly', 'wc-invoice-pdf'), 'y' => __('yearly', 'wc-invoice-pdf') ];

        self::load_options();

        // enable changing the due date through ajax
        add_action('wp_ajax_Invoice', ['\WcRecurring\Model\Invoice', 'DoAjax']);
        // enable ajax requests for invoice tasks (admin-only)
        add_action('wp_ajax_InvoiceTask', ['WcRecurring\Schedule\InvoiceTask', 'DoAjax']);
        // register the scheduler action being used by wp_schedule_event
        add_action('invoice_reminder', ['\WcRecurring\Schedule\InvoiceTask', 'Run']);
        // update the order period
        add_action('wc_recurring_order_period', ['\WcRecurring\WcRecurringIndex', 'order_period'], 10, 2);

        if (is_admin()) {
            // display invoice metabox in WC Order
            new WcExtend\InvoiceMetabox();
            // display the admin menu
            new Menu\InvoiceMenu();
            // extend invoice creation with xrechnung
            new Extend\Xrechnung();
            // load Js and styles only in admin area
            add_action('admin_enqueue_scripts', ['\WcRecurring\WcRecurringIndex', 'loadJS']);
        } else {
            new WcExtend\RecurringExtension();
            // display the customer invoice menu
            new Menu\AccountInvoice();
        }

        new WcExtend\CustomerProperties();
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
        $ok = update_option(self::OPTION_KEY, self::$OPTIONS);

        if ($ok) {
            self::migrate();
        }

        return $ok;
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
        load_plugin_textdomain('wc-invoice-pdf', false, basename(WCRECURRING_PLUGIN_DIR) . '/lang');
    }

    public static function loadJS()
    {
        $plugin_data = get_plugin_data(__FILE__);

        wp_enqueue_script('wc-recurring-script', WCRECURRING_PLUGIN_URL . 'browser/js/wc-recurring-admin.js', null, $plugin_data['Version']);
        wp_enqueue_style('wc-recurring-style', WCRECURRING_PLUGIN_URL . 'browser/style/wc-recurring.css', null, $plugin_data['Version']);
    }

    public static function plugin_meta($links, $file)
    {
        $l = strlen($file);

        if (substr(__FILE__, -$l) == $file) {
            $row_meta = array(
                'bug'    => '<a href="https://github.com/ole1986/wc-invoice-pdf/issues" style="color: #a00" target="_blank">Report Bug</a>',
                'donate'    => '<a href="https://www.paypal.com/cgi-bin/webscr?item_name=Donation+WC+Recurring+Invoice+Pdf&cmd=_donations&business=ole.koeckemann@gmail.com" target="_blank"><span class="dashicons dashicons-heart"></span> Donate</a>'
            );
            return array_merge($links, $row_meta);
        }
        return (array) $links;
    }

    public static function migrate_notice()
    {
        // increase when something to migration
        $plugin = get_plugin_data(__FILE__);
       
        // migration
        $version = intval(get_option(self::MIGRATE_KEY, self::$MIGRATE_VERSION));

        if ($version < self::$MIGRATE_VERSION) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php echo $plugin['Name'] ?>:</strong>
                    Please review invoices and <a href="admin.php?page=wcinvoicepdf_settings">Settings</a> due to BREAKING CHANGES in PDF output!
                    - <a href="https://github.com/ole1986/wc-invoice-pdf/releases/tag/v1.7.0" target="_blank" class="button button-primary">RELEASE NOTES</a>
                </p>
                
            </div>
            <?php
        }
    }

    public static function migrate()
    {
        // migration
        $version = intval(get_option(self::MIGRATE_KEY, self::$MIGRATE_VERSION - 1));

        if ($version < self::$MIGRATE_VERSION) {
            update_option(self::MIGRATE_KEY, self::$MIGRATE_VERSION);
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
            wp_schedule_event(time() + 86400, 'daily', 'invoice_reminder');
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
