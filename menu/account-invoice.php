<?php
namespace WCInvoicePdf\Menu;

use WCInvoicePdf\Model\Invoice;
use WCInvoicePdf\Menu\InvoiceMenu;

class AccountInvoice {
    public static function permalink(){
        add_rewrite_endpoint( 'invoices', EP_PERMALINK | EP_PAGES );
    }
    public function __construct(){
        self::permalink();
        if(!is_admin()) {
            add_filter('woocommerce_account_menu_items', [$this, 'wc_invoice_menu']);
            add_action('woocommerce_account_invoices_endpoint', [$this, 'wc_invoice_content']);

            if (isset($_GET['invoice'])) {
                $menu = new InvoiceMenu();
                $menu->OpenInvoice();
                return;
            }
        }
    }

    public function wc_invoice_menu($items){
        $result = array_slice($items, 0, 2);
        $result['invoices'] = __('Invoices', 'wc-invoice-pdf');
        
        $result = array_merge($result, array_slice($items, 2) );

        return $result;
    }

    public function wc_invoice_content(){
        global $wpdb, $current_user;
        
        if (isset($_GET['payment'])) {
            $this->showPaymentForInvoice(intval($_GET['payment']));
            return;
        }

        $query = "SELECT i.*, u.user_login AS customer_name, u.user_email AS user_email, u.ID AS user_id, p.ID AS order_id, p.post_status, pm.meta_value AS ispconfig_period 
                    FROM {$wpdb->prefix}ispconfig_invoice AS i 
                    LEFT JOIN {$wpdb->users} AS u ON u.ID = i.customer_id
                    LEFT JOIN {$wpdb->posts} AS p ON p.ID = i.wc_order_id
                    LEFT JOIN {$wpdb->postmeta} AS pm ON (p.ID = pm.post_id AND pm.meta_key = '_ispconfig_period')
                    WHERE i.customer_id = {$current_user->ID} AND i.deleted = 0
                    ORDER BY i.created DESC";

        $result = $wpdb->get_results($query, ARRAY_A);
        ?>
        <table class="woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_invoices account-orders-table">
            <thead>
                <tr>
                    <th><?php _e('Invoice', 'wc-invoice-pdf') ?></th>
                    <th><?php _e('Order', 'woocommerce') ?></th>
                    <th><?php _e('Created at', 'woocommerce') ?></th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($result as $k => $v) { ?>
                <tr>
                    <td><a href="<?php echo '?invoice=' . $v['ID'] ?>"><?php echo $v['invoice_number'] ?></a></td>
                    <td><a href="<?php echo get_permalink( get_option('woocommerce_myaccount_page_id')). 'view-order/' . $v['order_id'] ?>"><?php echo '#' . $v['order_id'] ?></a></td>
                    <td><?php echo strftime("%Y-%m-%d", strtotime($v['created'])) ?></td>
                    <td>
                        <?php if($v['status'] == Invoice::CANCELED): ?>
                            <?php _e('Canceled', 'wc-invoice-pdf') ?>
                        <?php elseif(($v['status'] & Invoice::PAID) == 0): ?>
                            <a href="<?php echo '?payment='.$v['ID']; ?>" class="button view"><?php _e('Pay Now', 'wc-invoice-pdf') ?></a>
                        <?php elseif(($v['status'] & Invoice::PAID) != 0): ?>
                            <strong><?php echo __('Paid at', 'wc-invoice-pdf') . ' ' . strftime("%x",strtotime($v['paid_date'])) ?> </strong>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php
    }

    public function showPaymentForInvoice($invoiceID){
        $invoice = new Invoice($invoiceID);
        $order = $invoice->Order();
        ?>
        <h3><?php  _e('Invoice', 'wc-invoice-pdf'); ?> <?php echo $invoice->invoice_number ?></h3>
        Zahlung via <?php echo $order->get_payment_method_title() ?>
        <p><?php _e('Order', 'woocommerce') ?># <?php echo $invoice->invoice_number ?></p>

        <?php if($invoice->status & Invoice::PAID): ?>
        <h4 style="text-align:center;"><?php echo __('Paid at', 'wc-invoice-pdf') . ' ' . strftime("%x",strtotime($invoice->paid_date)) ?></h4>
        <?php return; endif; ?>

        <?php if($order->get_payment_method() == 'bacs'): $bacs = new \WC_Gateway_BACS(); ?>      
            <?php $bacs->thankyou_page($order->get_id()); ?>
            <h3>Betrag: <?php echo $order->get_total() .' ' . $order->get_currency(); ?></h3>
        <?php elseif($order->get_payment_method() == 'paypal'): 
        
            // overwrite order number to use invoice number instead
            add_filter('woocommerce_order_number', function() use($invoice) { return $invoice->invoice_number; });
            add_filter('woocommerce_paypal_args', function($args) use($invoice) { $args['custom'] = json_encode(array('invoice_id' => $invoice->ID) ); return $args; });

            $paypal = new \WC_Gateway_Paypal();
            $result = $paypal->process_payment($order->get_id());
            //include_once(WPISPCONFIG3_PLUGIN_DIR . '../woocommerce/includes/gateways/paypal/includes/class-wc-gateway-paypal-request.php');
            //$paypal_request = new WC_Gateway_Paypal_Request( $paypal );
        ?>
            <div style="text-align: center;">
                <a href="<?php echo get_site_url() . '/wp-admin/admin.php?invoice=' . $invoiceID; ?>" class="button view"><?php  _e('Show');  ?></a>
                &nbsp;&nbsp;&nbsp;
                <a href="<?php echo $result['redirect'] ?>" class="button button-primary"><?php _e('Pay Now', 'wc-invoice-pdf') ?></a>
            </div>
        <?php endif; ?>
        <?php
    }
}