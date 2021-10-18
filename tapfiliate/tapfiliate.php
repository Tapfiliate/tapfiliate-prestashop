<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Tapfiliate extends Module
{
    public function __construct()
    {
        $this->name = 'tapfiliate';
        $this->tab = 'front_office_features';
        $this->version = '3.0.0';
        $this->author = 'Tapfiliate';
        $this->need_instance = 0;
        $this->displayName = 'Tapfiliate';
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => '1.7.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Tapfiliate');
        $this->description = $this->l('Easily create, track and grow your own affiliate marketing programs. Affiliate tracking software for E-Commerce and SaaS that integrates seamlessly with your site in just minutes. Begin affiliate marketing to reward loyal brand ambassadors and boost sales.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('TAPFILIATE')) {
            $this->warning = $this->l('No name provided');
        }
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        $shop = new Shop();

        return (
            parent::install()
            && $this->registerHook('header')
            && $this->registerHook('orderConfirmation')
        );
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

	public function getContent()
	{
        $context = Context::getContext();
        $shop = $context->shop;
        $domain = $shop->domain;
        $email = $context->employee->email;
        $firstname = $context->employee->firstname;
        $lastname = $context->employee->lastname;
        $currency = $context->currency;

        $address_obj = Context::getContext()->shop->getAddress();
        $country_obj = new Country($address_obj->id_country);
        $state_obj = new State($address_obj->id_state);

        $address1 = $address_obj->address1;
        $address2 = $address_obj->address2;
        $city = $address_obj->city;
        $postcode = $address_obj->postcode;
        $state = $state_obj->state;
        $country = $country_obj->iso_code;
        $vat_number = $address_obj->vat_number;
        $company = $address_obj->company;

        $api_was_enabled = filter_var(Configuration::get('PS_WEBSERVICE'), FILTER_VALIDATE_BOOLEAN);

        if (!$api_was_enabled) {
            Configuration::updateValue('PS_WEBSERVICE', 1);
        }

        $apiAccess = new WebserviceKey();
        $api_key = 'GENERATE_A_COMPLEX_VALUE_WITH_32_CHARACTERS'; // TODO
        $apiAccess->key = $api_key;
        $apiAccess->save();

        $payload = [
            'address1' => $address1,
            'address2' => $address2,
            'city' => $city,
            'postcode' => $postcode,
            'state' => $state,
            'country' => $country,
            'vat_number' => $vat_number,
            'company' => $company,
            'domain' => $domain,
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'currency' => $currency,
            'api_key' => $api_key,
        ];

        // ?io_format=JSON

        // api/configurations

	}

    public function hookDisplayHeader($params)
    {
        $this->context->smarty->assign([
            'tapfiliate_id' => Configuration::get('TAPFILIATE_ID'),
            'is_order' => false,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/snippet.tpl');
    }

	public function hookOrderConfirmation($params)
	{
		$order = isset($params['order']) ? $params['order'] : null;

        $rules = $order->getCartRules() ?: [];
        $coupons = array_map(function($rule) { return (new CartRule($rule['id_cart_rule']))->code; }, $rules);

		if ($order) {
			$amount = isset($order->total_paid_tax_excl) ? $order->total_paid_tax_excl : 0;
			$shipping = isset($order->total_shipping_tax_excl) ? $order->total_shipping_tax_excl : 0;
			$conversion_amount = $amount - $shipping;

			$tapfiliate_id = Configuration::get('TAPFILIATE_ID');

            $customer_email = $order->getCustomer()->email;
            $customer_id = filter_var($customer_email, FILTER_VALIDATE_EMAIL) ? $customer_email : null;
            $currency = new CurrencyCore($order->id_currency);

			$this->context->smarty->assign([
                'external_id' => $order->id,
			    'conversion_amount' => $conversion_amount,
			    'tapfiliate_id' => $tapfiliate_id,
			    'customer_id' => $customer_id,
                'order_currency' => $currency->iso_code,
			    'is_order' => true,
                'coupons' => $coupons,
            ]);

			return $this->display(__FILE__, 'views/templates/hook/snippet.tpl');
		}
	}
}
