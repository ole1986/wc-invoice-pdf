<?php

if (!class_exists('WC_Product')) {
    return;
}

add_filter('woocommerce_product_data_tabs', ['WC_Product_Hour','hour_product_data_tab']);

add_action('woocommerce_product_data_panels', ['WC_Product_Hour','product_data_fields']);
add_action('woocommerce_process_product_meta_hour', ['WC_Product_Hour', 'metadata_save']);

add_action('admin_footer', ['WC_Product_Hour', 'jsRegister']);
add_filter('product_type_selector', ['WC_Product_Hour','register']);

class WC_Product_Hour extends WC_Product
{
    public static $current;

    public function __construct($product)
    {
        $this->product_type = 'hour';
        parent::__construct($product);
    }

    public static function register($types)
    {
        // Key should be exactly the same as in the class product_type parameter
        $types[ 'hour' ] = __('Working hours', 'wc-invoice-pdf');
        return $types;
    }

    public static function jsRegister()
    {
        global $product_object;
        ?>
        <script type='text/javascript'>
            jQuery( document ).ready( function() {
                jQuery( '.options_group.pricing' ).addClass( 'show_if_hour' ).show();
                <?php if ($product_object instanceof self) : ?>
                jQuery('.general_options').show();
                jQuery('.general_options > a').trigger('click');
                <?php endif; ?>
            });
        </script>
        <?php
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

    public static function hour_product_data_tab($product_data_tabs)
    {
        $product_data_tabs['linked_product']['class'][] = 'hide_if_hour';
        $product_data_tabs['attribute']['class'][] = 'hide_if_hour';
        $product_data_tabs['advanced']['class'][] = 'hide_if_hour';
        $product_data_tabs['shipping']['class'][] = 'hide_if_hour';

        $product_data_tabs['hour_tab'] = array(
            'label' => __('Working hours', 'wc-invoice-pdf'),
            'target' => 'hour_data_tab',
            'class' => 'show_if_hour'
        );

        return $product_data_tabs;
    }


    public static function product_data_fields()
    {
        echo '<div id="hour_data_tab" class="panel woocommerce_options_panel">';
        woocommerce_wp_checkbox(['id' => '_hour_useminute', 'label' => __('minutes', 'wc-invoice-pdf'), 'description' => __("To the minute calculation", 'wc-invoice-pdf')]);
        echo '</div>';
    }

    /**
     * BACKEND: Used to save the template ID for later use (Cart/Order)
     */
    public static function metadata_save($post_id)
    {
        update_post_meta($post_id, '_hour_useminute', $_POST['_hour_useminute']);
    }

    public function get_price_suffix($price = '', $qty = 1, $shorten = false)
    {
        $plural = $qty > 1 ? 's' : '';

        $suffix = __('Hour' . $plural, 'wc-invoice-pdf');

        if ($shorten) {
            $suffix = 'h';
        }

        
        if ($this->get_meta('_hour_useminute', true)) {
            $suffix = __('minute' . $plural, 'wc-invoice-pdf');
            if ($shorten) {
                $suffix = 'min';
            }
        }

        return ' ' . $suffix;
    }

    public function get_price_html($price = '')
    {
        $price = wc_price(wc_get_price_to_display($this, array( 'price' => $this->get_regular_price() ))) . ' ' . __('per', 'wc-invoice-pdf') . $this->get_price_suffix('', 1);
        return apply_filters('woocommerce_get_price_html', $price, $this);
    }
}