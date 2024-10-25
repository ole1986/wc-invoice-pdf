<?php

use WcRecurring\WcRecurringIndex;

if (!class_exists('WC_Product')) {
    return;
}

// when adding 'webspace' product to cart
add_action('woocommerce_webspace_add_to_cart', ['WC_Product_Webspace', 'add_to_cart'], 30);
add_filter('woocommerce_product_data_tabs', ['WC_Product_Webspace','product_data_tab']);

add_action('woocommerce_checkout_before_customer_details', ['WC_Product_Webspace', 'OnCheckoutFields']);
add_action('woocommerce_checkout_order_processed', ['WC_Product_Webspace', 'OnCheckoutSubmit'], 20, 3);

add_action('woocommerce_checkout_create_order_line_item_object', ['WC_Product_Webspace', 'OnCreateOrderItem'], 20, 3);

add_filter('woocommerce_cart_item_subtotal', ['WC_Product_Webspace', 'ItemSubtotal'], 20, 3);
// use the quantity field to printout "Subscription" instead of the amount
add_filter('woocommerce_cart_item_quantity', ['WC_Product_Webspace', 'ItemQuantity'], 10, 3);


add_action('admin_footer', ['WC_Product_Webspace', 'jsRegister']);
add_filter('product_type_selector', ['WC_Product_Webspace','register']);

class WC_Product_Webspace extends \WC_Product implements WC_Product_RecurringInterface
{
    public $product_type = "webspace";

    public function __construct($product = 0)
    {
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

    public static function add_to_cart()
    {
        wc_get_template('single-product/add-to-cart/simple.php');
    }

    public static function product_data_tab($product_data_tabs)
    {
        $product_data_tabs['linked_product']['class'][] = 'hide_if_webspace';
        $product_data_tabs['inventory']['class'][] = 'show_if_webspace';
        $product_data_tabs['attribute']['class'][] = 'hide_if_webspace';
        $product_data_tabs['advanced']['class'][] = 'hide_if_webspace';
        $product_data_tabs['shipping']['class'][] = 'hide_if_webspace';

        return $product_data_tabs;
    }

    public static function ItemSubtotal($value, $item, $cart_item_key)
    {
        if (!$item['data'] instanceof self) {
            echo $value;
            return;
        }

        $period = WC()->session->get('wc-recurring-subscription', 'm');
        $periodName = WcRecurringIndex::$SUBSCRIPTIONS[$period];

        echo $value;
        echo "<br />" . $periodName;
    }

    public static function ItemQuantity($item_qty, $item_key, $item)
    {
        if (!$item['data'] instanceof self) {
            echo $item_qty;
            return;
        }
        
        _e('Subscription', 'wc-invoice-pdf');
    }

    /**
     * CHECKOUT: Add an additional field for the domain name being entered by customer (incl. validation check)
     */
    public static function OnCheckoutFields()
    {
        if (WC()->cart->is_empty()) {
            return 0;
        }
        $items =  WC()->cart->get_cart();

        $otherProducts = array_filter($items, function ($item) {
            return !$item['data'] instanceof self;
        });

        $items = array_diff_key($items, $otherProducts);

        if (count($items) > 0) {
            array_map(function ($item) {
                WC()->cart->remove_cart_item($item['key']);
                return;
            }, $otherProducts);

            foreach ($items as $current) {
                $current['data']->OnProductCheckoutFields($current['key'], $current);
            }
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

        /**
     * CHECKOUT: Save the domain field entered by the customer
     */
    public static function OnCheckoutSubmit($order_id, $post_data, $order)
    {
        if (WC()->cart->is_empty()) {
            return 0;
        }

        $items = WC()->cart->get_cart();

        $relatedProducts = array_filter($items, function ($item) {
            return $item['data'] instanceof self;
        });

        if (empty($relatedProducts)) {
            return;
        }

        $period = WC()->session->get('wc-recurring-subscription', 'm');

        do_action('wc_recurring_order_period', $order_id, $period);
    }

    public static function OnCreateOrderItem($order_item, $item_key, $item)
    {
        if (!$item['data'] instanceof self) {
            return $order_item;
        }

        if (!empty($_POST['order_domain'][$item_key])) {
            $order_item->add_meta_data("Domain", sanitize_text_field($_POST['order_domain'][$item_key]));
        }
        return $order_item;
    }

    public function is_purchasable()
    {
        return true;
    }

    public function get_sold_individually($context = 'view')
    {
        return false;
    }

    public function get_min_purchase_quantity()
    {
        return 12;
    }

    public function get_max_purchase_quantity()
    {
        return 12;
    }
    
    public function get_price_suffix($price = '', $qty = 1)
    {
        $plural = $qty > 1;
        if ($plural) {
            $suffix = __('months', 'wc-invoice-pdf');
        } else {
            $suffix = __('month', 'wc-invoice-pdf');
        }
        
        return ' ' . $suffix;
    }

    public function get_price_html($price = '')
    {
        $allowed_subscriptions = WcRecurringIndex::$OPTIONS['wc_order_subscriptions'];

        if (!empty($allowed_subscriptions) && $allowed_subscriptions == 'y') {
            $price = wc_price(wc_get_price_to_display($this, array( 'price' => $this->get_regular_price() * 12))) . '&nbsp;' . __('per year', 'wc-invoice-pdf');
        } else {
            $price = wc_price(wc_get_price_to_display($this, array( 'price' => $this->get_regular_price() ))) . '&nbsp;' . __('per month', 'wc-invoice-pdf');
        }
        
        return apply_filters('woocommerce_get_price_html', $price, $this);
    }

    public function add_to_cart_text()
    {
        $text = $this->is_purchasable() && $this->is_in_stock() ? __('Add to cart', 'woocommerce') : __('Read more', 'woocommerce');
        return apply_filters('woocommerce_product_add_to_cart_text', $text, $this);
    }

    public function invoice_title($item, $invoice)
    {
        $dateFormat = new IntlDateFormatter(get_locale(), IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);

        $product_name = $item['name'];
        $current = new \DateTime($invoice->created);
        $next = clone $current;
        $next->add(new \DateInterval('P' . strval($item['qty']) . 'M'));
        
        $product_name .= "\n<strong>" . __('Period', 'wc-invoice-pdf') . ": " . $dateFormat->format($current) ." - ". $dateFormat->format($next) . '</strong>';
        return $product_name;
    }

    public function invoice_qty($item, $invoice)
    {
        return number_format($item['qty'], 0, ',', ' ') . ' ' . $this->get_price_suffix('', $item['qty']);
    }
}
