<?php

if (!class_exists('WC_Product')) {
    return;
}

add_filter('woocommerce_product_data_tabs', ['WC_Product_Service','hour_product_data_tab']);

add_action('woocommerce_product_data_panels', ['WC_Product_Service','product_data_fields']);
add_action('woocommerce_process_product_meta_service', ['WC_Product_Service', 'metadata_save']);

add_action('admin_footer', ['WC_Product_Service', 'jsRegister']);
add_filter('product_type_selector', ['WC_Product_Service','register']);

class WC_Product_Service extends WC_Product
{
    public static $current;

    public function __construct($product)
    {
        $this->product_type = 'service';
        parent::__construct($product);
    }

    public function get_type()
    {
        return 'service';
    }

    public static function register($types)
    {
        // Key should be exactly the same as in the class product_type parameter
        $types[ 'service' ] = __('Services', 'wc-invoice-pdf');
        return $types;
    }

    public static function jsRegister()
    {
        global $product_object;
        ?>
        <script type='text/javascript'>
            jQuery( document ).ready( function() {
                jQuery( '.options_group.pricing' ).addClass( 'show_if_service' ).show();
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
        $product_data_tabs['linked_product']['class'][] = 'hide_if_service';
        $product_data_tabs['attribute']['class'][] = 'hide_if_service';
        $product_data_tabs['advanced']['class'][] = 'hide_if_service';
        $product_data_tabs['shipping']['class'][] = 'hide_if_service';

        $product_data_tabs['hour_tab'] = array(
            'label' => __('Unit information', 'wc-invoice-pdf'),
            'target' => 'service_data_tab',
            'class' => 'show_if_service'
        );

        return $product_data_tabs;
    }


    public static function product_data_fields()
    {
        echo '<div id="service_data_tab" class="panel woocommerce_options_panel">';
        woocommerce_wp_text_input([
            'id' => '_qty_suffix',
            'label' => __('Unit', 'wc-invoice-pdf'),
            'description' => __("The unit next to the quantity (default: h)", 'wc-invoice-pdf'),
            'style' => 'width: 100px'
        ]);
        woocommerce_wp_text_input([
            'id' => '_qty_suffix_plural',
            'label' => __('Unit (plural)', 'wc-invoice-pdf'),
            'description' => __("The unit when quantity is more than one (optional)", 'wc-invoice-pdf'),
            'style' => 'width: 100px'
        ]);
        echo '</div>';
    }

    /**
     * BACKEND: Used to save the template ID for later use (Cart/Order)
     */
    public static function metadata_save($post_id)
    {
        $suffix = sanitize_title($_POST['_qty_suffix']);
        $suffix_plural = sanitize_title($_POST['_qty_suffix_plural']);

        if (!empty($suffix)) {
            update_post_meta($post_id, '_qty_suffix', $suffix);
        } else {
            delete_post_meta($post_id, '_qty_suffix');
        }

        if (!empty($suffix_plural)) {
            update_post_meta($post_id, '_qty_suffix_plural', $suffix_plural);
        } else {
            delete_post_meta($post_id, '_qty_suffix_plural');
        }
    }

    public function get_price_suffix($price = '', $qty = 1)
    {
        $suffix = $this->get_meta('_qty_suffix', true);
        if (empty($suffix)) {
            $suffix = 'h';
        }
        $suffix_plural = $this->get_meta('_qty_suffix_plural', true);
        if (empty($suffix_plural)) {
            $suffix_plural = $suffix;
        }
        
        return ' ' . ($qty > 1 ? $suffix_plural : $suffix);
    }

    public function get_price_html($price = '')
    {
        $price = wc_price(wc_get_price_to_display($this, array( 'price' => $this->get_regular_price() ))) . ' ' . __('per', 'wc-invoice-pdf') . $this->get_price_suffix('', 1);
        return apply_filters('woocommerce_get_price_html', $price, $this);
    }
}
