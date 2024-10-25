<?php
namespace WcRecurring\WcExtend;

class CustomerProperties
{
    const CUSTOMER_REFERENCE_KEY = "customer_reference";

    public function __construct()
    {
        if (!is_admin()) {
            add_action('woocommerce_edit_account_form', [$this, 'show_customer_reference']);
            add_action('woocommerce_save_account_details', [$this, 'save_customer_reference']);
        } else {
            add_action('show_user_profile', [$this, 'edit_user_customer_reference'], 20);
            add_action('edit_user_profile', [$this, 'edit_user_customer_reference'], 20);
            add_action('profile_update', [$this, 'update_customer_reference']);
        }
    }

    public function show_customer_reference()
    {
        $user = wp_get_current_user();
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="dob"><?php _e('Customer reference ID (BT-10)', 'wc-invoice-pdf'); ?></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"  name="<?php echo self::CUSTOMER_REFERENCE_KEY ?>" value="<?php echo esc_attr($user->customer_reference); ?>" />
    </p>
        <?php
    }

    public function save_customer_reference($user_id)
    {
        if (isset($_POST[self::CUSTOMER_REFERENCE_KEY])) {
            $refno = sanitize_text_field($_POST[self::CUSTOMER_REFERENCE_KEY]);

            if (!preg_match("/\d{3,12}-\d{10,30}-\d{2}/", $refno)) {
                wc_add_notice("Invalid customer reference", 'error');
                return;
            }

            update_user_meta($user_id, self::CUSTOMER_REFERENCE_KEY, $refno);
        }
    }

    public function edit_user_customer_reference($user)
    {
        $saved_country = get_user_meta($user->ID, self::CUSTOMER_REFERENCE_KEY, true); ?>

        <h3><?php _e('Additional customer info', 'wc-invoice-pdf'); ?></h3>
        <table class="form-table">
            <tr>
            <th><label for="country"><?php _e('Customer reference ID (BT-10)', 'wc-invoice-pdf'); ?></label></th>
            <td>
                <input type="text" name="<?php echo self::CUSTOMER_REFERENCE_KEY ?>" id="country" class="regular-text" value="<?php echo esc_attr($saved_country); ?>" />
            </td>
            </tr>
        </table>
        <?php
    }

    public function update_customer_reference($user_id)
    {
        update_user_meta($user_id, self::CUSTOMER_REFERENCE_KEY, sanitize_key($_POST[self::CUSTOMER_REFERENCE_KEY]));
    }
}
