<?php

use WCInvoicePdf\Model\Invoice;

if (!class_exists('WC_Product')) {
    return;
}

abstract class WC_ISPConfigProduct extends WC_Product
{
    abstract public function OnProductCheckoutFields(&$checkout);
    abstract public function OnProductCheckoutValidate();
    abstract public function OnProductCheckoutSubmit($order_id, $item_key, $item);

    public function __construct($product = 0)
    {
        parent::__construct($product);
        // ORDER-PAID: When Order has been paid (can also happen manually as ADMIN)
        add_filter('woocommerce_payment_complete_order_status', array( $this, 'OnPaymentCompleted' ), 10, 3);

        if (!is_admin()) {
            add_action('woocommerce_checkout_before_customer_details', array($this, 'OnCheckoutFields'));
            add_action('woocommerce_checkout_process', array($this, 'OnCheckoutValidate'));
            add_action('woocommerce_checkout_order_processed', array($this, 'OnCheckoutSubmit'));
        }
    }

    public static function add_to_cart()
    {
        wc_get_template('single-product/add-to-cart/simple.php');
    }

    /**
     * CHECKOUT: Add an additional field for the domain name being entered by customer (incl. validation check)
     */
    public function OnCheckoutFields()
    {
        if (WC()->cart->is_empty()) {
            return 0;
        }
        $items =  WC()->cart->get_cart();

        $checkout = WC()->checkout();

        foreach ($items as $p) {
            if (is_subclass_of($p['data'], 'WC_ISPConfigProduct')) {
                $p['data']->OnProductCheckoutFields($checkout);
            }
        }
    }

    /**
     * CHECKOUT: Validate the domain entered by the customer
     */
    public function OnCheckoutValidate()
    {
        if (WC()->cart->is_empty()) {
            return 0;
        }
        $items =  WC()->cart->get_cart();

        foreach ($items as $p) {
            if (is_subclass_of($p['data'], 'WC_ISPConfigProduct')) {
                $p['data']->OnProductCheckoutValidate();
            }
        }
    }

    /**
     * CHECKOUT: Save the domain field entered by the customer
     */
    public function OnCheckoutSubmit($order_id)
    {
        if (WC()->cart->is_empty()) {
            return 0;
        }
        $items =  WC()->cart->get_cart();
        
        foreach ($items as $item_key => $item) {
            if (is_subclass_of($item['data'], 'WC_ISPConfigProduct')) {
                $item['data']->OnProductCheckoutSubmit($order_id, $item_key, $item);
            }
        }
    }

