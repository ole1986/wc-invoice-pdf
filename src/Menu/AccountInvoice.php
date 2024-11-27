<?php
namespace WcRecurring\Menu;

use WcRecurring\Model\Invoice;
use WcRecurring\WcRecurringIndex;

class AccountInvoice
{
    public static function permalink()
    {
        add_rewrite_endpoint('invoices', EP_PERMALINK | EP_PAGES);
    }
    public function __construct()
    {
        self::permalink();
        if (!is_admin()) {
            add_filter('woocommerce_account_menu_items', [$this, 'wc_invoice_menu']);
            add_action('woocommerce_account_invoices_endpoint', [$this, 'wc_invoice_content']);

            if (!empty(WcRecurringIndex::$OPTIONS['wc_order_show_completed'])) {
                add_filter('woocommerce_my_account_my_orders_query', [$this, 'wc_orders_query']);
            }
            

            if (isset($_GET['invoice'])) {
                $menu = new InvoiceMenu();
                $menu->OpenInvoice();
                return;
            }
        }
    }

    public function wc_orders_query($args)
    {
        $args['post_status'] = 'wc-completed';
        return $args;
    }

    public function wc_invoice_menu($items)
    {
        $result = array_slice($items, 0, 2);
        $result['invoices'] = __('Invoices', 'wc-invoice-pdf');
        
        $result = array_merge($result, array_slice($items, 2));

        return $result;
    }

    public function wc_invoice_content()
    {
        global $wpdb, $current_user;

        $query = "SELECT i.ID, i.invoice_number, i.created, i.due_date, i.status, i.paid_date, ISNULL(i.xinvoice) AS no_xinvoice,
                    u.user_login AS customer_name, u.user_email AS user_email, u.ID AS user_id, p.ID AS order_id, p.post_status, pm.meta_value AS ispconfig_period 
                    FROM {$wpdb->prefix}ispconfig_invoice AS i 
                    LEFT JOIN {$wpdb->users} AS u ON u.ID = i.customer_id
                    LEFT JOIN {$wpdb->posts} AS p ON p.ID = i.wc_order_id
                    LEFT JOIN {$wpdb->postmeta} AS pm ON (p.ID = pm.post_id AND pm.meta_key = '_ispconfig_period')
                    WHERE i.customer_id = {$current_user->ID} AND i.deleted = 0
                    ORDER BY i.created DESC";

        $result = $wpdb->get_results($query, ARRAY_A);

        $dateFormatter = new \IntlDateFormatter('de-DE', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
        ?>
        <table class="woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_invoices account-orders-table">
            <thead>
                <tr>
                    <th><?php _e('Invoice', 'wc-invoice-pdf') ?></th>
                    <th><?php _e('Order', 'woocommerce') ?></th>
                    <th><?php _e('Created at', 'woocommerce') ?></th>
                    <th><?php _e('Due at', 'wc-invoice-pdf') ?></th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result as $k => $v) { ?>
                <tr>
                    <td>
                        <a href="<?php echo '?invoice=' . $v['ID'] ?>" target="_blank"><?php echo $v['invoice_number'] ?></a><br />
                        <?php if (empty($v['no_xinvoice'])) : ?>
                        <span style="font-size: 75%"><a href="<?php echo '?invoice=' . $v['ID'] .'&xml=1' ?>" target="_blank">XRechnung [XML]</a></span>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?php echo get_permalink(get_option('woocommerce_myaccount_page_id')). 'view-order/' . $v['order_id'] ?>"><?php echo '#' . $v['order_id'] ?></a></td>
                    <td><?php echo  $dateFormatter->format(strtotime($v['created'])) ?></td>
                    <td><?php echo $dateFormatter->format(strtotime($v['due_date'])) ?></td>
                    <td style="text-transform: unset;">
                        <?php if ($v['status'] == Invoice::CANCELED) : ?>
                            <?php _e('Canceled', 'wc-invoice-pdf') ?>
                        <?php elseif (($v['status'] & Invoice::PAID) == 0) : ?>
                            <a href="<?php echo '?invoice=' . $v['ID'] ?>" class="button view" target="_blank"><?php _e('Pay Now', 'wc-invoice-pdf') ?></a>
                        <?php elseif (($v['status'] & Invoice::PAID) != 0) : ?>
                            <strong><?php echo __('Paid at', 'wc-invoice-pdf') . ' ' .  $dateFormatter->format(strtotime($v['paid_date'])) ?> </strong>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php
    }
}
