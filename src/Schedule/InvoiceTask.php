<?php
namespace WcRecurring\Schedule;

use WcRecurring\Helper\Substitute;
use WcRecurring\WcRecurringIndex;
use WcRecurring\Model\Invoice;
use WcRecurring\Model\Placeholder\CompanyDetails;
use WcRecurring\Model\Placeholder\InvoiceDetails;

class InvoiceTask
{
    public static function Run()
    {
        $me = new self();
        $me->payment_notify();
        $me->payment_recur();
        $me->payment_submit();
        $me->payment_reminder();
    }

    public static function DoAjax()
    {
        $task = new self();

        $name = esc_attr($_POST['name']);

        switch ($name) {
            case 'notify':
                $result = $task->payment_notify();
                break;
            case 'recur':
                $result = $task->payment_recur();
                break;
            case 'submit':
                $result = $task->payment_submit();
                break;
            case 'reminder':
                $result = $task->payment_reminder();
                break;
        }

        echo json_encode(intval($result));
        wp_die();
    }

    /**
     * SCHEDULE: Daily reminder for administrators on invoices which are due
     */
    public function payment_notify()
    {
        global $wpdb;

        if (empty(WcRecurringIndex::$OPTIONS['wc_payment_reminder'])) {
            error_log("WARNING: Payment reminder for adminstrators is disabled");
            return -1;
        }
            

        if (!filter_var(WcRecurringIndex::$OPTIONS['wc_mail_reminder'], FILTER_VALIDATE_EMAIL)) {
            return -2;
        }

        $res = $wpdb->get_results("SELECT i.*, u.display_name, u.user_login FROM {$wpdb->prefix}".Invoice::TABLE." AS i 
                                LEFT JOIN {$wpdb->posts} AS p ON (p.ID = i.wc_order_id)
                                LEFT JOIN {$wpdb->users} AS u ON u.ID = i.customer_id
                                WHERE i.deleted = 0 AND i.status < ".Invoice::PAID." AND i.status >= ".Invoice::SUBMITTED." AND DATE(i.due_date) <= CURDATE()", OBJECT);
            
        // remind admin when customer has not yet paid the invoices
        if (!empty($res)) {
            $subject = sprintf("Payment reminder - %s outstanding invoice(s)", count($res));

            $content = '';
            foreach ($res as $k => $v) {
                $userinfo = "'{$v->display_name}' ($v->user_login)";
                $u = get_userdata($v->customer_id);
                if ($u) {
                    $userinfo = "'{$u->first_name} {$u->last_name}' ($u->user_email)";
                }

                $content .= "\n\n" . __('Invoice', 'wc-invoice-pdf').": {$v->invoice_number}\n". __('Customer', 'woocommerce') .": $userinfo\n" . __('Due at', 'wc-invoice-pdf') .": " . date('d.m.Y', strtotime($v->due_date));
            }
            // attach the pdf documents via string content
            add_action('phpmailer_init', function ($phpmailer) use ($res) {
                foreach ($res as $v) {
                    $phpmailer->AddStringAttachment($v->document, $v->invoice_number . '.pdf');
                }
            });

            $message = sprintf(WcRecurringIndex::$OPTIONS['wc_payment_message'], $content);

            error_log("invoice_payment_reminder - Sending reminder to: " . WcRecurringIndex::$OPTIONS['wc_mail_reminder']);
            $ok = wp_mail(
                WcRecurringIndex::$OPTIONS['wc_mail_reminder'],
                $subject,
                $message,
                'From: '. WcRecurringIndex::$OPTIONS['wc_mail_sender']
            );
            return $ok;
        }
        return 0;
    }

    /**
     * SCHEDULE: Store recurring invoices when the created date matches the period (monthly/yearly)
     */
    public function payment_recur()
    {
        global $wpdb;

        if (empty(WcRecurringIndex::$OPTIONS['wc_recur'])) {
            error_log("WARNING: Recurring payment submission is disabled");
            return -1;
        }

        $res = $wpdb->get_results("SELECT p.ID,p.post_date_gmt, pm.meta_value AS payment_period FROM {$wpdb->posts} p 
                                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                                WHERE DATE_FORMAT(NOW(), '%d%m') = DATE_FORMAT(post_date, '%d%m') AND p.post_type = 'shop_order' AND p.post_status = 'wc-completed' AND pm.meta_key = '_ispconfig_period'", OBJECT);
        
        if (empty($res)) {
            return 0;
        }

        $curDate = new \DateTime();

        foreach ($res as $v) {
                $d = new \DateTime($v->post_date_gmt);

            if ($v->payment_period == 'y') {
                // yearly
                $postDate = $d->format('md');
                $dueDate = $curDate->format('md');
            } elseif ($v->payment_period == 'm') {
                // monthly
                $postDate = $d->format('d');
                $dueDate = $curDate->format('d');
            } else {
                continue;
            }

            if (isset($dueDate, $postDate) && $dueDate == $postDate) {
                // send the real invoice
                $order = new \WC_Order($v->ID);
                $invoice = new Invoice($order);
                $invoice->makeRecurring();
                $invoice->Save();
                // internal notice in the associated order
                $order->add_order_note(sprintf(__("Recurring invoice %s has been created", 'wc-invoice-pdf'), $invoice->invoice_number));
            }
        }

        return 0;
    }

    public function payment_submit()
    {
        global $wpdb;

        // get all invoices from table which are not yet submitted
        $sql = "SELECT * FROM {$wpdb->prefix}".Invoice::TABLE." WHERE deleted = 0 AND `status` = 0";
        $res = $wpdb->get_results($sql, OBJECT);

        if (empty($res)) {
            error_log("No pending invoices to submit");
            return 0;
        }

        $messageBody = WcRecurringIndex::$OPTIONS['wc_recur_message'];
        $dateFormat = new \IntlDateFormatter(get_locale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
        $companyDetails = new CompanyDetails();
        $substitude = new Substitute($companyDetails);

        foreach ($res as $v) {
            $invoice = new Invoice($v);
            $order = $invoice->order;

            $invoiceDetails = new InvoiceDetails($invoice, false, $dateFormat);
            $substitude->apply($invoiceDetails);

            add_action('phpmailer_init', function ($phpmailer) use ($invoice) {
                $phpmailer->clearAttachments();
                $phpmailer->AddStringAttachment($invoice->document, $invoice->invoice_number . '.pdf');
            });

            // CHECK IF IT IS TEST - DO NOT SEND TO CUSTOMER THEN
            if (!empty(WcRecurringIndex::$OPTIONS['wc_recur_test'])) {
                $recipient = WcRecurringIndex::$OPTIONS['wc_mail_reminder'];
            } else {
                $recipient = $order->get_billing_email();
            }

            error_log("INFO: Sending invoice ".$invoice->invoice_number." to: " . $recipient);

            $success = wp_mail(
                $recipient,
                __('Invoice', 'wc-invoice-pdf') . ' ' . $invoice->invoice_number,
                $substitude->message($messageBody),
                'From: '. WcRecurringIndex::$OPTIONS['wc_mail_sender']
            );

            if ($success) {
                $invoice->Submitted();
                $invoice->Save();

                $order->add_order_note(sprintf(__("The invoice %s has been submitted to %s", 'wc-invoice-pdf'), $invoice->invoice_number, $recipient));
            }
        }
    }

    /**
     * SCHEDULE: Recurring reminder being sent to customer when due date is older "wc_recur_reminder_age"
     */
    public function payment_reminder()
    {
        global $wpdb;
        
        if (empty(WcRecurringIndex::$OPTIONS['wc_recur_reminder'])) {
            error_log("WARNING: Payment reminder on due invoices is disabled");
            return -1;
        }

        $age = intval(WcRecurringIndex::$OPTIONS['wc_recur_reminder_age']);
        $interval = intval(WcRecurringIndex::$OPTIONS['wc_recur_reminder_interval']);

        $max = intval(WcRecurringIndex::$OPTIONS['wc_recur_reminder_max']);

        $messageBody = WcRecurringIndex::$OPTIONS['wc_recur_reminder_message'];
        $dateFormat = new \IntlDateFormatter(get_locale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
        $companyDetails = new CompanyDetails();
        $substitude = new Substitute($companyDetails);

        // fetch all invoices which have status = Sent (ignore all invoice which are already marked as paid)
        $sql = "SELECT * FROM {$wpdb->prefix}".Invoice::TABLE." WHERE deleted = 0 AND NOT (`status` & ".Invoice::PAID.") AND `status` < ".Invoice::CANCELED." AND DATE_ADD(NOW(), INTERVAL -{$age} DAY) > due_date AND reminder_sent < $max";

        $res = $wpdb->get_results($sql, OBJECT);

        if (!empty($res)) {
            foreach ($res as $v) {
                $due_date = new \DateTime($v->due_date);

                $diff  = $due_date->diff(new \DateTime());
                $diffDays = intval($diff->format("%a"));

                $acceptableDays = $age + ($interval * intval($v->reminder_sent));

                if ($diffDays <= $acceptableDays) {
                    // age: 2 days, interval: 2 days
                    // 4 days later for the second reminder
                    // 6 days later for the third reminder
                    $nextReminderNo = $v->reminder_sent + 1;
                    error_log("Skipping reminder for {$v->invoice_number} as its the {$nextReminderNo} reminder and {$diffDays} days are in acceptable range of {$acceptableDays} days");
                    continue;
                }

                $v->reminder_sent++;

                $invoice = new Invoice($v);
                $order = $invoice->order;
                $invoiceDetails = new InvoiceDetails($invoice, false, $dateFormat);
                $substitude->apply($invoiceDetails);

                if (!empty(WcRecurringIndex::$OPTIONS['wc_recur_test'])) {
                    $recipient = WcRecurringIndex::$OPTIONS['wc_mail_reminder'];
                } else {
                    $recipient = $order->get_billing_email();
                }
                
                
                error_log("INFO: Sending recurring reminder number {$v->reminder_sent} for {$v->invoice_number} to $recipient with $diffDays days due");

                // attach invoice pdf into php mailer
                add_action('phpmailer_init', function ($phpmailer) use ($v) {
                    $phpmailer->clearAttachments();
                    $phpmailer->AddStringAttachment($v->document, $v->invoice_number . '.pdf');
                });

                $success = wp_mail(
                    $recipient,
                    __('Payment reminder', 'wc-invoice-pdf') . ' ' . $v->invoice_number,
                    $substitude->message($messageBody),
                    'From: '. WcRecurringIndex::$OPTIONS['wc_mail_sender']
                );
        
                if ($success) {
                    $order->add_order_note(sprintf(__('A reminder #%s for invoice %s has been submitted to %s', 'wc-invoice-pdf'), $v->reminder_sent, $v->invoice_number, $recipient));
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}".Invoice::TABLE." SET reminder_sent = {$v->reminder_sent} WHERE ID = %s", $v->ID));
                }
            }
        }

        return 0;
    }
}
