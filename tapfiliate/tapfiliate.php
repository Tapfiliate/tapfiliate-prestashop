<?php

if (!defined('_PS_VERSION_'))
	exit;

class Tapfiliate extends Module
{
	public function __construct()
	{
		$this->name = 'tapfiliate';
		$this->tab = 'advertising_marketing';
		$this->version = '2.0';
		$this->author = 'Tapfiliate';
		$this->displayName = 'Tapfiliate';
		$this->module_key = 'fd2aaefea84ac1bb512e6f1878d990bj';

		parent::__construct();

		if ($this->id && !Configuration::get('TAPFILIATE_ID')) {
			$this->warning = $this->l('You have not yet set your Tapfiliate Account ID');
		}

		$this->description = $this->l('Integrate the Tapfiliate tracking script in your shop');
		$this->confirmUninstall = $this->l('Are you sure you want to delete Tapfiliate from your shop?');
	}

	public function install()
	{
		return (parent::install() && $this->registerHook('displayAfterBodyOpeningTag') && $this->registerHook('orderConfirmation'));
	}

	public function getContent()
	{
		$output = '<h2>Tapfiliate</h2>';
		if (Tools::isSubmit('submitTap'))
		{
			Configuration::updateValue('TAPFILIATE_ID', Tools::getValue('tapfiliate_id'));
			$output .= '
			<div class="conf confirm">
				<img src="../img/admin/ok.gif" alt="" title="" />
				'.$this->l('Settings updated').'
			</div>';
		}

		return $output.$this->displayForm();
	}

	public function displayForm()
	{
		$output = '
		<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">
			<fieldset class="width2">
				<legend><img src="../img/admin/cog.gif" alt="" class="middle" />'.$this->l('Settings').'</legend>
				<label>'.$this->l('Your Tapfiliate customer ID').'<br><span style="opacity: 0.5; font-size: 0.8em;">This can be <a href="https://tapfiliate.com/a/integration/" target="_blank">found here</span></label>
				<div class="margin-form">
					<input type="text" name="tapfiliate_id" value="'.Tools::safeOutput(Tools::getValue('tapfiliate_id', Configuration::get('TAPFILIATE_ID'))).'" />
					<p class="clear">'.$this->l('Example:').' 1-123abc</p>
				</div>
				<center><input type="submit" name="submitTap" value="'.$this->l('Save').'" class="button" /></center>
			</fieldset>
		</form>';

		return $output;
	}

	public function hookDisplayAfterBodyOpeningTag($params)
	{
		if (!$this->context->smarty->getTemplateVars('is_order')) {
			$this->context->smarty->assign('tapfiliate_id', Configuration::get('TAPFILIATE_ID'));
			$this->context->smarty->assign('is_order', false);

			return $this->display(__FILE__, 'views/templates/snippet.tpl');
		}
	}

	public function hookOrderConfirmation($params)
	{
		// Setting parameters
		// $parameters = Configuration::getMultiple(array('PS_LANG_DEFAULT'));
		$order = isset($params['order']) ? $params['order'] : null;
		if ($order) {
			$conversion_rate = 1;

			$amount = (isset($order->total_paid_tax_excl)) ? $order->total_paid_tax_excl : 0;
			$shipping = (isset($order->total_shipping_tax_incl)) ? $order->total_shipping_tax_incl : 0;
			$conversion_amount = $amount - $shipping;

			$tapfiliate_id = Configuration::get('TAPFILIATE_ID');

			$this->context->smarty->assign('external_id', $order->id);
			$this->context->smarty->assign('conversion_amount', $conversion_amount);
			$this->context->smarty->assign('tapfiliate_id', $tapfiliate_id);
			$this->context->smarty->assign('is_order', true);

			return $this->display(__FILE__, 'views/templates/snippet.tpl');
		}
	}
}
