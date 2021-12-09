<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/* Custom defines made by users */
if (is_file(__DIR__ . '/environment.php')) {
    include_once(__DIR__ . '/environment.php');
}

class Tapfiliate extends Module
{
    const UPDATE_TYPE_UPDATE = 'order_update';
    const UPDATE_TYPE_NEW = 'order_new';
    const PROD_TAPFILIATE_BASE_URL = 'https://app.tapfiliate.com/';

    public function __construct()
    {
        $this->name = 'tapfiliate';
        $this->tab = 'front_office_features';
        $this->version = '3.0.0';
        $this->author = 'Tapfiliate';
        $this->need_instance = 0;
        $this->displayName = 'Tapfiliate';
        $this->ps_versions_compliancy = [
            'min' => '1.6',
            'max' => '1.7.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Tapfiliate');
        $this->description = $this->l('Easily create, track and grow your own affiliate marketing programs. Affiliate tracking software for E-Commerce and SaaS that integrates seamlessly with your site in just minutes. Begin affiliate marketing to reward loyal brand ambassadors and boost sales.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if ($this->id && !Configuration::get('TAPFILIATE_ID')) {
            $this->warning = $this->l('The Tapfiliate module needs to be configured Tapfiliate Account ID');
        }
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return (
            parent::install()
            && $this->registerHook('header')
            && $this->registerHook('orderConfirmation')
            && $this->registerHook('actionOrderStatusPostUpdate')
            && $this->registerHook('actionOrderSlipAdd')
            && $this->registerHook('actionDispatcher')
        );
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function getContent() {}

    public function hookActionDispatcher($args)
    {
        if (
            defined('_PS_ADMIN_DIR_')
            && isset($args['controller_class'])
            && $args['controller_class'] == 'AdminModulesController'
            && Tools::getValue('configure') === $this->name
        ) {
            $context = Context::getContext();
            $address_obj = Context::getContext()->shop->getAddress();
            $country_obj = new Country($address_obj->id_country);
            $state_obj = new State($address_obj->id_state);
            $api_was_enabled = filter_var(Configuration::get('PS_WEBSERVICE'), FILTER_VALIDATE_BOOLEAN);

            if (!$api_was_enabled) {
                Configuration::updateValue('PS_WEBSERVICE', 1);
            }

            if ($key_id = Configuration::get('TAP_WEBSERVICE_KEY_ID')) {
                $apiAccess = new WebserviceKey($key_id);
                $api_key = $apiAccess->key;
            } else {
                $apiAccess = new WebserviceKey();
                $api_key = substr(hash('sha256', uniqid('', true)), 0, 32);
                $apiAccess->key = $api_key;
                $apiAccess->save();

                $permissions = [
                    'customers' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'HEAD' => 1],
                    'orders' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'HEAD' => 1],
                    'configurations' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'HEAD' => 1],
                    'products' => ['GET' => 1, 'POST' => 1, 'PUT' => 1, 'HEAD' => 1],
                ];

                WebserviceKey::setPermissionForAccount($apiAccess->id, $permissions);

                Configuration::set('TAP_WEBSERVICE_KEY_ID', $apiAccess->id);
            }

            $payload = [
                'address1' => $address_obj->address1,
                'address2' => $address_obj->address2,
                'city' => $address_obj->city,
                'postcode' => $address_obj->postcode,
                'state' => $state_obj->name,
                'country' => $country_obj->iso_code,
                'vat_number' => $address_obj->vat_number,
                'company' => $address_obj->company,
                'domain' => $context->shop->domain,
                'email' => $context->employee->email,
                'firstname' => $context->employee->firstname,
                'lastname' => $context->employee->lastname,
                'currency' => $context->currency->iso_code,
                'api_key' => $api_key,
                'shop_id' => $context->shop->id,
                'shop_group_id' => $context->shop->id_shop_group,
                'login_key' => Configuration::get('TAPFILIATE_LOGIN_KEY')
            ];

            $client = new GuzzleHttp\Client();
            // @TODO remove
            $client->setDefaultOption('verify', false);

