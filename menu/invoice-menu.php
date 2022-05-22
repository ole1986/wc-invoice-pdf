<?php

namespace WCInvoicePdf\Menu;

use WCInvoicePdf\Model\InvoiceList;
use WCInvoicePdf\Model\Invoice;
use WCInvoicePdf\Model\InvoicePdf;

use WCInvoicePdf\WCInvoicePdf;

class InvoiceMenu
{
    public function __construct()
    {
        if (is_admin()) {
            add_action('admin_menu', array( $this, 'admin_menu' ));
        }
    }

    public function admin_menu()
    {
        add_menu_page('WC-' . __('Invoices', 'wc-invoice-pdf'), 'WC-' . __('Invoices', 'wc-invoice-pdf'), 'null', 'wcinvoicepdf_menu', null, WCINVOICEPDF_PLUGIN_URL.'invoicepdf.png', 3);
        add_submenu_page('wcinvoicepdf_menu', __('Invoices', 'wc-invoice-pdf'), __('Invoices', 'wc-invoice-pdf'), 'edit_themes', 'wcinvoicepdf_invoices', [$this, 'DisplayInvoices']);
        add_submenu_page('wcinvoicepdf_menu', __('Settings'), __('Settings'), 'edit_themes', 'wcinvoicepdf_settings', [$this, 'DisplaySettings']);
        // hide the menu using null
        add_submenu_page(null, '_Invoice', '_Invoice', 'wc_invoice_pdf', 'wcinvoicepdf_invoice', [$this, 'OpenInvoice']);
    }

    /**
     * Check for permission on specific invoices being generated by the sytem and populated to users "My Account" page.
     * Also it validates the access for administrative users to generate invoices/quotes
     */
    private function userHasAccess($invoice)
    {
        global $current_user;
        
        // only for logged in users
        if (!is_user_logged_in()) {
            return false;
        }

        if (!current_user_can('manage_options') && $invoice->customer_id != $current_user->ID) {
            return false;
        }

        return true;
    }

    /**
     * Used to open or generate invoices.
     * This method is also used in front end through "My Account" endpoint
     */
    public function OpenInvoice()
    {
        global $wpdb, $pagenow, $current_user;

        // remove previous output to generate a clean pdf result
        ob_clean();

        $offer = !empty($_GET['offer']) ? true : false;

        if (!empty($_GET['order'])) {
            $order = new \WC_Order(intval($_GET['order']));

            $invoice = new Invoice($order);

            //$invoicepdf = new InvoicePdf();

            if (!$this->userHasAccess($invoice)) {
                wp_die("You do not have access to generate invoices");
            }

            $invoice->makeNew($offer);

            header("Content-type: application/pdf");
            header("Content-Disposition: inline; filename=".$invoice->invoice_number .'.pdf');

            echo $invoice->document;
        } elseif (!empty($_GET['invoice'])) {
            $invoice = new Invoice(intval($_GET['invoice']));

            if (!$invoice->ID) {
                die("Invoice not found");
            }

            if (!$this->userHasAccess($invoice)) {
                wp_die("You cannot access this invoice");
            }

            header("Content-type: application/pdf");
            header("Content-Disposition: inline; filename=".$invoice->invoice_number .'.pdf');

            echo $invoice->document;
        }

        die;
    }

