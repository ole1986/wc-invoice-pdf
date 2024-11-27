<?php

namespace WcRecurring\Model\Placeholder;

use WcRecurring\WcRecurringIndex;

class CompanyDetails
{
    public $company_name;
    public $email;
    public $address;
    public $city;
    public $postcode;
    public $country;

    public $vat_id;
    public $iban;
    public $bic;
    public $bank_name;

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance != null) {
            return self::$instance;
        }
        self::$instance = new self();
        return self::$instance;
    }

    public function __construct()
    {
        $this->loadAddressFromWoocommerce();
        $this->loadPaymentsFromWoocommerce();

        $this->company_name = WcRecurringIndex::$OPTIONS['wc_company_name'];
        $this->email = WcRecurringIndex::$OPTIONS['wc_company_email'];
        $this->vat_id = WcRecurringIndex::$OPTIONS['wc_company_vat'];
    }

    public function loadAddressFromWoocommerce()
    {
        $options = get_options([
            'woocommerce_store_address',
            'woocommerce_store_city',
            'woocommerce_store_postcode'
        ]);

        array_walk($options, function ($value, $key) {
            $mkey = str_replace('woocommerce_store_', '', $key);
            $this->$mkey = $value;
        });

        $this->country = explode(":", get_option('woocommerce_default_country'))[0];
    }

    public function loadPaymentsFromWoocommerce()
    {
        $bacs = get_option('woocommerce_bacs_accounts');
        if (empty($bacs)) {
            return;
        }

        $accountInfo = array_pop($bacs);
        $this->iban = $accountInfo['iban'];
        $this->bic = $accountInfo['bic'];
        $this->bank_name = $accountInfo['bank_name'];
    }

    public function getSingleAddress()
    {
        $address = implode(', ', [
            $this->address,
            $this->postcode,
            $this->city,
            $this->country
        ]);
        return $address;
    }
}
