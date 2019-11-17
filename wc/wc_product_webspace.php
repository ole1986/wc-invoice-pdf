<?php

use WCInvoicePdf\WCInvoicePdf;

// when adding 'webspace' product to cart
add_action('woocommerce_webspace_add_to_cart', ['WC_ISPConfigProduct', 'add_to_cart'], 30);
add_filter('woocommerce_product_data_tabs', ['WC_Product_Webspace','ispconfig_product_data_tab']);
add_action('woocommerce_process_product_meta_webspace', ['WC_Product_Webspace', 'webspace_metadata_save']);

// display the domain inside WC-InvoicePdf metabox
add_action('wcinvoicepdf_invoice_metabox', ['WC_Product_Webspace', 'Metabox']);

add_action('admin_footer', ['WC_Product_Webspace', 'jsRegister']);

add_filter('product_type_selector', ['WC_Product_Webspace','register']);

class WC_Product_Webspace extends WC_ISPConfigProduct
{
    public static $OPTIONS;

    public $product_type = "webspace";

    public function __construct($product = 0)
    {
        self::$OPTIONS = ['m' => __('monthly', 'wc-invoice-pdf'), 'y' => __('yearly', 'wc-invoice-pdf') ];

        $this->supports[]   = 'ajax_add_to_cart';

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
       
        return $product_data_tabs;
    }

    public static function Metabox($post_id)
    {
        $domain = get_post_meta($post_id, 'Domain', true);
        
        if (empty($domain)) {
            return;
        }

        ?>
        <p>
            <label class="post-attributes-label">Domain: </label>
            <?php echo $domain ?>
        </p>
        <?php
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

    public function OnProductCheckoutFields($item_key, $item)
    {
        $checkout = WC()->checkout();

        echo "<div>". $this->get_name() ."</div>";
        echo '<div><sup>' . __('Please enter the domain to host here', 'wc-invoice-pdf') . '</sup></div>';
        woocommerce_form_field(
            'order_domain['. $item_key .']',
            [
            'type'              => 'text',
            'placeholder'       => 'E.g. mydomain.net',
            'custom_attributes' => ['data-ispconfig-checkdomain'=>'1']
                ],
            $checkout->get_value('order_domain')
        );
        echo '<div id="domainMessage" class="ispconfig-msg" style="display:none;"></div>';
    }

    public function OnProductCheckoutValidate($item_key, $item)
    {
        if (!class_exists('Ispconfig')) {
            return;
        }
        
        try {
            $dom = Ispconfig::$Self->validateDomain($_POST['order_domain'][$item_key]);

            $available = Ispconfig::$Self->withSoap()->IsDomainAvailable($dom);

            Ispconfig::$Self->closeSoap();

            if ($available == 0) {
                wc_add_notice(__("The domain is not available", 'wp-ispconfig3') . ' - ' . $this->get_name(), 'error');
            } elseif ($available == -1) {
                wc_add_notice(__("The domain might not be available", 'wp-ispconfig3'), 'notice');
            }
        } catch (Exception $e) {
            wc_add_notice($e->getMessage() . ' - ' . $this->get_name(), 'error');
        }
    }

    /**
     * Called when checkout is processed per product item
     * @param WC_Order_Item_Product $order_item Order Item
     * @param string $item_key Cart item key
     */
    public function OnProductCheckoutSubmit(&$order_item, $item_key)
    {
        if (!empty($_POST['order_domain'][$item_key])) {
            $order_item->add_meta_data("Domain", sanitize_text_field($_POST['order_domain'][$item_key]));
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
        $allowed_subscriptions = WCInvoicePdf::$OPTIONS['wc_order_subscriptions'];

        if (!empty($allowed_subscriptions) && $allowed_subscriptions == 'y') {
            $price = wc_price(wc_get_price_to_display($this, array( 'price' => $this->get_regular_price() * 12))) . '&nbsp;' . __('per year', 'wc-invoice-pdf');
        } else {
            $price = wc_price(wc_get_price_to_display($this, array( 'price' => $this->get_regular_price() ))) . '&nbsp;' . __('per month', 'wc-invoice-pdf');
        }
        
        return apply_filters('woocommerce_get_price_html', $price, $this);
    }
}
