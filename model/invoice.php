<?php

namespace WCInvoicePdf\Model;

use WCInvoicePdf\WCInvoicePdf;

class Invoice
{
    /**
     * database table without WP prefix
     */
    const TABLE = 'ispconfig_invoice';
    const SUBMITTED = 1;
    const PAID = 2;
    const EXPORTED = 8;
    const CANCELED = 128;

    /**
     * Possible status flags
     */
    public static $STATUS = [
        1 => 'Sent',
        2 => 'Paid',
        8 => 'Exported',
        128 => 'Canceled'
    ];

    public static $PERIOD = [
        'm' => 'monthly',
        'y' => 'yearly'
    ];

    /**
     * allowed db columns
     */
    protected static $columns = [
         'customer_id' => 'bigint(20) NOT NULL DEFAULT 0',
         'wc_order_id' => 'bigint(20) NOT NULL',
         'offer_number'=> 'VARCHAR(50) NOT NULL',
         'invoice_number' => 'VARCHAR(50) NOT NULL',
         'document' => 'mediumblob NULL',
         'created' => 'datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\'',
         'status' => 'smallint(6) NOT NULL DEFAULT 0',
         'due_date' => 'datetime NULL',
         'paid_date' => 'datetime NULL',
         'reminder_sent' => 'tinyint(4) NOT NULL',
         'deleted' => 'BOOLEAN NOT NULL DEFAULT FALSE'
    ];

    public $isFirst = false;

    /**
     * constructor call with various load options given in parameter $id
     * @param {mixed} $id ID | WC_Order | stdClass
     */
    public function __construct($id = null)
    {
        if (!empty($id) && is_integer($id)) {
            $this->load($id);
        } elseif (!empty($id) && is_object($id) && (is_a($id, 'WC_Order') || is_subclass_of($id, 'WC_Order'))) {
            $this->loadFromOrder($id);
        } elseif (!empty($id) && is_object($id) && (get_class($id) == 'stdClass' || $id instanceof Invoice)) {
            $this->loadFromStd($id);
        } else {
            $this->makeNew();
        }
    }

    private function load($id)
    {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}" . self::TABLE . " WHERE ID = %d LIMIT 1";
        $item = $wpdb->get_row($wpdb->prepare($query, $id), OBJECT);

