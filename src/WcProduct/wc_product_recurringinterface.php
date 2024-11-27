<?php

interface WC_Product_RecurringInterface
{
    public function invoice_title($item, $invoice);
    public function invoice_qty($item, $invoice);
}
