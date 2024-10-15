<?php
namespace WCInvoicePdf\Model;

class InvoicePdf
{
    public function __construct()
    {
        // only load rospdf library when its necessary to avoid class conflicts
        include_once WCINVOICEPDF_PLUGIN_DIR . 'vendor/rospdf/pdf-php/src/Cezpdf.php';
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

        $isB2C = !empty(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_b2c']) ? true : false;

        if (!empty($order->get_meta('_wc_pdf_b2c'))) {
            $isB2C = true;
        }

        $formatStyle = \NumberFormatter::CURRENCY;
        $formatter = new \NumberFormatter(get_locale(), $formatStyle);
        $formatter->setSymbol(\NumberFormatter::INTL_CURRENCY_SYMBOL, $order->get_currency());
        $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, $order->get_currency());

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

                    
        $pdf = new \Cezpdf('a4');
        $pdf->ezSetMargins(50, 110, 50, 50);

        $mediaId = intval(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_logo']);
        if ($mediaId > 0) {
            $mediaUrl = wp_get_attachment_url($mediaId);
            if ($mediaUrl !== false) {
                $pdf->ezImage($mediaUrl, 0, 250, 'none', 'right');
            }
        }


        $all = $pdf->openObject();
        $pdf->saveState();
        $pdf->setStrokeColor(0, 0, 0, 1);
        $pdf->line(50, 100, 550, 100);

        $pdf->addTextWrap(50, 90, 8, \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_block1']);
        $pdf->addTextWrap(250, 90, 8, \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_block2'], 0);
        $pdf->addTextWrap(550, 90, 8, \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_block3'], 0, 'right');

        $pdf->restoreState();
        $pdf->closeObject();

        $pdf->addObject($all, 'all');

        $pdf->ezSetDy(-60);

        $y = $pdf->y;

        $pdf->ezText(sprintf(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_info'], strftime('%x', strtotime($invoice->created))), 0, ['justification' => 'right']);

        if ($order->get_date_paid() && !$isOffer) {
            $pdf->saveState();
            $pdf->setColor(1, 0, 0);
            $pdf->ezText(sprintf(__('Paid at', 'wc-invoice-pdf') . ' %s', strftime('%x', strtotime($order->get_date_paid()))), 0, ['justification' => 'right']);
            $pdf->restoreState();
        } else {
            $pdf->ezText('');
        }


        $pdf->y = $y;

        $pdf->ezText("<strong>" . \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_addressline'] . "</strong>\n", 8);
        $pdf->line($pdf->ez['leftMargin'], $pdf->y + 5, 200, $pdf->y + 5);

        $pdf->ezText($billing_info, 10);
        $pdf->ezSetDy(-60);

        $pdf->ezText("$headlineText", 14);



        $cols = array('num' => __('No#', 'wc-invoice-pdf'), 'desc' => __('Description', 'wc-invoice-pdf'), 'qty' => __('Qty', 'wc-invoice-pdf'), 'price' => __('Unit Price', 'wc-invoice-pdf'), 'total' => __('Amount', 'wc-invoice-pdf'));
        $colOptions = [
            'num' => ['width' => 30],
            'desc' => [],
            'qty' => ['justification' => 'right', 'width' => 55],
            'price' => ['justification' => 'right', 'width' => 80],
            'total' => ['justification' => 'right', 'width' => 80],
        ];

        $data = [];

        $i = 1;
        $summary = 0;

        $fees = $order->get_fees();
        $items = array_merge($items, $fees);

        foreach ($items as $v) {
            $row = [];

            $product_name = $v['name'];
            //error_log(print_r($v,true));
            $product = null;
            // check if product id is available and fetch the ISPCONFIG tempalte ID
            if (!empty($v['product_id'])) {
                $product = wc_get_product($v['product_id']);
            }
                
            if (!isset($v['qty'])) {
                $v['qty'] = 1;
            }
           
            if (\is_subclass_of($product, 'WC_ISPConfigProduct')) {
                // if its an ISPCONFIG Template product
                $current = new \DateTime($invoice->created);
                $next = clone $current;
                if ($v['qty'] == 1) {
                    $next->add(new \DateInterval('P1M'));
                } elseif ($v['qty'] == 12) {
                    // overwrite the QTY to be 1 MONTH
                    $next->add(new \DateInterval('P12M'));
                }
                $qtyStr = number_format($v['qty'], 0, ',', ' ') . ' ' . $product->get_price_suffix('', $v['qty']);
                $product_name .= "\n<strong>" . __('Period', 'wc-invoice-pdf') . ": " . \strftime('%x', $current->getTimestamp()) ." - ". \strftime('%x', $next->getTimestamp()) . '</strong>';
            } elseif ($product instanceof \WC_Product_Service) {
                // check if product type is "hour" to output hours instead of Qty
                $qtyStr = number_format($v['qty'], 1, ',', ' ');
                $qtyStr.= '' . $product->get_price_suffix('', $v['qty']);
            } else {
                $qtyStr = number_format($v['qty'], 2, ',', ' ');
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
                        return "\n<strong>".$m->key.":</strong> ".$m->value;
                    }, $meta));
                }
            }

            $row['num'] = "$i";
            $row['desc'] = $product_name . "\n" . $mdcontent;
            $row['qty'] = $qtyStr;
            $row['price'] = $formatter->format($unitprice);
            $row['total'] = $formatter->format($total);

            $summary += $total;

            $data[] = $row;
            $i++;
        }

        foreach ($order->get_refunds() as $v) {
            $data[] = [
                "num" => "",
                "desc" => "\n<strong>" .sprintf(__('Refund from %s', 'wc-invoice-pdf'), \strftime('%x', $v->get_date_created()->getTimestamp())) . ":</strong>\n" . $v->reason . "\n",
                "qty" => "",
                "price" => "",
                "total" => "\n" . $formatter->format($v->total),
            ];

            $summary -= $v->amount;
        }
        
        $pdf->ezSetDy(-30);

        $pdf->ezTable($data, $cols, '', ['width' => '500',
                                         'shaded' => 1,
                                         'shadeHeadingCol'=> [0.8,0.8,0.8],
                                         'shadeCol' => [0.94,0.94,0.94],
                                         'splitRows' => !boolval(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_keeprows'] ?? 0),
                                         'gridlines' => EZ_GRIDLINE_HEADERONLY + EZ_GRIDLINE_COLUMNS,
                                         'cols' => $colOptions]);
    
        $colOptions = [
            ['justification' => 'right'],
            ['justification' => 'right']
        ];

        $summaryData = [
            [
                "<strong>".__('Summary', 'wc-invoice-pdf')."</strong>",
                "<strong>".$formatter->format($summary)."</strong>"
            ]
        ];

        $summaryTax = 0;

        foreach ($order->get_tax_totals() as $tax) {
            $summaryTax += $tax->amount;
            $tax_rate = \WC_Tax::get_rate_percent($tax->rate_id);

            if ($isB2C) {
                $taxStr = sprintf(__("includes %s %s", 'wc-invoice-pdf'), $tax_rate, $tax->label);
            } else {
                $taxStr = sprintf(__("plus %s %s", 'wc-invoice-pdf'), $tax_rate, $tax->label);
            }

            $summaryData[] = ['<strong>' . $taxStr . '</strong>', '<strong>' . $formatter->format($tax->amount) . '</strong>'];
        }

        if (!$isB2C) {
            $summaryData[] = ["<strong>".__('Total', 'wc-invoice-pdf')."</strong>", "<strong>".$formatter->format($summary + $summaryTax)."</strong>"];
        }

        $pdf->ezSetDy(-15);

        $footerText = $isOffer ? \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_condition_offer'] : sprintf(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_pdf_condition'], \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_invoice_due_days']);
        $isNewPage = $pdf->ezText("<strong>" . $footerText . "</strong>", 8, ["aright" => 350], true);

        if ($isNewPage) {
            $pdf->ezNewPage();
        }

        $yOffset = $pdf->y;
        $pdf->ezText("<strong>" . $footerText . "</strong>", 8, ["aright" => 350]);

        $pdf->ezSetDy($yOffset - $pdf->y);
        $pdf->ezTable($summaryData, null, '', ['width' => 200, 'gridlines' => 0, 'showHeadings' => 0,'shaded' => 0 ,'xPos' => 'right', 'xOrientation' => 'left', 'cols' => $colOptions ]);

        if ($stream) {
            $pdf->ezStream(['Content-Disposition' => ($isOffer ? $invoice->offer_number : $invoice->invoice_number) . '.pdf' ]);
        } else {
            return $pdf->ezOutput();
        }
    }
}
