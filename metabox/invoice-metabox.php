<?php

namespace WCInvoicePdf\Metabox;

use WCInvoicePdf\Model\InvoicePdf;
use WCInvoicePdf\Model\Invoice;

class InvoiceMetabox {
    public function __construct(){
        add_action('add_meta_boxes', [$this, 'invoice_box'] );
        add_action('post_updated', [$this, 'invoice_submit']);
    }

    public static function DoAjax(){
        if(!empty($_POST['order_id']) && isset($_POST['period'])) {
            $period = esc_attr($_POST['period']);
            do_action('wcinvoicepdf_order_period', intval($_POST['order_id']), $period);
            $result = $period;
        }

        echo json_encode($result);
        wp_die();
    }

    public function invoice_box(){
        add_meta_box( 'ispconfig-invoice-box', 'Invoice', [$this, 'invoice_box_callback'], 'shop_order', 'side', 'high' );
    }

    public function invoice_box_callback() {
        global $post_id, $post;

        $period = get_post_meta($post_id, '_ispconfig_period', true);
        $customer_email = get_post_meta($post_id, '_billing_email', true, '');

        ?>
        <?php do_action('wcinvoicepdf_invoice_metabox', $post_id); ?>
        <p>
            <label class="post-attributes-label" for="ispconfig_period"><?php _e('Payment period', 'wc-invoice-pdf') ?>:</label>
            <select id="ispconfig_period" data-id="<?php echo $post_id ?>" onchange="WCInvoicePdfAdmin.UpdatePeriod(this)">
                <option value="">Off</option>
            <?php foreach(Invoice::$PERIOD as $k => $v) { ?>
                <option value="<?php echo $k ?>" <?php echo ($k == $period)?'selected': '' ?> ><?php _e($v, 'wc-invoice-pdf') ?></option>
            <?php } ?>
            </select>
        </p>
        <p class="ispconfig_scheduler_info periodinfo-s" style="<?php if($period != '') { echo 'display: none'; } ?>">
            <?php printf(__("A scheduler will submit the invoice once it has been created using the %s button to '%s'", 'wc-invoice-pdf'), __( 'Invoice', 'wc-invoice-pdf'), $customer_email); ?>
        </p>
        <p class="ispconfig_scheduler_info periodinfo-m" style="<?php if($period != 'm') { echo 'display: none'; } ?>">
            <?php printf(__("A scheduler will submit the invoice %s to '%s'", 'wc-invoice-pdf'), __('monthly', 'wc-invoice-pdf'), $customer_email); ?>
        </p>
        <p class="ispconfig_scheduler_info periodinfo-y" style="<?php if($period != 'y') { echo 'display: none'; } ?>">
            <?php printf(__("A scheduler will submit the invoice %s to '%s'", 'wc-invoice-pdf'), __('yearly', 'wc-invoice-pdf'), $customer_email); ?>
        </p>
        <p style="text-align: right">
            <a href="/wp-admin/admin.php?page=wcinvoicepdf_invoices"><?php _e('Show all invoices', 'wc-invoice-pdf') ?></a>
        </p>
        <p style="text-align: right">
            <button type="submit" name="ispconfig_invoice_action" class="button" value="preview">
                <?php printf(__('Preview', 'wc-invoice-pdf'), '') ?>
            </button>
            <button type="submit" name="ispconfig_invoice_action" class="button" value="offer">
                <?php _e('Offer', 'wc-invoice-pdf') ?>
            </button>
            <button type="submit" name="ispconfig_invoice_action" class="button button-primary" value="invoice">
                <?php _e( 'Invoice', 'wc-invoice-pdf') ?>
            </button>
        </p>
        <?php
    }

    public function invoice_submit($post_id){
        global $post;

        if(!isset($_REQUEST['ispconfig_invoice_action'])) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        remove_action( 'post_updated', [$this, 'ispconfig_invoice_submit']);

        if( ! ( wp_is_post_revision( $post_id) || wp_is_post_autosave( $post_id ) ) ) {
            $order = new \WC_Order($post);

            $invoice = new Invoice($order);
            $invoicePdf = new InvoicePdf();

            $action = preg_replace('/\W/', '', $_REQUEST['ispconfig_invoice_action']);

            switch($action)
            {
                case 'preview':
                    $invoicePdf->BuildInvoice($invoice, false, true);
                    exit;
                    break;
                case 'offer':
                    $invoicePdf->BuildInvoice($invoice, true, true);
                    exit;
                    break;
                case 'invoice':
                    $invoice->makeNew();
                    if($invoice->Save()){
                        $order->add_order_note("Invoice ".$invoice->invoice_number." created");
                    }                   
                    break;
            }
        }
    }
}