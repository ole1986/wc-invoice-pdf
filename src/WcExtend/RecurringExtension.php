<?php

namespace WcRecurring\WcExtend;

use WcRecurring\WcRecurringIndex;

class RecurringExtension
{
    public function __construct()
    {
        // Choice and update subscription
        add_action('woocommerce_before_cart', [$this, 'BeforeCart']);
        add_action('woocommerce_cart_contents', [$this, 'SubscribeContent'], 20, 0);
        add_action('woocommerce_update_cart_action_cart_updated', [$this, 'SubscribePost']);
    }

    public function BeforeCart()
    {
        if (WC()->cart->is_empty()) {
            return;
        }

        $this->setCartQtyFromSubscription();
    }

    /**
     * CART: Subscribe content
     */
    public function SubscribeContent()
    {
        if (WC()->cart->is_empty()) {
            return;
        }

        $items = WC()->cart->get_cart();

        $relatedProducts = array_filter($items, function ($item) {
            return ($item['data'] instanceof \WC_Product_Webspace);
        });

        if (empty($relatedProducts)) {
            return;
        }

        if (count($relatedProducts) < count($items)) {
            // notice to customer that all non-subscibable products will be removed from cart
            wc_print_notice(__("All non-subscribed products will be removed with the next step", 'wc-recurring-pdf'), 'notice');
        }
        
        if (!empty(WcRecurringIndex::$OPTIONS['wc_order_subscriptions'])) {
            // skip the below as the subscription has been fixed
            return;
        }

        $period = WC()->session->get('wc-recurring-subscription', 'm');

        ?>
        <script>
        function updateCartFromSubscriptionChange() {
            jQuery("*[name='update_cart']").prop('disabled', false).trigger('click');
        }
        </script>
        <tr>
            <td colspan="6" style="text-align: right">
                <label><?php _e('Payment interval', 'wc-invoice-pdf') ?></label>
                <select name="wc-recurring-subscription" onchange="updateCartFromSubscriptionChange()">
                <?php foreach (WcRecurringIndex::$SUBSCRIPTIONS as $key => $value) {
                    $selected = $period == $key ? 'selected' : '';
                    echo "<option value='$key' $selected>$value</option>";
                }
                ?>
                </select>
            </td>
        </tr>
        <?php
    }

    public function SubscribePost($cart_updated)
    {
        if (isset($_POST['wc-recurring-subscription'])) {
            WC()->session->set('wc-recurring-subscription', $_POST['wc-recurring-subscription']);
            $cart_updated = true;
        }

        return $cart_updated;
    }

    /**
     * internal set the Woocommerce cart to its given subscription
     * @param $subscription can either be 'y' (year) or 'm' (month) or empty
     */
    private function setCartQtyFromSubscription()
    {
        $subscriptionSetting = WcRecurringIndex::$OPTIONS['wc_order_subscriptions'];

        if (empty($subscriptionSetting)) {
            // fetch from session when admin setting is not given
            $subscriptionSetting = WC()->session->get('wc-recurring-subscription');
        }

        if (empty($subscriptionSetting)) {
            return;
        }

        $items = WC()->cart->get_cart();

        $relatedProducts = array_filter($items, function ($item) {
            return ($item['data'] instanceof \WC_Product_Webspace);
        });

        $qty = $subscriptionSetting == 'y' ? 12 : 1;

        foreach ($relatedProducts as $key => $value) {
            WC()->cart->set_quantity($key, $qty);
        }

        WC()->session->set('wc-recurring-subscription', $subscriptionSetting);
    }
}
