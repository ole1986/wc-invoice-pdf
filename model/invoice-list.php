<?php
namespace WCInvoicePdf\Model;

// Prevent loading this file directly
defined('ABSPATH') || exit;

// load the wordpress list table class
if (! class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

add_action('admin_head', array( 'WCInvoicePdf\Model\InvoiceList', 'admin_header' ));

class InvoiceList extends \WP_List_Table
{

    private $rows_per_page = 15;
    private $total_rows = 0;

    public $total_trash_rows = 0;

    public function __construct()
    {
        parent::__construct();
    }

    public static function admin_header()
    {
        $page = ( isset($_GET['page']) ) ? esc_attr($_GET['page']) : false;
        if ('wcinvoicepdf_invoices' != $page) {
            return;
        }

        echo '<style type="text/css">';
        echo '.wp-list-table .column-ID { width: 40px; }';
        echo '.wp-list-table .column-created { width: 150px; }';
        //echo '.wp-list-table .column-status { width: 100px; }';
        echo '.wp-list-table .column-due_date { width: 200px; }';
        
        echo '</style>';
    }

    public function get_sortable_columns()
    {
        $sortable = [
            'customer_name' => ['customer_name', true],
            'status' => ['status', true],
            'created' => ['created', true],
            'due_date' => ['due_date', true],
            'paid_date' => ['paid_date', true]
        ];
        return $sortable;
    }

    public function get_columns()
    {
        $columns = [
            'cb' => '<input type="checkbox" />',
            'ID' => 'ID',
            'invoice_number' => __('Invoice', 'wc-invoice-pdf'),
            'customer_name'  => __('Customer', 'woocommerce'),
            'order_id'   => __('Order', 'woocommerce'),
            'status' => __('Status', 'wc-invoice-pdf'),
            'created'        => __('Created at', 'woocommerce'),
            'due_date'    => __('Due at', 'wc-invoice-pdf'),
            'paid_date' => __('Paid at', 'wc-invoice-pdf')
        ];
        return $columns;
    }
    
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'ID':
            case 'customer_name':
            case 'created':
            case 'due_date':
            case 'paid_date':
                return $item->$column_name;
            case 'status':
                return Invoice::GetStatus($item->$column_name);
            default:
                return print_r($item, true) ; //Show the whole array for troubleshooting purposes
        }
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="invoice[]" value="%s" />', $item->ID);
    }

    public function column_status($item)
    {
        $page = ( isset($_REQUEST['page']) ) ? urlencode($_REQUEST['page']) : '';
        $action = [];

        if (!$item->deleted) {
            $actions = [
                'sent' => sprintf('<a href="?page=%s&action=%s&id=%s">'. __('Sent', 'wc-invoice-pdf').'</a>', $page, 'sent', $item->ID),
                'paid' => sprintf('<a href="?page=%s&action=%s&id=%s">'. __('Paid', 'wc-invoice-pdf').'</a>', $page, 'paid', $item->ID),
                'cancel' => sprintf('<a href="?page=%s&action=%s&id=%s">'. __('Canceled', 'wc-invoice-pdf').'</a>', $page, 'cancel', $item->ID),
            ];
        }
        
        return sprintf('%s %s', Invoice::GetStatus($item->status, true), $this->row_actions($actions));
    }

    public function column_order_id($item)
    {
        $stat = wc_get_order_statuses();
        $recurr = '';
        if (!empty($item->ispconfig_period)) {
            $recurr = __('Payment period', 'wc-invoice-pdf') .': ' . __(Invoice::$PERIOD[$item->ispconfig_period], 'wc-invoice-pdf');
        }
        return '<a href="post.php?post='.$item->order_id. '&action=edit" >#' . $item->order_id. ' ('.$stat[$item->post_status].')</a><br />' . $recurr;
    }

    public function column_customer_name($item)
    {
        $res = sprintf('<a href="user-edit.php?user_id=%d">%s</a>', $item->user_id, $item->customer_name);
        $res.="<br /> ". $item->user_email;
        return $res;
    }
    
    public function column_invoice_number($item)
    {
        $page = ( isset($_REQUEST['page']) ) ? urlencode($_REQUEST['page']) : '';
        $action = [];
        
        if (!$item->deleted) {
            $actions = [
                'delete'=> sprintf('<a href="?page=%s&action=%s&id=%s" onclick="WCInvoicePdfAdmin.ConfirmDelete(this)" data-name="%s">Delete</a>', $page, 'delete', $item->ID, $item->invoice_number),
                'quote' => sprintf('<a href="?page=wcinvoicepdf_invoice&order=%s&offer=1" target="_blank">%s</a>', $item->order_id, __('Offer', 'wc-invoice-pdf')),
            ];
        }
        
        return sprintf('<a target="_blank" href="?page=wcinvoicepdf_invoice&invoice=%s">%s</a> %s', $item->ID, $item->invoice_number, $this->row_actions($actions));
    }

    public function column_due_date($item)
    {
        if ($item->deleted) {
            return $item->due_date;
        }
        $result = '<a href="javascript:void(0)" data-id="'.$item->ID.'" onclick="WCInvoicePdfAdmin.EditDueDate(this)">'.$item->due_date.'</a>';
        if ($item->reminder_sent > 0) {
            $result.= "<br />" . sprintf(__('%s reminders sent', 'wc-invoice-pdf'), $item->reminder_sent);
        }
        return $result;
    }

    public function column_paid_date($item)
    {
        return '<a href="javascript:void(0)" data-id="'.$item->ID.'" onclick="WCInvoicePdfAdmin.EditPaidDate(this)">'.$item->paid_date.'</a>';
    }
    
    public function get_bulk_actions()
    {
        $actions = [
          'export' => __('Export', 'wc-invoice-pdf')
        ];
        return $actions;
    }

    public function process_bulk_actions()
    {
        if (!empty($_POST['action']) && $_POST['action'] == 'export') {
            if (empty($_POST['invoice'])) {
                echo '<div class="wrap"><div class="notice notice-info"><p>Nothing to export</p></div></div>';
                return;
            }

            $invoiceIds = array_map(function ($value) {
                return intval($value);
            }, $_POST['invoice']);
            $exp = new InvoiceExport($invoiceIds);
            $exp->GnuCash();
        }
    }

    public function prepare_items()
    {
        global $wpdb;

        $this->process_bulk_actions();

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $query = "SELECT i.id AS ID, i.customer_id, i.invoice_number, i.wc_order_id, i.created, i.due_date, i.paid_date, i.status, i.deleted, i.reminder_sent, u.user_login AS customer_name, u.user_email AS user_email, u.ID AS user_id, p.ID AS order_id, p.post_status, pm.meta_value AS ispconfig_period 
                    FROM {$wpdb->prefix}".Invoice::TABLE." AS i 
                    LEFT JOIN {$wpdb->users} AS u ON u.ID = i.customer_id
                    LEFT JOIN {$wpdb->posts} AS p ON p.ID = i.wc_order_id
                    LEFT JOIN {$wpdb->postmeta} AS pm ON (p.ID = pm.post_id AND pm.meta_key = '_ispconfig_period')
                    WHERE";
        
        if (isset($_GET['post_status']) && $_GET['post_status'] == 'deleted') {
            $query .= ' i.deleted = 1';
        } else {
            $query .= ' i.deleted = 0';
        }
                    
        $action = preg_replace('/\W/', '', isset($_GET['action']) ? $_GET['action'] : '');
        $invoiceId = isset($_GET['id']) ? intval($_GET['id']) : null;

        if (isset($_GET['page']) && $_GET['page'] == 'wcinvoicepdf_invoices' && !empty($action)) {
            if ($invoiceId !== null) {
                $invoice = new Invoice($invoiceId);
            }
            
            switch ($action) {
                case 'delete':
                    $invoice->Delete();
                    break;
                case 'sent':
                    $invoice->Submitted();
                    $invoice->Save();
                    break;
                case 'paid':
                    $invoice->Paid();
                    $invoice->Save();
                    break;
                case 'cancel':
                    $invoice->Cancel();
                    $invoice->Save();
                    break;
                case 'filter':
                    if (!empty($_GET['customer_id'])) {
                        $query = $wpdb->prepare($query . " AND i.customer_id = %d", intval($_GET['customer_id']));
                    }
                    if (!empty($_GET['recur_only'])) {
                        $query.= " AND pm.meta_value IS NOT NULL";
                    }
                    break;
            }
        }

        $this->total_trash_rows = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}".Invoice::TABLE." AS i WHERE deleted = 1");

        $this->applySorting($query);
        $this->applyPaging($query);
        $this->items = $wpdb->get_results($query, OBJECT);

        $this->postPaging();
    }

    private function applySorting(&$query)
    {
        if (!isset($_GET['orderby'])) {
            $_GET['orderby'] = 'created';
        }
        
        if (!isset($_GET['order'])) {
            $_GET['order'] = 'desc';
        }

        $_GET['orderby'] = $orderby = preg_replace('/\W/', '', $_GET['orderby']);
        $_GET['order'] = $order = preg_replace('/\W/', '', $_GET['order']);
        
        $query .= " ORDER BY $orderby $order";
    }

    private function applyPaging(&$query)
    {
        // paging settings
        $page = $this->get_pagenum();
        $offset = $this->rows_per_page * $page - $this->rows_per_page;

        $query = str_replace('SELECT ', 'SELECT SQL_CALC_FOUND_ROWS ', $query);
        $query.= " LIMIT {$this->rows_per_page} OFFSET {$offset}";
    }

    private function postPaging()
    {
        global $wpdb;
        $total_rows = $wpdb->get_var("SELECT FOUND_ROWS();");

        $this->set_pagination_args([
            'total_items' => $total_rows,   //WE have to calculate the total number of items
            'per_page'    => $this->rows_per_page      //WE have to determine how many items to show on a page
        ]);

        return $total_rows;
    }
}