    /**
     * Display a list of all available invoices
     */
    public function DisplayInvoices()
    {
        global $wpdb;
        $invList = new InvoiceList();
        
        $a = $invList->current_action();
        $invList->prepare_items();

        // get the invoice_reminder cron job installed by this plugin
        $cron_jobs = get_option('cron');
        $invoice_reminder_event = array_filter($cron_jobs, function ($v) {
            return isset($v['invoice_reminder']);
        });

        $isInvoiceSubmissionEnabled = WCInvoicePdf::$OPTIONS['wc_recur'];

        ?>
        <div class='wrap'>
            <h1><?php _e('Invoices', 'wc-invoice-pdf') ?></h1>
            <?php if (count($invoice_reminder_event) > 0 && $isInvoiceSubmissionEnabled) :
                $event_time = key($invoice_reminder_event);

                $d = new \DateTime();
                $d->setTimezone(new \DateTimeZone(get_option('timezone_string', 'UTC')));
                $d->setTimestamp($event_time);
                ?>
            <div class="updated"><p><?php printf(__('The next automated invoice submission is scheduled for %s', 'wc-invoice-pdf'), $d->format('Y-m-d H:i')); ?></p></div>
            <?php endif; ?>
            <?php if (!$isInvoiceSubmissionEnabled) : ?>
            <div class="error"><p><?php _e("The scheduler is disabled for automated invoice submission. Please check the <a href='/wp-admin/admin.php?page=wcinvoicepdf_settings'>settings</a>", 'wc-invoice-pdf') ?></p></div>
            <?php endif; ?>
            <h2></h2>
            <form action="" method="GET">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']) ?>" />
                <input type="hidden" name="action" value="filter" />
                <label class="post-attributes-label" for="user_login"><?php _e('Customer', 'woocommerce') ?></label>
                <select name="customer_id" style="min-width: 200px">
                    <option value=""></option>
                <?php
                $users = get_users(['role' => 'customer']);
                foreach ($users as $u) {
                    $company = get_user_meta($u->ID, 'billing_company', true);
                    $selected = (isset($_GET['customer_id']) && $u->ID == intval($_GET['customer_id']))?'selected':'';
                    echo '<option value="'.$u->ID.'" '.$selected.'>'. $company . ' (' .$u->user_login.')</option>';
                }
                ?>
                </select>
                <input type="checkbox" id="recur_only" name="recur_only" value="1" <?php echo (!empty($_GET['recur_only'])?'checked':'') ?> /> <label for="recur_only"><?php _e('Recurring payments', 'wc-invoice-pdf') ?></label>
                <input type="submit" class="button" value="<?php _e('Filter &#187;') ?>">
            </form>
            <form action="" method="POST">
                <div style="margin-top: 1em">
                    <?php if (isset($_GET['post_status'])) : ?>
                        <a href="/wp-admin/admin.php?page=wcinvoicepdf_invoices"><?php _e('All') ?></a>
                    <?php else : ?>
                        <?php _e('All') ?>
                    <?php endif; ?>
                    |
                    <?php if (!isset($_GET['post_status'])) : ?>
                        <a href="<?php echo '?' . $_SERVER['QUERY_STRING'] . '&post_status=deleted'; ?>"><?php printf(__('Trash <span class="count">(%s)</span>'), $invList->total_trash_rows) ?></a>
                    <?php else : ?>
                        <?php printf(__('Trash <span class="count">(%s)</span>'), $invList->total_trash_rows) ?>
                    <?php endif; ?>
                </div>
                <?php $invList->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Show the avaialble settings
     */
    public function DisplaySettings()
    {
        if ('POST' === $_SERVER[ 'REQUEST_METHOD' ]) {
            WCInvoicePdf::$OPTIONS = $_POST;
            if (WCInvoicePdf::save_options()) {
                ?><div class="updated"><p> <?php _e('Settings saved', 'wc-invoice-pdf');?></p></div><?php
            }
        }
        ?>
        <div class="wrap">
            <h1><?php _e('WC-Invoice Settings', 'wc-invoice-pdf');?></h1>

            <?php if (wp_get_schedule('invoice_reminder')) : ?>
                <div class="notice notice-info"><p><?php _e('The schedule is properly installed and running', 'wc-invoice-pdf') ?></p></div>
            <?php else : ?>
                <div class="notice notice-error"><p><?php _e('The scheduled task is not installed - Please try to reenable the plugin', 'wc-invoice-pdf') ?></p></div>
            <?php endif; ?>
            <h2 id="wcinvoicepdf-tabs" class="nav-tab-wrapper">
                <a href="#wcinvoicepdf-order" class="nav-tab nav-tab-active"><?php _e('Order', 'woocommerce') ?></a>
                <a href="#wcinvoicepdf-invoice" class="nav-tab"><?php _e('Invoice template (PDF)', 'wc-invoice-pdf') ?></a>
                <a href="#wcinvoicepdf-scheduler" class="nav-tab"><?php _e('Task Scheduler', 'wc-invoice-pdf') ?></a>
                <a href="#wcinvoicepdf-template" class="nav-tab"><?php _e('Templates', 'wc-invoice-pdf') ?></a>
                <a href="#wcinvoicepdf-export" class="nav-tab"><?php _e('Export', 'wc-invoice-pdf') ?></a>
            </h2>
            <form method="post" action="">
                <div id="wcinvoicepdf-order" class="inside tabs-panel" style="display: none;">
                    <?php
                        WCInvoicePdf::addField('wc_pdf_b2c', '<strong>' . __('Enable B2C', 'wc-invoice-pdf') . '</strong><br />' . __('Create invoice compatible for Business to Customer (B2C) relationship', 'wc-invoice-pdf'), 'checkbox');
                    ?>
                    <?php
                        WCInvoicePdf::addField('wc_invoice_due_days', '<strong>' . __('Due date in days', 'wc-invoice-pdf') . '</strong><br />' . __('The number of days an invoice becomes due, when created from an order', 'wc-invoice-pdf'), 'number');
                    ?>
                    <p>
                    <label style="width: 220px; display:inline-block;vertical-align:top;">
                        <strong><?php _e('Subscription option', 'wc-invoice-pdf')  ?></strong><br />
                        <?php _e('Allow the customer to choose between the subscriptions during checkout or fix a value', 'wc-invoice-pdf') ?>
                    </label>
                    <select name="wc_order_subscriptions">
                        <option value=""><?php _e('Customer choose', 'wc-invoice-pdf') ?></option>
                        <?php
                        foreach (WCInvoicePdf::$SUBSCRIPTIONS as $key => $value) {
                            $selected = WCInvoicePdf::$OPTIONS['wc_order_subscriptions'] == $key ? 'selected' : '';
                            echo '<option value="'. $key . '" '. $selected .'>'. $value .'</option>';
                        }
                        ?>
                    </select>
                    </p>
                    <?php if (\class_exists('Ispconfig')) : ?>
                    <p>
                    <label style="width: 220px; display:inline-block;vertical-align:top;">
                        <strong>ISPConfig client template</strong><br />
                        Choose a ISPConfig client template for new orders containing WC_ISPConfig_Products (E.g Webspace)
                    </label>
                    <select name="wc_ispconfig_client_template">
                        <option value="0">None</option>
                        <?php
                        $templates = \Ispconfig::$Self->withSoap()->GetClientTemplates();
                        foreach ($templates as $v) {
                            $selected = WCInvoicePdf::$OPTIONS['wc_ispconfig_client_template'] == $v['template_id'] ? 'selected' : '';
                            echo '<option value="'. $v['template_id'] . '" '. $selected .'>'. $v['template_name'] .'</option>';
                        }
                        \Ispconfig::$Self->closeSoap();
                        ?>
                    </select>
                    </p>
                    <?php endif ?>
                </div>
                <div id="wcinvoicepdf-invoice" class="inside tabs-panel" style="display: none;">
                    <?php
                    WCInvoicePdf::addField('wc_pdf_title', __('Document Title', 'wc-invoice-pdf'));
                    WCInvoicePdf::addField('wc_pdf_logo', 'Logo', 'media');
                    WCInvoicePdf::addField('wc_pdf_addressline', __('Address line', 'wc-invoice-pdf'));
                    WCInvoicePdf::addField('wc_pdf_condition', __('Payment terms', 'wc-invoice-pdf'), 'textarea');
                    WCInvoicePdf::addField('wc_pdf_info', '<strong>Info Block</strong><br />' . 'Supports "Inline codes" provided by the <a href="https://github.com/rospdf/pdf-php/blob/master/README.md" target="_blank">R&amp;OS pdf class</a>', 'textarea', ['input_attr' => ['style' => 'width: 340px; height: 100px']]);
                    WCInvoicePdf::addField('wc_pdf_keeprows', '<strong>' . __('Protect rows from splitting', 'wc-invoice-pdf') . '</strong><br />' . __('Keep rows together when page breaks', 'wc-invoice-pdf'), 'checkbox');

                    WCInvoicePdf::addField('wc_pdf_block1', 'Block #1', 'rte', ['container' => 'div', 'input_attr' => ['style'=>'width: 350px;display:inline-block;'] ]);
                    WCInvoicePdf::addField('wc_pdf_block2', 'Block #2', 'rte', ['container' => 'div', 'input_attr' => ['style'=>'width: 350px;display:inline-block;'] ]);
                    WCInvoicePdf::addField('wc_pdf_block3', 'Block #3', 'rte', ['container' => 'div', 'input_attr' => ['style'=>'width: 350px;display:inline-block;'] ]);
                    ?>
                </div>
                <div id="wcinvoicepdf-scheduler" class="inside tabs-panel" style="display: none;">
                    <h3><?php _e('Run commands', 'wc-invoice-pdf') ?></h3>
                    <p>
                        <a href="javascript:void(0)" onclick="WCInvoicePdfAdmin.RunTask(this, 'notify')" class="button">Run Payment Notification</a><br />
                        Run the payment notifier now and submit outstanding invoice information to <strong>Admin Email</strong>.
                    </p>
                    <p>
                        <a href="javascript:void(0)" onclick="WCInvoicePdfAdmin.RunTask(this, 'recur')" class="button" style="background-color: #bd0000; color: white">Generate recurring invoices</a><br />
                        Generate all recurring invoices for today. Please be careful with this as it may generate (and later submit) duplicates to the recipients
                    </p>
                    <p>
                        <a href="javascript:void(0)" onclick="WCInvoicePdfAdmin.RunTask(this, 'submit')" class="button" style="background-color: #bd0000; color: white">Run invoice submission</a><br />
                        Submit all outstanding invoices to their recipients
                    </p>
                    <p>
                        <a href="javascript:void(0)" onclick="WCInvoicePdfAdmin.RunTask(this, 'reminder')" class="button" style="background-color: #bd0000; color: white">Run invoice Reminder</a><br />
                        Submit all reminder for invoice which are due. Please be careful with this as it will re-submit the reminders and increase the counter
                    </p>
                    <h3><?php _e('Sender info', 'wc-invoice-pdf') ?></h3>
                    <?php
                    WCInvoicePdf::addField('wc_mail_reminder', '<strong>Admin Email</strong><br />used for payment reminders and testing purposes');
                    WCInvoicePdf::addField('wc_mail_sender', '<strong>Sender Email</strong><br />Customer will see this address');
                    ?>
                    <h3><?php _e('Payments', 'wc-invoice-pdf') ?></h3>
                    <?php
                    WCInvoicePdf::addField('wc_recur_test', '<span style="color: red; font-weight: bold">Test Mode</span><br />' . __('Enable test mode and replace all recipients with the admin email address', 'wc-invoice-pdf'), 'checkbox');
                    WCInvoicePdf::addField('wc_payment_reminder', '<strong>'. __('Payment report', 'wc-invoice-pdf') .'</strong><br />Send a daily report of unpaid invoices to "Admin Email"', 'checkbox');
                    WCInvoicePdf::addField('wc_recur', '<strong>' . __('Automate invoice submission', 'wc-invoice-pdf').'</strong><br />' . __('Enable automate invoice submission to customers on a daily schedule', 'wc-invoice-pdf'), 'checkbox');
                    WCInvoicePdf::addField('wc_recur_reminder', '<strong>'. __('Payment reminder', 'wc-invoice-pdf').'</strong><br />' . __('Send payment reminders to customer when invoice is due', 'wc-invoice-pdf'), 'checkbox');
                    WCInvoicePdf::addField('wc_recur_reminder_age', '<strong>' . __('First reminder (days)', 'wc-invoice-pdf') . '</strong><br />' . __('The number of days (after due) when the first payment reminder should be sent to the customer', 'wc-invoice-pdf'));
                    WCInvoicePdf::addField('wc_recur_reminder_interval', '<strong>'. __('Reminder interval', 'wc-invoice-pdf') .'</strong><br />The number of days (after first occurence) a reminder should be resent to customer');
                    WCInvoicePdf::addField('wc_recur_reminder_max', '<strong>'. __('Max reminders', 'wc-invoice-pdf') .'</strong><br />How many reminders should be sent for a single invoice to the customer');
                    ?>
                </div>
                <div id="wcinvoicepdf-template" class="inside tabs-panel" style="display: none;">
                    <p>
                        Customize your templates being sent internally or to the customer<br />
                    </p>
                    <h3><?php _e('Payment report', 'wc-invoice-pdf')  ?></h3>
                    <?php
                    $attr = [
                        'label_attr' => [ 'style' => 'width: 200px; display:inline-block;vertical-align:top;'],
                        'input_attr' => ['style' => 'margin-left: 1em; width:50em;height: 200px']
                    ];
                    WCInvoicePdf::addField('wc_payment_message', '<strong>'. __('Payment report', 'wc-invoice-pdf') .'</strong><br />Inform the administrator (see "Admin Email") about outstanding invoices', 'textarea', $attr);
                    ?>
                    <h3><?php _e('Payments', 'wc-invoice-pdf') ?></h3>
                    <?php
                    WCInvoicePdf::addField('wc_recur_message', '<strong>' . __('Automate invoice submission', 'wc-invoice-pdf').'</strong><br />Submit the recurring invoice to the customer containing this message', 'textarea', $attr);
                    ?>
                    <div style="font-size: smaller; margin-left: 220px;">
                        Placeholder: 
                        <strong>{CUSTOMER_NAME}</strong> |
                        <strong>{INVOICE_NO}</strong> |
                        <strong>{DUE_DATE}</strong> |
                        <strong>{DUE_DAYS}</strong>
                    </div>
                    <?php
                    WCInvoicePdf::addField('wc_recur_reminder_message', '<strong>'. __('Payment reminder', 'wc-invoice-pdf').'</strong><br />Submit the recurring invoice to the customer containing this message', 'textarea', $attr);
                    ?>
                    <div style="font-size: smaller;margin-left: 220px;">
                        Placeholder: 
                        <strong>{CUSTOMER_NAME}</strong> |
                        <strong>{INVOICE_NO}</strong> |
                        <strong>{DUE_DATE}</strong> |
                        <strong>{DUE_DAYS}</strong> |
                        <strong>{NEXT_DUE_DAYS}</strong>
                    </div>
                </div>
                <div id="wcinvoicepdf-export" class="inside tabs-panel" style="display: none;">
                    <p>
                        The export feature currently supports GnuCash *.csv format to import invoices.</br />
                        Please make sure the CUSTOMER ID (in GnuCash) matches the user id in wordpress
                    </p>
                    <?php
                    WCInvoicePdf::addField('wc_export_locale', '<strong>Locale</strong><br />Example: de_DE en_US');
                    WCInvoicePdf::addField('wc_export_notes', '<strong>Notes</strong><br />Invoice notes');
                    WCInvoicePdf::addField('wc_export_account', '<strong>Account name</strong><br />Name of the account an invoce is booked');
                    WCInvoicePdf::addField('wc_export_account_posted', '<strong>Account name</strong><br />Name of the account invoce is posted');
                    WCInvoicePdf::addField('wc_export_account_tax', '<strong>Tax Account (optional)</strong><br />Name of the tax account');
                    ?>
                </div>
            <div class="inside">
                <p></p>
                <p><input type="submit" class="button-primary" name="submit" value="<?php _e('Save', 'wc-invoice-pdf');?>" /></p>
                <p></p>
            </div>
            </form>
        </div>
        <?php
    }
}
