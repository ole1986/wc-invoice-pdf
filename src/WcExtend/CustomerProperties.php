<?php
namespace WcRecurring\WcExtend;

use WcRecurring\WcRecurringIndex;

class CustomerProperties
{
    const CUSTOMER_REFERENCE_KEY = "customer_reference";
    const ACCEPT_GDPR_KEY = "wc_recurring_accept_gdpr";

    public function __construct()
    {
        if (!is_admin()) {
            add_action('woocommerce_edit_account_form', [$this, 'show_customer_reference']);
            add_action('woocommerce_save_account_details', [$this, 'save_customer_reference']);
            // provide GDPR in login page
            if (!empty(WcRecurringIndex::$OPTIONS['wc_customer_login_gdpr'])) {
                add_action('woocommerce_login_form', [$this, 'show_gdpr']);
                add_action('wp_login', [$this, 'customer_login'], 10, 2);
            }
        } else {
            add_action('show_user_profile', [$this, 'edit_user_customer_reference'], 20);
            add_action('edit_user_profile', [$this, 'edit_user_customer_reference'], 20);
            add_action('profile_update', [$this, 'update_profile']);
            add_filter('woocommerce_admin_billing_fields', [$this, 'admin_billing_fields']);
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
        $customer_reference = get_user_meta($user->ID, self::CUSTOMER_REFERENCE_KEY, true);
        $gdpr = get_user_meta($user->ID, self::ACCEPT_GDPR_KEY, true);
        $dateFormat = new \IntlDateFormatter(get_locale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM);

        ?>

        <h3><?php _e('Additional customer info', 'wc-invoice-pdf'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="customer_reference"><?php _e('Customer reference ID (BT-10)', 'wc-invoice-pdf'); ?></label></th>
                <td>
                    <input type="text" name="<?php echo self::CUSTOMER_REFERENCE_KEY ?>" id="customer_reference" class="regular-text" value="<?php echo esc_attr($customer_reference); ?>" />
                </td>
            </tr>
            <tr>
                <th><label for="gdpr"><?php _e('GDPR accepted', 'wc-invoice-pdf'); ?></label></th>
                <td>
                    <span><?php echo (!empty($gdpr) ? $dateFormat->format($gdpr) : '?') ; ?></span>
                </td>
            </tr>
        </table>
        <?php
    }

    public function admin_billing_fields($fields)
    {
        // meta key: _billing_email2
        $fields['email2'] = array(
            'label' => __('Additional Email', 'woocommerce'),
        );
        return $fields;
    }

    public function update_profile($user_id)
    {
        update_user_meta($user_id, self::CUSTOMER_REFERENCE_KEY, sanitize_key($_POST[self::CUSTOMER_REFERENCE_KEY]));
    }

    public function show_gdpr()
    {
        $link = get_the_privacy_policy_link(__('I hereby accept the ', 'wc-invoice-pdf'));
        ?>
        <p>
            <input type="checkbox" id="gdpr" required name="<?php echo self::ACCEPT_GDPR_KEY ?>"> <label for="gdpr"><?php echo $link ?></label>.
        </p>
        <?php
    }

    public function customer_login($user_login, $user)
    {
        if (!empty($_POST[self::ACCEPT_GDPR_KEY])) {
            update_user_meta($user->ID, self::ACCEPT_GDPR_KEY, time());
        }
    }
}
