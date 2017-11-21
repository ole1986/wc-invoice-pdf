<?php
namespace WCInvoicePdf\Model;

use WCInvoicePdf\WCInvoicePdf;
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

class InvoiceExport {
    private $items = [];
    public function __construct($invoiceIds) {
        global $wpdb;
        
        if(!empty($invoiceIds)) {
            $query = "SELECT i.ID, i.customer_id, i.invoice_number, i.wc_order_id, i.created, i.due_date, i.paid_date, i.status, u.user_login AS customer_name, u.user_email AS user_email, u.ID AS user_id, p.ID AS order_id, p.post_status 
                        FROM {$wpdb->prefix}".Invoice::TABLE." AS i 
                        LEFT JOIN wp_users AS u ON u.ID = i.customer_id
                        LEFT JOIN wp_posts AS p ON p.ID = i.wc_order_id
                      WHERE 
                        i.deleted = 0 AND (status < ".Invoice::CANCELED.")
                        AND i.ID IN (".implode(',', $invoiceIds).")";
            
            $this->items = $wpdb->get_results($query, OBJECT);
        }
    }

    public function GnuCash(){
        ob_clean();
        
        if(empty($this->items)) {
            echo "Nothing to export";
            return;
        }

        header('Content-Type: text/comma-separated-values');
        header('Content-Disposition: attachment; filename="wcinvoice_export.csv"');

        $fp = fopen('php://output', 'w');

        foreach($this->items as $i) {
            $invoice = new Invoice($i);
            
            if(!empty(WCInvoicePdf::$OPTIONS['wc_export_locale'])) {
                setlocale(LC_ALL, WCInvoicePdf::$OPTIONS['wc_export_locale']);
            }

            $locale = localeconv();

            $crDate  = strftime('%x', strtotime($i->created));
            $dueDate = strftime('%x', strtotime($i->due_date));

            $combined = array_merge($invoice->Order()->get_items());

            foreach($combined as $item) {
                if(get_class($item) != 'WC_Order_Item_Product') continue;

                $fields = [
                    $i->invoice_number . $i->customer_id,
                    $crDate, /* date */
                    $i->customer_id, /* customer ID */
                    '', /* billing ID */
                    '', /* notes */
                    $crDate, /* date */
                    $item->get_name(), /* description */
                    '', /* action */
                    utf8_decode(WCInvoicePdf::$OPTIONS['wc_export_account']), /* account */
                    $item->get_quantity(), /* qty */
                    number_format($item->get_total(), 2, $locale['decimal_point'], ''), /* total incl. tax  */
                    '', /* disc_type */
                    '', /* disc_how */
                    '', /* discount */
                    'yes', /* taxable */
                    'no', /* tax included */
                    WCInvoicePdf::$OPTIONS['wc_export_account_tax'], /* tax table */
                    $crDate, /* date posted */
                    $dueDate, /* due date */
                    utf8_decode(WCInvoicePdf::$OPTIONS['wc_export_account_posted']), /* account posted*/
                    sprintf(WCInvoicePdf::$OPTIONS['wc_export_notes'], $i->invoice_number), /* memo_posted */
                    '' /* accu_split */
                ];

                fputcsv($fp, $fields, ';');
            }

            $invoice->Exported();
            $invoice->Save();
        }
        fclose($fp);
        exit;
    }
}