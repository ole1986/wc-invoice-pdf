<?php
namespace WcRecurring\Model;

use setasign\Fpdi\Tcpdf\Fpdi;
use WcRecurring\Helper\Substitute;
use WcRecurring\WcRecurringIndex;
use WcRecurring\Model\Placeholder\CompanyDetails;
use WcRecurring\Model\Placeholder\InvoiceDetails;

class InvoicePdf
{
    public function __construct()
    {
    }
    /**
     * Used to build a pdf invoice using the WC_Order object
     * @param {WC_Order} $order - the woocommerce order object
     * @param {Array} $invoice-> list of extra data passed as array (E.g. invoice_number, created, due date, ...)
     */
    public function BuildInvoice($invoice, $isOffer = false, $stream = false)
    {
        setlocale(LC_ALL, get_locale());
        
        $order = $invoice->order;

        $isB2C = !empty(WcRecurringIndex::$OPTIONS['wc_pdf_b2c']) ? true : false;

        if (!empty($order->get_meta('_wc_pdf_b2c'))) {
            $isB2C = true;
        }

        $dateFormat = new \IntlDateFormatter(get_locale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
        $formatStyle = \NumberFormatter::CURRENCY;
        $formatter = new \NumberFormatter(get_locale(), $formatStyle);
        $formatter->setSymbol(\NumberFormatter::INTL_CURRENCY_SYMBOL, $order->get_currency());
        $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, $order->get_currency());

        // substition classes
        $companyDetails = CompanyDetails::getInstance();
        $invoiceDetails = new InvoiceDetails($invoice, $isOffer, $dateFormat);
        $substitude = new Substitute($companyDetails);
        $substitude->apply($invoiceDetails);

        $items = $order->get_items();

        // if its first invoice, use shipping item as one-time fee
        if ($invoice->isFirst) {
            $items = array_merge($items, $order->get_items('shipping'));
        }

        $billing_info = str_replace('<br/>', "\n", $order->get_formatted_billing_address());

        if ($isOffer) {
            $headlineText =  __('Offer', 'wc-invoice-pdf') . ' ' . $invoice->offer_number;
        } else {
            $headlineText =  __('Invoice', 'wc-invoice-pdf') . ' ' . $invoice->invoice_number;
        }

        $mediaId = intval(WcRecurringIndex::$OPTIONS['wc_pdf_template']);
        
        $pdf = new Fpdi();
        $pdf->SetFont('helvetica', '', 9);
        $pdf->setPrintHeader(0);
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM * 2);
        $pdf->AddPage();

        $importedPageId = 0;

        if ($mediaId > 0) {
            $mediaPath = get_attached_file($mediaId);
            if ($mediaPath !== false) {
                $pdf->setSourceFile($mediaPath);
                $importedPageId = $pdf->importPage(1);
            }
        }

        if ($importedPageId) {
            $pdf->useImportedPage($importedPageId);
        }

        $pdf->Ln(20);
        $pdf->writeHTMLCell(0, 30, 0, $pdf->GetY(), nl2br($substitude->message(WcRecurringIndex::$OPTIONS['wc_pdf_info'])), 0, 0, false, true, 'R');

        $pdf->Ln(5);
        $pdf->writeHTMLCell(80, 25, PDF_MARGIN_LEFT, $pdf->GetY(), nl2br($billing_info), 0, 1);
        
        if ($order->get_date_paid() && !$isOffer) {
            //$pdf->setFillColor(34, 58, 89);
            $pdf->setFillColor(145, 166, 114);
            $pdf->setTextColor(255, 255, 255);
            $pdf->writeHTMLCell(40, 0, 155, $pdf->GetY(), sprintf(__('Paid at', 'wc-invoice-pdf') . ' %s', $dateFormat->format(strtotime($order->get_date_paid()))), 0, 0, true, true, 'R');
        }

        $pdf->setTextColor(0, 0, 0);
        
        $pdf->Ln(10);
        $pdf->SetFontSize(14);
        $pdf->Cell(0, 0, $headlineText, 0, 1, 'L', 0, '', 0);

        $summary = 0;

