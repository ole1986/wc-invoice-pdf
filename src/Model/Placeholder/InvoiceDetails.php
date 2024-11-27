<?php

namespace WcRecurring\Model\Placeholder;

use WcRecurring\WcRecurringIndex;
use WcRecurring\Model\Invoice;

class InvoiceDetails
{
    public $customer_name;
    public $invoice_number;
    public $due_date;
    public $invoice_created;
    public $due_days;
    public $next_due_days;

    public function __construct(Invoice $invoice, bool $isOffer, \IntlDateFormatter $dateFormatter)
    {
        $this->invoice_number =  !$isOffer ? $invoice->invoice_number : $invoice->offer_number;
        $this->invoice_created = $dateFormatter->format(strtotime($invoice->created));
        $this->due_date = $dateFormatter->format(strtotime($invoice->due_date));
        $this->due_days = WcRecurringIndex::$OPTIONS['wc_invoice_due_days'];
        $this->next_due_days = WcRecurringIndex::$OPTIONS['wc_recur_reminder_interval'];

        $customer = $invoice->order->get_user();
        $this->customer_name = !empty($customer) ? $customer->display_name : 'Guest';
    }
}