    /**
     * ORDER: When order has changed to status to "processing" assume its payed and REGISTER the user in ISPCONFIG (through SOAP)
     */
    public function registerFromOrder($order)
    {
        $items = $order->get_items();
        $product = $order->get_product_from_item(array_pop($items));
        $templateID = $product->getISPConfigTemplateID();
        
        if (empty($templateID)) {
            $order->add_order_note('<span style="font-weight:bold;">ISPCONFIG NOTICE: No ISPConfig template found - registration skipped</span>');
            return;
        }
        
        if ($order->get_customer_id() == 0) {
            $order->add_order_note('<span style="color: red">ISPCONFIG ERROR: Guest account is not supported. User action required!</span>');
            return;
        }
        
        if ($order->get_item_count() <= 0) {
            $order->add_order_note('<span style="color: red">ISPCONFIG ERROR: No product found</span>');
            wp_update_post(array( 'ID' => $order->id, 'post_status' => 'wc-cancelled' ));
            return;
        }
        
        try {
            $userObj = get_user_by('ID', $order->get_customer_id());
            $password = substr(str_shuffle('!@#$%*&abcdefghijklmnpqrstuwxyzABCDEFGHJKLMNPQRSTUWXYZ23456789'), 0, 12);
            
            $username = $userObj->get('user_login');
            $email =  $userObj->get('user_email');
            
            $domain = get_post_meta($order->id, 'Domain', true);
            $client = $order->get_formatted_billing_full_name();
            
            // overwrite the domain part for free users to only have subdomains
            if ($templateID == 4) {
                if (empty(WPISPConfig3::$OPTIONS['default_domain'])) {
                    throw new Exception("Failed to create free account on template ID: $templateID");
                }
                $domain = "free{$order->id}." . WPISPConfig3::$OPTIONS['default_domain'];
                $username = "free{$order->id}";
            }

            // fetch all templates from ISPConfig
            $limitTemplates = Ispconfig::$Self->withSoap()->GetClientTemplates();
            // filter for only the TemplateID defined in self::$TemplateID
            $limitTemplates = array_filter(
                $limitTemplates,
                function ($v, $k) use ($templateID) {
                    return ($templateID == $v['template_id']);
                },
                ARRAY_FILTER_USE_BOTH
            );
            
            if (empty($limitTemplates)) {
                throw new Exception("No client template found with ID '{$templateID}'");
            }
            $foundTemplate = array_pop($limitTemplates);
            
            $opt = ['company_name' => '',
                    'contact_name' => $client,
                    'street' => '',
                    'zip' => '',
                    'city' => '',
                    'email' => $email,
                    'username' => $username,
                    'password' => $password,
                    'usertheme' => 'DarkOrange',
                    'template_master' => $templateID
            ];

            $webOpt = [ 'domain' => $domain, 'password' => $password,
                        'hd_quota' => $foundTemplate['limit_web_quota'],
                        'traffic_quota' => $foundTemplate['limit_traffic_quota'] ];
            
            if (isset(self::$WEBTEMPLATE_PARAMS[$templateID])) {
                foreach (self::$WEBTEMPLATE_PARAMS as $k => $v) {
                    $webOpt[$k] = $v;
                }
            }
            
            $client = Ispconfig::$Self->GetClientByUser($opt['username']);
            
            // TODO: skip this error when additional packages are being bought (like extra webspace or more email adresses, ...)
            if (!empty($client)) {
                throw new Exception("The user " . $opt['username'] . ' already exists in ISPConfig');
            }
            
            // ISPCONFIG SOAP: add the customer and website for the same client id
            Ispconfig::$Self->AddClient($opt)->AddWebsite($webOpt);

            // ISPCONFIG SOAP: give the user a shell (only for non-free products)
            if ($templateID != 4) {
                Ispconfig::$Self->AddShell(['username' => $opt['username'] . '_shell', 'username_prefix' => $opt['username'] . '_', 'password' => $password ]);
            }
            
            // TODO: Submit possible information to customer through email or WC_Order note
            
            $order->add_order_note('<span style="color: green">ISPCONFIG: User '.$username.' added using limit template '. $foundTemplate['template_name'] .'</span>');

            wp_update_post(array( 'ID' => $order->id, 'post_status' => 'wc-on-hold' ));
            
            Ispconfig::$Self->closeSoap();

            return true;
        } catch (SoapFault $e) {
            $order->add_order_note('<span style="color: red">ISPCONFIG SOAP ERROR (payment): ' . $e->getMessage() . '</span>');
        } catch (Exception $e) {
            $order->add_order_note('<span style="color: red">ISPCONFIG ERROR (payment): ' . $e->getMessage() . '</span>');
        }

        return false;
    }

    public function OnPaymentCompleted($processing, $order_id, $order)
    {
        if (!in_array($processing, ['processing', 'completed'])) {
            return $processing;
        }

        $payment_method = $order->get_payment_method();

        if ($payment_method == 'bacs' && $order->get_status() == 'on-hold') {
            return $processing;
        }

        if ($this->registerFromOrder($order)) {
            $invoice = new Invoice($order);
            $invoice->Paid();
            $invoice->Save();

            return $processing;
        } else {
            $order->set_status('on-hold');
            return;
        }
    }

    public function is_purchasable()
    {
        return true;
    }

    public function get_sold_individually($context = 'view')
    {
        return true;
    }

    public function get_min_purchase_quantity()
    {
        return 1;
    }

    public function get_price($context = 'view')
    {
        return $this->get_regular_price();
    }

    /**
     * Get the add to url used mainly in loops.
     *
     * @return string
     */
    public function add_to_cart_url()
    {
        $url = $this->is_purchasable() && $this->is_in_stock() ? remove_query_arg('added-to-cart', add_query_arg('add-to-cart', $this->id)) : get_permalink($this->id);
        return $url;
    }

    /**
     * Get the add to cart button text.
     *
     * @return string
     */
    public function add_to_cart_text()
    {
        $text = $this->is_purchasable() && $this->is_in_stock() ? __('Add to cart', 'woocommerce') : __('Read more', 'woocommerce');

        return apply_filters('woocommerce_product_add_to_cart_text', $text, $this);
    }
}