        ob_start();
        ?>
        <style>
            table {
                padding: 2;
                width: 100%;
            }
            th {
                background-color: #ccc;
                border-right: 0.2em solid black;
            }
            td {
                border-top: 1 solid black;
                border-right: 0.2em solid black;
            }
        </style>
        <table>
            <tr>
                <th style="width: 5%;text-align: center"><?php _e('No#', 'wc-invoice-pdf') ?></th>
                <th style="width: 45%;"><?php _e('Description', 'wc-invoice-pdf') ?></th>
                <th style="text-align: right; width: 11%;"><?php _e('Qty', 'wc-invoice-pdf') ?></th>
                <th style="text-align: right; width: 19%;"><?php _e('Unit Price', 'wc-invoice-pdf') ?></th>
                <th style="text-align: right; width: 20%; border-right: none !important"><?php _e('Amount', 'wc-invoice-pdf') ?></th>
            </tr>
            <?php
                $fees = $order->get_fees();
                $items = array_merge($items, $fees);
                $i = 1;
            foreach ($items as $v) {
                $product_name = $v['name'];
                            
                if (!isset($v['qty'])) {
                    $v['qty'] = 1;
                }

                if ($v instanceof \WC_Order_Item_Product) {
                    $product = $v->get_product();
                    if ($product instanceof \WC_Product_RecurringInterface) {
                        $qtyStr = $product->invoice_qty($v, $invoice);
                        $product_name = $product->invoice_title($v, $invoice);
                    } else {
                        $qtyStr = number_format($v['qty'], 2, ',', ' ');
                        $product_name = $v['name'];
                        if (!empty($product->sku)) {
                            $product_name .= ' [' . $product->sku . ']';
                        }
                    }
                } else {
                    $qtyStr = number_format($v['qty'], 2, ',', ' ');
                    $product_name = $v['name'];
                }

                $total = round($v['total'], 2);
                $tax = round($v['total_tax'], 2);

                if ($isB2C) {
                    $total += $tax;
                }

                $unitprice = $total / intval($v['qty']);

                $mdcontent = '';
                if ($v instanceof \WC_Order_Item_Product) {
                    $meta = $v->get_meta_data();
                    if (!empty($meta)) {
                        $mdcontent.= implode('', array_map(function ($m) {
                            return "\n<strong>".$m->key.":</strong> ".nl2br($m->value);
                        }, $meta));
                    }
                }

                $color = ($i + 1) % 2 ? "#eee" : "white";

                echo "<tr nobr=\"true\" style=\"background-color: $color\">";
                echo "<td style=\"text-align: center\">$i</td>";
                echo "<td style=\"font-size: small\"><div>$product_name</div><br/>". $mdcontent . "</td>";
                echo "<td style=\"text-align: right;\">$qtyStr</td>";
                echo "<td style=\"text-align: right;\">".$formatter->format($unitprice)."</td>";
                echo "<td style=\"text-align: right; border-right: none !important\">".$formatter->format($total)."</td>";
                echo "</tr>";

                $summary += $total;
                $i++;
            }
            ?>
        </table>
        <?php
        $htmlTable = ob_get_clean();

        $pdf->Ln(5);
        $pdf->SetFontSize(10);
        $pdf->writeHtml($htmlTable);

        $pdf->Ln(5);
        $footerText = $substitude->message($isOffer ? WcRecurringIndex::$OPTIONS['wc_pdf_condition_offer'] : WcRecurringIndex::$OPTIONS['wc_pdf_condition']);
        $pdf->SetFontSize(8);

        $pdf->writeHTMLCell(100, 0, PDF_MARGIN_LEFT, $pdf->GetY(), "<div nobr=\"true\">" . nl2br($footerText) . "</div>", 0, 0);

        $summaryTax = 0;

        $pdf->SetFontSize(10);

        ob_start();
        ?>
        <style>
            table {
                padding: 1.5;
                background-color: #ccc;
                text-align: right;
            }
            th {
                border-bottom: 0.2em solid black;
            }
            td {
                border-bottom: 0.2em solid black;
            }
        </style>
        <table nobr="true">
            <tr>
                <th style="font-weight: bold;"><?php _e('Total', 'wc-invoice-pdf') ?></th>
                <td style="font-weight: bold;"><?php echo $formatter->format($summary) ?></td>
            </tr>
            <?php foreach ($order->get_tax_totals() as $tax) {
                $summaryTax += $tax->amount;
                $tax_rate = \WC_Tax::get_rate_percent($tax->rate_id);
                ?>
            <tr>
                <th style="font-weight: bold;"><?php $isB2C ? printf(__("includes %s %s", 'wc-invoice-pdf'), $tax_rate, $tax->label) : printf(__("plus %s %s", 'wc-invoice-pdf'), $tax_rate, $tax->label) ?></th>
                <td style="font-weight: bold;"><?php echo $formatter->format($tax->amount) ?></td>
            </tr>
            <?php } ?>
            <?php if (!$isB2C) : ?>
            <tr>
                <th style="font-weight: bold;border-bottom: 0.4em solid black;"><?php _e('Gross Amount', 'wc-invoice-pdf') ?></th>
                <td style="font-weight: bold;border-bottom: 0.4em solid black;"><?php echo $formatter->format($summary + $summaryTax) ?></td>
            </tr>
            <?php endif ?>
        </table>
        <?php
        $summaryTable = ob_get_clean();

        $pdf->writeHTML($summaryTable);

        if ($stream) {
            $pdf->Output($isOffer ? $invoice->offer_number : $invoice->invoice_number);
        } else {
            $pdf->Output($isOffer ? $invoice->offer_number : $invoice->invoice_number, 'D');
        }
    }
}
