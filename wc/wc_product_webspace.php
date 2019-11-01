<?php
add_filter('woocommerce_cart_item_quantity', ['WC_Product_Webspace', 'Period'], 10, 3);
add_filter('woocommerce_update_cart_action_cart_updated', ['WC_Product_Webspace', 'CartUpdated']);
add_action('woocommerce_webspace_add_to_cart', ['WC_ISPConfigProduct', 'add_to_cart'], 30);

add_filter('woocommerce_product_data_tabs', ['WC_Product_Webspace','ispconfig_product_data_tab']);

add_action('woocommerce_product_data_panels', ['WC_Product_Webspace','ispconfig_product_data_fields']);
add_action('woocommerce_process_product_meta_webspace', ['WC_Product_Webspace', 'webspace_metadata_save']);

add_action('admin_footer', ['WC_Product_Webspace', 'jsRegister']);

add_filter('product_type_selector', ['WC_Product_Webspace','register']);

class WC_Product_Webspace extends WC_ISPConfigProduct
{
    public static $OPTIONS;

    public $sold_individually = true;
    public $product_type = "webspace";

    public function __construct($product = 0)
    {
        self::$OPTIONS = ['m' => __('monthly', 'wc-invoice-pdf'), 'y' => __('yearly', 'wc-invoice-pdf') ];

        $this->supports[]   = 'ajax_add_to_cart';
        //$this->product_type = "webspace";
        parent::__construct($product);
    }

    public static function register($types)
    {
        $types[ 'webspace' ] = __('Webspace', 'wc-invoice-pdf');
        return $types;
    }

    public static function jsRegister()
    {
        global $product_object;
        ?>
        <script type='text/javascript'>
            jQuery( document ).ready( function() {
                jQuery('.options_group.pricing' ).addClass( 'show_if_webspace' ).show();
                <?php if ($product_object instanceof self) : ?>
                jQuery('.general_options').show();
                jQuery('.general_options > a').trigger('click');
                <?php endif; ?>
            });
        </script>
        <?php
    }

    public static function ispconfig_product_data_tab($product_data_tabs)
    {
        $product_data_tabs['general']['class'][] = 'show_if_webspace';
        $product_data_tabs['linked_product']['class'][] = 'hide_if_webspace';
        $product_data_tabs['attribute']['class'][] = 'hide_if_webspace';
        $product_data_tabs['advanced']['class'][] = 'hide_if_webspace';

        if (class_exists("Ispconfig")) {
            $product_data_tabs['ispconfig_tab'] = array(
                'label' => __('ISPConfig 3', 'wp-ispconfig3'),
                'target' => 'ispconfig_data_tab',
                'class' => 'show_if_webspace'
            );
        }
        
        return $product_data_tabs;
    }

    public static function ispconfig_product_data_fields()
    {
        if (!class_exists("Ispconfig")) {
            return;
        }
        
        echo '<div id="ispconfig_data_tab" class="panel woocommerce_options_panel">';
        try {
            $templates = Ispconfig::$Self->withSoap()->GetClientTemplates();
          
            $options = [0 => 'None'];
            foreach ($templates as $v) {
                $options[$v['template_id']] = $v['template_name'];
            }
            woocommerce_wp_select(['id' => '_ispconfig_template_id', 'label' => '<strong>Client Limit Template</strong>', 'options' => $options]);

            Ispconfig::$Self->closeSoap();
        } catch (SoapFault $e) {
            echo "<div style='color:red; margin: 1em;'>ISPConfig SOAP Request failed: " . $e->getMessage() . '</div>';
        }
  
        echo '</div>';
    }

    /**
     * BACKEND: Used to save the template ID for later use (Cart/Order)
     */
    public static function webspace_metadata_save($post_id)
    {
        if (!empty($_POST['_ispconfig_template_id'])) {
            update_post_meta($post_id, '_ispconfig_template_id', $_POST['_ispconfig_template_id']);
        }
    }

    public function getISPConfigTemplateID()
    {
        return get_post_meta($this->get_id(), '_ispconfig_template_id', true);
    }

