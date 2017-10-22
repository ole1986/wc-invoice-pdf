<?php
namespace WCInvoicePdf\Model;

class InvoiceTask {

    public static function Run(){
        $me = new self();
        $me->payment_reminder();
        $me->payment_recur();
        $me->payment_recur_reminder();
    }

    public function __construct(){ }

    /**
     * SCHEDULE: Daily reminder for administrators on invoices which are due
     */
    public function payment_reminder(){
        global $wpdb;

        if(empty(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_payment_reminder'])) {
            error_log("WARNING: Payment reminder for adminstrators is disabled");
            return -1;
        }
            

        if(!filter_var(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_mail_reminder'], FILTER_VALIDATE_EMAIL))
            return -2;

        $res = $wpdb->get_results("SELECT i.*, u.display_name, u.user_login FROM {$wpdb->prefix}".Invoice::TABLE." AS i 
                                LEFT JOIN {$wpdb->posts} AS p ON (p.ID = i.wc_order_id)
                                LEFT JOIN {$wpdb->users} AS u ON u.ID = i.customer_id
                                WHERE i.deleted = 0 AND (i.status & 2) = 0 AND DATE(i.due_date) <= CURDATE()", OBJECT);
            
        // remind admin when customer has not yet paid the invoices
        if(!empty($res)) {
            $subject = sprintf("Payment reminder - %s outstanding invoice(s)", count($res));

            $content = '';
            foreach ($res as $k => $v) {
                
                $userinfo = "'{$v->display_name}' ($v->user_login)";
                $u = get_userdata($v->customer_id);
                if($u) $userinfo = "'{$u->first_name} {$u->last_name}' ($u->user_email)";

                $content .= "\n\n" . __('Invoice', 'wc-invoice-pdf').": {$v->invoice_number}\n". __('Customer', 'woocommerce') .": $userinfo\n" . __('Due at', 'wc-invoice-pdf') .": " . date('d.m.Y', strtotime($v->due_date));
            }
            // attach the pdf documents via string content
            add_action('phpmailer_init', function($phpmailer) use($res){
                foreach($res as $v) {
                    $phpmailer->AddStringAttachment($v->document, $v->invoice_number . '.pdf');
                }
            });

            $message = sprintf(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_payment_message'], $content);

            error_log("invoice_payment_reminder - Sending reminder to: " . \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_mail_reminder']);
            $ok = wp_mail(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_mail_reminder'], 
                        $subject,
                        $message,
                        'From: '. \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_mail_sender']);
            return $ok;
        }
        return 0;
    }

    /**
     * SCHEDULE: Submit the recurring invoices (based on the period) to customers email address (daily checked)
     */
    public function payment_recur(){
        global $wpdb;

        if(empty(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_recur'])) {
            error_log("WARNING: Recurring payment submission is disabled");
            return -1;
        }

        $res = $wpdb->get_results("SELECT p.ID,p.post_date_gmt, pm.meta_value AS payment_period FROM {$wpdb->posts} p 
                                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                                WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-completed' AND pm.meta_key = '_ispconfig_period'", OBJECT);
        
        // remind admin about new recurring invoices
        if(!empty($res)) {
            $curDate = new DateTime();

            $messageBody = \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_recur_message'];

            foreach ($res as $v) {
                 $d = new DateTime($v->post_date_gmt);

                if($v->payment_period == 'y') {
                    // yearly
                    $postDate = $d->format('md');
                    $dueDate = $curDate->format('md');
                } else if($v->payment_period == 'm') {
                    // monthly
                    $postDate = $d->format('d');
                    $dueDate = $curDate->format('d');
                } else {
                    continue;
                }

                if(isset($dueDate, $postDate) && $dueDate == $postDate) {
                    // send the real invoice
                    $order = new WC_Order($v->ID);
                    $invoice = new IspconfigInvoice($order);
                    $invoice->makeRecurring();

                    add_action('phpmailer_init', function($phpmailer) use($invoice){
                        $phpmailer->clearAttachments();
                        $phpmailer->AddStringAttachment($invoice->document, $invoice->invoice_number . '.pdf');
                    });

                    // CHECK IF IT IS TEST - DO NOT SEND TO CUSTOMER THEN
                    if(!empty(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_recur_test']))
                        $recipient = \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_mail_reminder'];
                    else
                        $recipient = $order->get_billing_email();
                    error_log("INFO: Sending recurring invoice ".$invoice->invoice_number." to: " . $recipient);

                    $success = wp_mail($recipient, 
                            __('Invoice', 'wc-invoice-pdf') . ' ' . $invoice->invoice_number,
                            sprintf($messageBody, $invoice->invoice_number),
                            'From: '. \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_mail_sender']);
                    
                    if($success)
                    {
                        $invoice->Submitted();

                        $invoice->Save();
                        $order->add_order_note("Invoice ".$invoice->invoice_number." sent to: " . $recipient);
                    }
                }
            }
        }
        return 0;
    }
    /**
     * SCHEDULE: Recurring reminder being sent to customer when due date is older "wc_recur_reminder_age"
     */
    public function payment_recur_reminder() {
        global $wpdb;
        
        if(empty(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_recur_reminder'])) {
            error_log("WARNING: Recurring reminder on due invoices is disabled");
            return -1;
        }

        $age = intval(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_recur_reminder_age']);
        $interval = intval(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_recur_reminder_interval']);

        $max = intval(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_recur_reminder_max']);

        $messageBody = \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_recur_reminder_message'];

        // fetch all invoices which have status = Sent (ignore all invoice which are already marked as paid)
        $sql = "SELECT * FROM {$wpdb->prefix}".Invoice::TABLE." WHERE `status` = 1 AND DATE_ADD(NOW(), INTERVAL -{$age} DAY) > due_date AND reminder_sent < $max";

        $res = $wpdb->get_results($sql, OBJECT);

        if(!empty($res)) {
            foreach ($res as $v) {
                $due_date = new DateTime($v->due_date);
                $due_date->add(new DateInterval("P{$age}D"));

                $diff  = $due_date->diff(new DateTime());
                $diffDays = intval($diff->format("%a"));
                $rest = $diffDays % $interval;

                if($rest > 0) {
                    error_log("Skipping recurring reminder for {$v->invoice_number}");
                    continue;
                }

                $v->reminder_sent++;

                $order = new WC_Order($v->wc_order_id);

                if(!empty(\WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_recur_test']))
                    $recipient = \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_mail_reminder'];
                else
                    $recipient = $order->get_billing_email();
                
                
                error_log("INFO: Sending recurring reminder for {$v->invoice_number} to $recipient | DIFF: $diffDays | REST: $rest");

                // attach invoice pdf into php mailer
                add_action('phpmailer_init', function($phpmailer) use($v){
                    $phpmailer->clearAttachments();
                    $phpmailer->AddStringAttachment($v->document, $v->invoice_number . '.pdf');
                });

                $success = wp_mail($recipient, 
                    __('Payment reminder', 'wc-invoice-pdf') . ' ' . $v->invoice_number,
                    sprintf($messageBody, $v->invoice_number),
                    'From: '. \WCInvoicePdf\WCInvoicePdf::$OPTIONS['wc_mail_sender']
                );
        
                if($success)
                {
                    $order->add_order_note("Invoice reminder #{$v->reminder_sent} for ".$v->invoice_number." sent to " . $recipient);
                    $wpdb->query( $wpdb->prepare("UPDATE {$wpdb->prefix}".Invoice::TABLE." SET reminder_sent = {$v->reminder_sent} WHERE ID = %s", $v->ID ) );
                }
            }
        }

        return 0;
    }
}