        foreach (get_object_vars($item) as $key => $value) {
            $this->$key = $value;
        }
    }
    
    private function loadFromStd($std)
    {
        foreach (get_object_vars($std) as $k => $v) {
            $this->{$k} = $v;
        }
    }

    private function loadFromOrder($order)
    {
        global $wpdb;

        $this->order = $order;

        $this->order->_ispconfig_period = get_post_meta($order->get_id(), "_ispconfig_period", true);

        // get the latest actual from when WC_Order is defined
        $query = "SELECT * FROM {$wpdb->prefix}" . self::TABLE . " WHERE wc_order_id = %d AND deleted = 0 ORDER BY created DESC LIMIT 1";
        $item = $wpdb->get_row($wpdb->prepare($query, $order->get_id()), OBJECT);

        if ($item != null) {
            foreach (get_object_vars($item) as $key => $value) {
                $this->$key = $value;
            }
        } else {
            $this->isFirst = true;
        }
    }

    /**
     * mark current invoice as paid
     */
    public function Paid($withDate = true)
    {
        $this->status |= self::PAID;
        if ($withDate) {
            $this->paid_date = date('Y-m-d H:i:s');
        }
    }

    /**
     * mark current invoice as submitted
     */
    public function Submitted()
    {
        $this->status |= self::SUBMITTED;
    }

    public function Exported()
    {
        $this->status |= self::EXPORTED;
    }

    public function Cancel()
    {
        $this->status = self::CANCELED;
    }

    /**
     * dynamic property loader to lazy load objects
     */
    public function __get($name)
    {
        if ($name == 'order' && !empty($this->wc_order_id)) {
            $this->$name = new \WC_Order($this->wc_order_id);
        }
        return $this->$name;
    }

    /**
     * save the current invoice or overwrite when $this->ID is defined
     */
    public function Save()
    {
        global $wpdb;

        // check for order status
        if ($this->order->get_status() == 'pending' && in_array($this->order->payment_method, ['paypal'])) {
            $this->order->add_order_note("skipped invoice creation because paypal payment is not yet completed");
            return false;
        }

        $item = [];
        foreach (self::$columns as $k => $v) {
            if ($k == 'deleted') {
                continue;
            }
            if (!empty($this->ID) && $k == 'document') {
                continue;
            }
            if (isset($this->$k)) {
                $item[$k] = $this->$k;
            }
        }

        $result = false;
        if (!empty($this->ID)) {
            // do not update the document only the meta data
            $result = $wpdb->update("{$wpdb->prefix}". self::TABLE, $item, ['ID' => $this->ID]);
        } else {
            $result = $wpdb->insert("{$wpdb->prefix}". self::TABLE, $item);
            $this->ID = $wpdb->insert_id;
        }

        return $result;
    }

    /**
     * mark the current invoice as deleted
     */
    public function Delete($hard = false)
    {
        global $wpdb;

        if (empty($this->ID)) {
            return;
        }

        if ($hard) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}".self::TABLE." WHERE deleted = 1 AND ID = %s", $this->ID));
        } else {
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}".self::TABLE." SET deleted = 1 WHERE ID = %s", $this->ID));
        }
    }

    public function makeNew($isOffer = false)
    {
        unset($this->ID);
        unset($this->reminder_sent);
        
        if (!empty($this->order) && is_object($this->order)) {
            $Ym = $this->order->get_date_created()->date('Ym');
            $this->invoice_number = $Ym . '-' . $this->order->get_id() . '-R';
            $this->offer_number = $Ym . '-' . $this->order->get_id() . '-A';
            $this->wc_order_id = $this->order->get_id();
            $this->customer_id = $this->order->get_customer_id();
        } else {
            $this->invoice_number = 'YYYYMM-' . $this->order->get_id() . '-R';
            $this->offer_number = 'YYYYMM-' . $this->order->get_id() . '-A';
        }

        $d = new \DateTime();

        $this->created = $d->format('Y-m-d H:i:s');
        // due date
        $d->add(new \DateInterval('P14D'));
        $this->due_date = $d->format('Y-m-d H:i:s');
        $this->paid_date = null;
        $this->status = 0;

        // (re)create the pdf
        $invoicePdf = new InvoicePdf();
        $this->document = $invoicePdf->BuildInvoice($this, $isOffer);
    }

    public function makeRecurring()
    {
        unset($this->ID);
        unset($this->reminder_sent);

        if (!empty($this->order) && is_object($this->order)) {
            // reset the payment status for recurring invoices (customer has to pay first)
            $this->order->set_date_paid(null);
        }

        $d = new \DateTime();

        if (!empty($this->order) && is_object($this->order)) {
            $this->invoice_number = $d->format('Ym') . '-' . $this->order->get_id() . '-R';
            $this->offer_number = $d->format('Ym') . '-' . $this->order->get_id() . '-A';
            $this->wc_order_id = $this->order->get_id();
            $this->customer_id = $this->order->get_customer_id();
        } else {
            $this->invoice_number = $d->format('Ymd-His') . '-R';
            $this->offer_number = $d->format('Ymd-His') . '-A';
        }
        $this->created = $d->format('Y-m-d H:i:s');
        // due date
        $d->add(new \DateInterval('P' . WCInvoicePdf::$OPTIONS['wc_invoice_due_days'] . 'D'));
        $this->due_date = $d->format('Y-m-d H:i:s');
        $this->paid_date = null;
        $this->status = 0;

        // (re)create the pdf
        $invoicePdf = new InvoicePdf();
        $this->document = $invoicePdf->BuildInvoice($this);
    }

    public static function GetStatus($s, $lang = false)
    {
        $s = intval($s);
        $res = '';
        foreach (self::$STATUS as $key => $value) {
            if ($s > 0 && ($key & $s)) {
                if ($lang) {
                    $res .= __($value, 'wc-invoice-pdf') . ' | ';
                } else {
                    $res .= $value . ' | ';
                }
            }
        }
        return rtrim($res, ' | ');
    }

    public static function DoAjax()
    {
        $result = '';
        if (!empty($_POST['invoice_id'])) {
            $invoice = new self(intval($_POST['invoice_id']));
            if (!empty($_POST['due_date'])) {
                $invoice->due_date = $result = date('Y-m-d H:i:s', strtotime($_POST['due_date']));
            }
            if (!empty($_POST['paid_date'])) {
                $invoice->paid_date = $result = date('Y-m-d H:i:s', strtotime($_POST['paid_date']));
            }

            $invoice->Save();
        }

        echo json_encode($result);
        wp_die();
    }

    public static function install()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}".self::TABLE." (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,";
        foreach (self::$columns as $col => $dtype) {
            $sql.= "$col $dtype,\n";
        }

        $sql.= "UNIQUE KEY id (id) ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