    public function OnCheckout($checkout)
    {
        $templateID = $this->getISPConfigTemplateID();

        if ($templateID >= 1 && $templateID <= 3) {
            echo "<h3>" . __('Your desired domain', 'wc-invoice-pdf') . "</h3>";
            echo '<div><sup>' . __('Please enter a domain you want to host here', 'wc-invoice-pdf') . '</sup></div>';
            woocommerce_form_field(
                'order_domain',
                [
                'type'              => 'text',
                'placeholder'       => '',
                'custom_attributes' => ['data-ispconfig-checkdomain'=>'1']
                 ],
                $checkout->get_value('order_domain')
            );
        }
        echo '<div id="domainMessage" class="ispconfig-msg" style="display:none;"></div>';
    }

    public function OnCheckoutValidate()
    {
        $templateID = $this->getISPConfigTemplateID();
        
        // all products require a DOMAIN to be entered
        if ($templateID >= 1 && $templateID <= 3) {
            try {
                $dom = Ispconfig::$Self->validateDomain($_POST['order_domain']);

                $available = Ispconfig::$Self->withSoap()->IsDomainAvailable($dom);

                Ispconfig::$Self->closeSoap();

                if ($available == 0) {
                    wc_add_notice(__("The domain is not available", 'wp-ispconfig3'), 'error');
                } elseif ($available == -1) {
                    wc_add_notice(__("The domain might not be available", 'wp-ispconfig3'), 'notice');
                }
            } catch (Exception $e) {
                wc_add_notice($e->getMessage(), 'error');
            }
        }
    }

    public function OnCheckoutSubmit($order_id, $item_key, $item)
    {
        if (! empty($_POST['order_domain'])) {
            update_post_meta($order_id, 'Domain', sanitize_text_field($_POST['order_domain']));

            $templateID = $this->getISPConfigTemplateID();
            // no ispconfig product found in order - so skip doing ispconfig related stuff
            if (empty($templateID)) {
                return;
            }

            // WC-InvoicePDF: use external plugin to set the recurring properly
            if ($item['quantity'] == 12) {
                do_action('wcinvoicepdf_order_period', $order_id, 'yearly');
            } else {
                do_action('wcinvoicepdf_order_period', $order_id, 'monthly');
            }
        }
    }

    public function get_price_suffix($price = '', $qty = 1)
    {
        $plural = $qty > 1 ? 's' : '';
        $suffix = __('month' . $plural, 'wc-invoice-pdf');
        
        return ' ' . $suffix;
    }

    public function get_price_html($price = '')
    {
        $price = wc_price(wc_get_price_to_display($this, array( 'price' => $this->get_regular_price() ))) . '&nbsp;' . __('per month', 'wc-invoice-pdf');
        return apply_filters('woocommerce_get_price_html', $price, $this);
    }

    public static function AddToCart($item, $item_key)
    {
        if (get_class($item['data']) != 'WC_Product_Webspace') {
            return $item;
        }
        // empty cart when a webspace product is being added
        // ONLY ONE webspace product is allowed in cart
        WC()->cart->empty_cart();
        return $item;
    }

    /**
     * Display a DropDown (per webspace product) for selecting the period (month / year / ...)
     * Can be customized in $OPTIONS property
     */
    public static function Period($item_qty, $item_key, $item)
    {
        if (get_class($item['data']) != 'WC_Product_Webspace') {
            return $item_qty;
        }
        
        $period = ($item['quantity'] == 12)?'y':'m';

        ?>
        <select style="width:70%;margin-right: 0.3em" name="period[<?php echo $item_key?>]" onchange="jQuery('input[name=\'update_cart\']').prop('disabled', false).trigger('click');">
        <?php foreach (self::$OPTIONS as $k => $v) { ?>
            <option value="<?php echo $k ?>" <?php echo ($period == $k)?'selected':'' ?> ><?php echo $v ?></option>
        <?php } ?>
        </select>
        <?php
        return "";
    }

    /**
     * when the cart gets updated - E.g. the selection has changed
     */
    public static function CartUpdated($isUpdated)
    {
        
        if (!isset($_POST['period'])) {
            return $isUpdated;
        }

        foreach ($_POST['period'] as $item_key => $v) {
            $qty = ($v == 'y')?12:1;
            // update the qty of the product
            WC()->cart->set_quantity($item_key, $qty, false);
        }
        return $isUpdated;
    }
}
