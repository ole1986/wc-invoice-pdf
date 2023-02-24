<?php

namespace WCInvoicePdf\Metabox;

use WCInvoicePdf\Model\InvoicePdf;
use WCInvoicePdf\Model\Invoice;

class InvoiceMetabox
{
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'invoice_box']);
        add_action('post_updated', [$this, 'invoice_submit']);
        
        // fill up the info about recurrence
        add_action('manage_shop_order_posts_custom_column', [$this, 'fill_orders_recurring_column'], 20, 3);
    }

    public static function DoAjax()
    {
        $order_id = intval($_POST['order_id']);

        if (empty($order_id)) {
            wp_die();
        }

        if (isset($_POST['period'])) {
            $period = esc_attr($_POST['period']);
            do_action('wcinvoicepdf_order_period', $order_id, $period);
            $result = $period;
        } elseif (isset($_POST['b2c'])) {
            if ($_POST['b2c'] == 'true') {
                update_post_meta($order_id, '_wc_pdf_b2c', '1');
            } else {
                delete_post_meta($order_id, '_wc_pdf_b2c');
            }
        } elseif (isset($_POST['resetpaid'])) {
            delete_post_meta($order_id, '_date_paid');
            delete_post_meta($order_id, '_paid_date');
        }

        echo json_encode($result);
        wp_die();
    }

    public function invoice_box()
    {
        add_meta_box('ispconfig-invoice-box', __('Invoice', 'wc-invoice-pdf'), [$this, 'invoice_box_callback'], 'shop_order', 'side', 'high');
    }

    public function invoice_box_callback()
    {
        global $post_id, $post;

        $b2c = get_post_meta($post_id, '_wc_pdf_b2c', true);
        $period = get_post_meta($post_id, '_ispconfig_period', true);
        $customer_email = get_post_meta($post_id, '_billing_email', true, '');

        ?>
        <?php do_action('wcinvoicepdf_invoice_metabox', $post_id); ?>
        <p>
            <label class="post-attributes-label" for="wc_pdf_b2c"><?php _e('Enable B2C', 'wc-invoice-pdf') ?></label>
            <input id="wc_pdf_b2c" type="checkbox" data-id="<?php echo $post_id ?>" value="1" onclick="WCInvoicePdfAdmin.UpdateB2C(this)" <?php echo !empty($b2c) ? 'checked' : '' ?> />
            <div>
                <?php _e('Create invoice compatible for Business to Customer (B2C) relationship', 'wc-invoice-pdf') ?>
            </div>
        </p>
        <p>
            <label class="post-attributes-label" for="ispconfig_period"><?php _e('Payment interval', 'wc-invoice-pdf') ?>:</label>
            <select id="ispconfig_period" data-id="<?php echo $post_id ?>" onchange="WCInvoicePdfAdmin.UpdatePeriod(this)">
                <option value="">Off</option>
            <?php foreach (Invoice::$PERIOD as $k => $v) { ?>
                <option value="<?php echo $k ?>" <?php echo ($k == $period)?'selected': '' ?> ><?php _e($v, 'wc-invoice-pdf') ?></option>
            <?php } ?>
            </select>
        </p>
        <p class="ispconfig_scheduler_info periodinfo-s" style="<?php if ($period != '') { echo 'display: none'; } ?>">
            <?php printf(__("A scheduler will submit the invoice once it has been created using the %s button to '%s'", 'wc-invoice-pdf'), __('Invoice', 'wc-invoice-pdf'), $customer_email); ?>
        </p>
        <p class="ispconfig_scheduler_info periodinfo-m" style="<?php if ($period != 'm') { echo 'display: none'; } ?>">
            <?php printf(__("A scheduler will submit the invoice %s to '%s'", 'wc-invoice-pdf'), __('monthly', 'wc-invoice-pdf'), $customer_email); ?>
        </p>
        <p class="ispconfig_scheduler_info periodinfo-y" style="<?php if ($period != 'y') { echo 'display: none'; } ?>">
            <?php printf(__("A scheduler will submit the invoice %s to '%s'", 'wc-invoice-pdf'), __('yearly', 'wc-invoice-pdf'), $customer_email); ?>
        </p>
        <p style="text-align: right">
            <a href="/wp-admin/admin.php?page=wcinvoicepdf_invoices"><?php _e('Show all invoices', 'wc-invoice-pdf') ?></a>
        </p>
        <p style="text-align: right;">
            <a href="#" data-id="<?php echo $post_id ?>" onclick="WCInvoicePdfAdmin.ResetOrderPaidStatus(this)"><?php _e('Reset order paid status', 'wc-invoice-pdf') ?></a>
        </p>
        <p style="text-align: right">
            <a href="admin.php?page=wcinvoicepdf_invoice&order=<?php echo $post_id ?>" target="_blank" class="button"><?php printf(__('Preview', 'wc-invoice-pdf'), '') ?></a>
            <a href="admin.php?page=wcinvoicepdf_invoice&order=<?php echo $post_id ?>&offer=1" target="_blank" class="button"><?php _e('Offer', 'wc-invoice-pdf') ?></a>
            <button type="submit" name="ispconfig_invoice_action" class="button button-primary" value="invoice">
                <?php _e('Generate', 'wc-invoice-pdf') ?>
            </button>
        </p>
        <?php
    }

    public function invoice_submit($post_id)
    {
        global $post;

        if (!isset($_REQUEST['ispconfig_invoice_action'])) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        remove_action('post_updated', [$this, 'ispconfig_invoice_submit']);

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (wp_is_post_autosave($post_id)) {
            return;
        }

        $order = new \WC_Order($post);

        $invoice = new Invoice($order);
        $invoice->makeNew();

        if ($invoice->Save()) {
            $order->add_order_note(sprintf(__("Invoice %s successfully created", 'wc-invoice-pdf'), $invoice->invoice_number));
        }
    }

    public function fill_orders_recurring_column($column)
    {
        global $post;

        if ($column === 'order_number') {
            $period = get_post_meta($post->ID, '_ispconfig_period', true);
            $customer_email = get_post_meta($post->ID, '_billing_email', true, '');
            if ($period) {
                echo "<div style='font-size: 85%'>" . __('Payments', 'wc-invoice-pdf') . ': ' . __(Invoice::$PERIOD[$period], 'wc-invoice-pdf') .'</div>';
            }
        }
    }
}
