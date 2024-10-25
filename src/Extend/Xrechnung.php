<?php

namespace WcRecurring\Extend;

use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use WcRecurring\WcRecurringIndex;
use WcRecurring\Model\Placeholder\CompanyDetails;
use WcRecurring\Model\Placeholder\InvoiceDetails;
use WcRecurring\Model\Invoice;
use WcRecurring\Helper\Substitute;
use WcRecurring\WcExtend\CustomerProperties;

class Xrechnung
{
    public function __construct()
    {
        add_action('wc_recurring_invoice_creating', [$this, 'apply'], 20, 2);
    }

    public function apply(Invoice $invoice, &$invoiceItem)
    {
        $dateFormat = new \IntlDateFormatter(get_locale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        $company = CompanyDetails::getInstance();
        $invoiceDetails = new InvoiceDetails($invoice, false, $dateFormat);
        $substitude = new Substitute($company);
        $substitude->apply($invoiceDetails);

        $order = $invoice->order;

        $taxes = $order->get_items('tax');
        $taxItem = array_pop($taxes);

      // Create an empty invoice document in the EN16931 profile
        $document = ZugferdDocumentBuilder::CreateNew(ZugferdProfiles::PROFILE_EN16931);

      // Add invoice and position information
        $document
        ->setDocumentInformation($invoice->invoice_number, "380", \DateTime::createFromFormat("Y-m-d H:i:s", $invoiceItem['created']), $invoice->order->get_currency())
        ->setDocumentBusinessProcess("urn:fdc:peppol.eu:2017:poacc:billing:01:1.0")
        ->addDocumentNote('Rechnung gemäß Auftrag vom ' . $invoiceItem['created'])
        // Seller info
        ->setDocumentSeller($company->company_name)
        ->addDocumentSellerTaxRegistration("VA", $company->vat_id)
        ->setDocumentSellerAddress($company->address, "", "", $company->postcode, $company->city, $company->country)
        ->setDocumentSellerCommunication('EM', $company->email)
        ->setDocumentSellerContact($company->company_name, null, null, null, $company->email)
        // Customer info
        ->setDocumentBuyer($order->get_billing_company())
        ->setDocumentBuyerAddress($order->get_billing_address_1(), "", "", $order->get_billing_postcode(), $order->get_billing_city(), $order->get_billing_country())
        ->setDocumentBuyerCommunication('EM', $order->get_billing_email())
        // Payment info
        ->addDocumentPaymentMeanToDirectDebit($company->iban, $company->bic)
        ->addDocumentPaymentTerm($substitude->message(WcRecurringIndex::$OPTIONS['wc_pdf_condition']), null, null);

        if ($order->get_customer_id()) {
            $cust_ref = get_user_meta($order->get_customer_id(), CustomerProperties::CUSTOMER_REFERENCE_KEY, true);
            $document->setDocumentBuyerReference(!empty($cust_ref) ? $cust_ref : null);
        }

        $items = $order->get_items();

        $i = 0;
        $total = 0;
        $tax = 0;
        foreach ($items as $item) {
            $total += $item->get_total();
            $tax += $item->get_total_tax();

            $document->addNewPosition(strval($i++));

            $desc =  array_map(function ($v) {
                return $v->key . ': ' . $v->value;
            }, $item->get_meta_data());

            $document->setDocumentPositionProductDetails($item->get_name(), implode("\n\n", $desc), null, null, null, null, null, strval($item->get_product_id))
            ->setDocumentPositionQuantity($item->get_quantity(), "C62")
            ->setDocumentPositionNetPrice(floatval($item->get_total()))
            ->setDocumentPositionLineSummation(floatval($item->get_total()));

            if (!empty($taxItem)) {
                $document->addDocumentPositionTax('S', 'VAT', $taxItem->get_rate_percent());
            }
        }

        if (!empty($taxItem)) {
            $document->addDocumentTaxSimple('S', 'VAT', $total, $tax, $taxItem->get_rate_percent());
        }
        $document->setDocumentSummation($total + $tax, $total + $tax, $total, null, null, $total, $tax);

        // apply to database
        $invoiceItem['xinvoice'] = $document->getContent();
    }
}