            try {
                $response = $client
                    ->post(
                        $this->getTapfiliateBaseURL() . '/integrations/prestashop/auth/check/',
                        ['body' => $payload],
                    )
                    ->getBody()
                    ->getContents();

                Tools::redirect($response);
            } catch(GuzzleHttp\Exception\BadResponseException $e) {
                Logger::addLog("[TAPFILIATE] could not send webhook with message: {$e->getMessage()}");
            }
        }

        return 0;
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

        $coupons = $this->getCoupons($order);

		if ($order) {
			$amount = isset($order->total_paid_tax_excl) ? $order->total_paid_tax_excl : 0;
			$shipping = isset($order->total_shipping_tax_excl) ? $order->total_shipping_tax_excl : 0;
			$conversion_amount = $amount - $shipping;

			$tapfiliate_id = Configuration::get('TAPFILIATE_ID');

            $customer_email = $order->getCustomer()->email;
            $customer_id = filter_var($customer_email, FILTER_VALIDATE_EMAIL) ? $customer_email : null;

            // Send via API
            $this->sendOrderUpdate(
                self::UPDATE_TYPE_NEW,
                $order
            );

			$this->context->smarty->assign([
                'external_id' => $order->id,
			    'conversion_amount' => $conversion_amount,
			    'tapfiliate_id' => $tapfiliate_id,
			    'customer_id' => $customer_id,
                'order_currency' => $this->getCurrency($order),
			    'is_order' => true,
                'coupons' => $coupons,
            ]);

			return $this->display(__FILE__, 'views/templates/hook/snippet.tpl');
		}
	}

    public function hookActionOrderSlipAdd($params)
    {
        $this->sendOrderUpdate(
            self::UPDATE_TYPE_UPDATE,
            $params['order'],
        );
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        $order = new Order($params['id_order']);

        $this->sendOrderUpdate(
            self::UPDATE_TYPE_UPDATE,
            $order,
        );
    }

    private function sendOrderUpdate($update_type, Order $order)
    {
        $amount = $this->getOrderAmount($order);

        // Customer details
        $customer = $order->getCustomer();
        $customer_email = $customer->email;
        $customer_firstname = $customer->firstname;
        $customer_lastname = $customer->lastname;
        $customer_id = filter_var($customer_email, FILTER_VALIDATE_EMAIL) ? $customer_email : null;

        $payload = json_encode([
            'type' => $update_type,
            'payload' => [
                'external_id' => (string)$order->id,
                'amount' => number_format($amount, 2),
                'customer_id' => $customer_id,
                'customer_email' => $customer_id,
                'customer_firstname' => $customer_firstname,
                'customer_lastname' => $customer_lastname,
                'status' => $order->getCurrentOrderState()->name,
                'options' => [
                    'currency' => $this->getCurrency($order),
                    'coupons' => $this->getCoupons($order),
                ],
            ]
        ], JSON_THROW_ON_ERROR);

        if (null === $webhook_secret = Configuration::get('TAPFILIATE_WEBHOOK_SECRET')) {
            Logger::addLog("TAPFILIATE_WEBHOOK_SECRET is missing");

            return;
        }

        $signature = base64_encode(hash_hmac('sha256', $payload, $webhook_secret, true));

        $context = Context::getContext();
        $shop = $context->shop;

        $client = new GuzzleHttp\Client();
        // @TODO remove
        $client->setDefaultOption('verify', false);

        try {
            $client->post($this->getTapfiliateBaseURL() . '/integrations/prestashop/webhooks/receive/', [
                'body' => $payload,
                'headers' => [
                    'X-Webhook-Signature' => $signature,
                    'X-Prestashop-Domain' => $shop->domain
                ]
            ]);
        } catch(GuzzleHttp\Exception\BadResponseException $e) {
            Logger::addLog("[TAPFILIATE] could not send webhook with message: {$e->getMessage()}");
        }
    }

    private function getOrderAmount(Order $order)
    {
        // Get base amount
        $total_paid = isset($order->total_paid_tax_excl) ? $order->total_paid_tax_excl : 0;
        $shipping = isset($order->total_shipping_tax_excl) ? $order->total_shipping_tax_excl : 0;
        $amount = $total_paid - $shipping;

        // Subtract refunds
        foreach ($order->getOrderSlipsCollection() as $slip) {
            $amount -= $slip->total_products_tax_excl;
        }

        return $amount;
    }

    private function getCoupons(Order $order)
    {
        $rules = $order->getCartRules() ?: [];

        return array_map(function($rule) { return (new CartRule($rule['id_cart_rule']))->code; }, $rules);
    }

    private function getCurrency(Order $order)
    {
        $currency = new CurrencyCore($order->id_currency);

        return $currency->iso_code;
    }

    private function getTapfiliateBaseURL(): string
    {
        return defined('ENV_TAPFILIATE_BASE_URL') ? ENV_TAPFILIATE_BASE_URL : self::PROD_TAPFILIATE_BASE_URL;
    }
}
