<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;

jimport( 'joomla.plugin.plugin' );
jimport( 'joomla.filesystem.file');
jimport( 'joomla.html.parameter' );
//jimport('joomla.log.log');
//JLog::addLogger( array('text_file' => 'com_phocacart_error_log.php'), JLog::ALL, array('com_phocacart'));
//phocacartimport('phocacart.utils.log');

JLoader::registerPrefix('Phocacart', JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/phocacart');

class plgPCPPaypal_Standard extends CMSPlugin
{
	protected $name 	= 'paypal_standard';

	function __construct(& $subject, $config) {

		parent :: __construct($subject, $config);
		$this->loadLanguage();
	}

	/**
	 * Proceed to payment - some method do not have proceed to payment gateway like e.g. cash on delivery
	 *
	 * @param   integer	$proceed  Proceed or not proceed to payment gateway
	 * @param   string	$message  Custom message array set by plugin to override standard messages made by component
	 *
	 * @return  boolean  True
	 */

	function onPCPbeforeProceedToPayment(&$proceed, &$message, $eventData) {

		if (!isset($eventData['pluginname']) || isset($eventData['pluginname']) && $eventData['pluginname'] != $this->name) {
			return false;
		}

		// THERE ARE 3 PLACES WHERE THE MESSAGE CAN BE CREATED:
		// 1) COMPONENT - components/com_phocacart/views/info/tmpl/ ...
		// 2) LANGUAGE FILE - there is specific string in language file which can be customized for each e-shop (see top of ini file)
		// 3) PAYMENT PLUGIN - means that payment plugin can override the component (1) and language file (2) message
		// See examples:

		$proceed = 1;
		$message = array();
		/*
		// Order processed successfully made - no downloadable items
		$message['order_nodownload'] 	= Text::_('COM_PHOCACART_ORDER_SUCCESSFULLY_PROCESSED')
		.'</br>' . Text::_('COM_PHOCACART_ORDER_PROCESSED_ADDITIONAL_INFO');
		// Order processed successfully made - downloadable items
		$message['order_download'] 		= Text::_('COM_PHOCACART_ORDER_SUCCESSFULLY_PROCESSED')
		.'</br>' . Text::_('COM_PHOCACART_ORDER_PROCESSED_DOWNLOADABLE_ITEMS_ADDITIONAL_INFO');
		// Order and payment successfully made - no downloadable items
		$message['payment_nodownload'] 	= Text::_('COM_PHOCACART_ORDER_AND_PAYMENT_SUCCESSFULLY_PROCESSED')
		.'</br>' . Text::_('COM_PHOCACART_ORDER_PAYMENT_PROCESSED_ADDITIONAL_INFO');
		// Order and payment successfully made - downloadable items
		$message['payment_download']	= Text::_('COM_PHOCACART_ORDER_AND_PAYMENT_SUCCESSFULLY_PROCESSED')
		.'</br>' . Text::_('COM_PHOCACART_ORDER_PAYMENT_PROCESSED_DOWNLOADABLE_ITEMS_ADDITIONAL_INFO');
		*/

		return true;
	}

	/**
	 * Payment Canceled
	 *
	 * @param   integer	$mid  ID of message - can be set in PCPbeforeSetPaymentForm
	 * @param   string	$message  Custom message array set by plugin to override standard messages made by component
	 *
	 * @return  boolean  True
	 */

	function onPCPafterCancelPayment($mid, &$message, $eventData){

		if (!isset($eventData['pluginname']) || isset($eventData['pluginname']) && $eventData['pluginname'] != $this->name) {
			return false;
		}

		$message = array();
		/*
		switch($mid) {
			case 1:
				$message['payment_canceled']	= Text::_('COM_PHOCACART_PAYMENT_CANCELED')
				.'</br>' . Text::_('COM_PHOCACART_ORDER_PAYMENT_CANCELED_ADDITIONAL_INFO');
			break;
			default:
				$message['payment_canceled']	= Text::_('COM_PHOCACART_PAYMENT_CANCELED')
				.'</br>' . Text::_('COM_PHOCACART_ORDER_PAYMENT_CANCELED_ADDITIONAL_INFO');
			break;
		}
		*/


		return true;
	}

	function onPCPbeforeSetPaymentForm(&$form, $paramsC, $params, $order, $eventData) {

		if (!isset($eventData['pluginname']) || isset($eventData['pluginname']) && $eventData['pluginname'] != $this->name) {
			return false;
		}

		$document				= Factory::getDocument();
		$sandbox 				= $params->get('sandbox', 0);
		$merchant_email			= $params->get('merchant_email', '');
		$address_override		= $params->get('address_override', '');
		$country_type			= $params->get('country_type', 1);

		$paramsC 				= PhocacartUtils::getComponentParameters();
		$rounding_calculation	= $paramsC->get( 'rounding_calculation', 1 );

		// Since 5.0.0 - there can be discount (product discount, cart discount, coupon) from brutto amount (discount from amount which includes tax, see below)
		$tax_calculation_sales	 			= $paramsC->get( 'tax_calculation_sales', 1);
		$tax_calculation_sales_change_subtotal = $paramsC->get( 'tax_calculation_sales_change_subtotal', 0);

		$price					= new PhocacartPrice();


		// !IMPORTANT =================================================================================
		// Items price * Quantities = Subotal
		// - Discounts (Product, Cart, Reward Points) and Coupons
		// + Shipping Costs
		// + Payment Costs
		// -+ Currency Rounding (in PayPal the plus is new item, the minus is discount)
		// -+ Total Amount Rounding (in PayPal the plus is new item, the minus is discount)
		// ============================================================================================


	/*	$orderView 			= new PhocacartOrderView();
		$productDiscounts 	= $orderView->getItemProductDiscounts($order['common']->id);
		$products 			= $orderView->getItemProducts($order['common']->id);

		print r($order);
		print r($products);
		print r($productDiscounts);
		// $order['products'] = $products ($order['products'] is the same link $products)
		*/


		//$invoice_prefix			= $paramsC->get( 'invoice_prefix', '');
		//$invoice_number_format	= $paramsC->get( 'invoice_number_format', '');
		//$invoice_number_chars	= $paramsC->get( 'invoice_number_chars', 12);
		$invoiceNr				= PhocacartOrder::getInvoiceNumber($order['common']->id, $order['common']->date, $order['common']->invoice_number);
		$orderNr				= PhocacartOrder::getOrderNumber($order['common']->id, $order['common']->date, $order['common']->order_number);
		$itemName				= Text::_('COM_PHOCACART_ORDER') . ': ' . $orderNr;

		$actionLink = 'https://www.paypal.com/cgi-bin/webscr';
		if ($sandbox == 1) {
			$actionLink = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
		}

		// Other currency in order - r = rate
		$r = 1;
		if (isset($order['common']->currency_exchange_rate)) {
			$r = $order['common']->currency_exchange_rate;
		}

		if (isset($order['common']->payment_id) && (int)$order['common']->payment_id > 0) {
			$paymentId = (int)$order['common']->payment_id;
		} else {
			$paymentId = 0;
		}

		$return 		= Uri::root(false). 'index.php?option=com_phocacart&view=response&task=response.paymentrecieve&type=paypal_standard&mid=1';
		$cancel_return 	= Uri::root(false). 'index.php?option=com_phocacart&view=response&task=response.paymentcancel&type=paypal_standard&mid=1';
		$notify_url 	= Uri::root(false). 'index.php?option=com_phocacart&view=response&task=response.paymentnotify&type=paypal_standard&pid='.(int)$paymentId.'&tmpl=component';
		//$payment_action = '';

		$f		= array();
		$f[]	= '<form action="'.$actionLink.'" name="phCartPayment" id="phCartPayment" method="post">';
		$f[]	= '<input type="hidden" name="cmd" value="_cart" />';
		$f[]	= '<input type="hidden" name="upload" value="1" />';
		$f[]	= '<input type="hidden" name="business" value="'.$merchant_email.'" />';

		$f[]	= '<input type="hidden" name="item_name" value="'.$itemName.'" />';


		$i = 1;

		// There can be difference between cart total amount and payment total amount (because of currency and its rounding)
		// cart total amount (brutto) 	= (item * quantity) * currency rate
		// payment total amount			= (item * currency rate) * quantity
		$cartBrutto 		= 0;// Total amount (brutto) calculated by cart
		$paymentBrutto		= 0;// Total amount (brutto) calculated by payment method
		$discountAmount		= 0;// Sum of all discount values - all MINUS values
		$currencyAmount		= 0;// Sum of all currency rounding amounts - all PLUS values


		foreach ($order['products'] as $k => $v) {

			$paymentBrutto = $paymentBrutto + (($price->roundPrice($v->netto * $r)) * (int)$v->quantity);

			$f[]	= '<input type="hidden" name="item_name_'.$i.'" value="'.$v->title.'" />';
			$f[]	= '<input type="hidden" name="item_number_'.$i.'" value="'.$v->sku.'" />';

			// Since 5.0.0 - there can be discount (product discount, cart discount, coupon) from brutto amount
			if ($tax_calculation_sales == 2) {
				$f[]	= '<input type="hidden" name="amount_'.$i.'" value="'.$price->roundPrice($v->brutto * $r).'" />';

				// POSSIBLE CUSTOMIZATION *** a) don't send tax, b) send tax
				// a) we subtract the discount from brutto - so we don't set any tax to paypal, e.g. 120 - 80 (discount) = 40
				// b) or it can be customized - so we send the tax but because of paypal calculation, we need to subtract this tax from brutto
				/* UNCOMMENT
				$bruttoPrice = $price->roundPrice($v->brutto * $r);
				$lastDiscountTax = 0;
				if (!empty($order['productdiscounts'][$v->product_id_key])) {
					foreach($order['productdiscounts'][$v->product_id_key] as $k3 => $v3) {
						$lastDiscountTax = $price->roundPrice($v3->tax * $r);

					}
				}
				$f[]	= '<input type="hidden" name="amount_'.$i.'" value="'.$bruttoPrice - $lastDiscountTax.'" />';
				*/

			} else {
				$f[]	= '<input type="hidden" name="amount_'.$i.'" value="'.$price->roundPrice($v->netto * $r).'" />';
			}

			$f[]	= '<input type="hidden" name="quantity_'.$i.'" value="'.$v->quantity.'" />';
			$f[]	= '<input type="hidden" name="weight_'.$i.'" value="'.$v->weight.'" />';

			if (isset($v->attributes)) {
				$j = 0;
				foreach ($v->attributes as $k2 => $v2) {
					$f[]	= '<input type="hidden" name="on'.$j.'_'.$i.'" value="'.$v2->attribute_title.'" />';
					$f[]	= '<input type="hidden" name="os'.$j.'_'.$i.'" value="'.$v2->option_title.'" />';
					$j++;
				}
			}
			$i++;
		}

		// More tax rates
		// If there are different taxes, we need to change tax variable for PayPal
		$countTax = 0;
		foreach ($order['total'] as $k => $v) {

			if ($v->type == 'tax') {
				$countTax++;
				continue;
			} else {
				continue;
			}
		}


		$tI = 1;// More tax rates
		$taxSum = 0;// in case PayPal does not recognize more tax rates
		foreach ($order['total'] as $k => $v) {
			if ($v->amount != 0 || $v->amount_currency != 0) {

				switch($v->type) {

					// All discounts (MINUS)

					// Since 5.0.0 - there can be discount (product discount, cart discount, coupon) from brutto amount
					case 'dnetto':
					case 'dbrutto':

						if ($tax_calculation_sales == 2) {
							if ($v->type == 'dbrutto'){
								//$paymentBrutto 		+= $price->roundPrice($v->amount * $r);

								$discountAmount 	+= $price->roundPrice(abs($v->amount * $r));

							}
							if ($v->type == 'dnetto'){
								$paymentBrutto 		+= $price->roundPrice($v->amount * $r);
								//$discountAmount 	+= $price->roundPrice(abs($v->amount * $r));
							}

						} else {
							if ($v->type == 'dnetto'){
								$paymentBrutto 		+= $price->roundPrice($v->amount * $r);
								$discountAmount 	+= $price->roundPrice(abs($v->amount * $r));
							}
						}
					break;

					// Old 4.x code
					/*
					case 'dnetto':
						$paymentBrutto 		+= $price->roundPrice($v->amount * $r);
						$discountAmount 	+= $price->roundPrice(abs($v->amount * $r));
					break;
					*/


					// Tax (PLUS)
					case 'tax':
						$paymentBrutto 		+= $price->roundPrice($v->amount * $r);

						// Since 5.0.0 - there can be discount (product discount, cart discount, coupon) from brutto amount
						if ($tax_calculation_sales == 2) {
							// Don't send tax to PayPal in case we subtract discounts from brutto - from amount with tax
							// PayPal calculation is different: items + tax which cannot be used when we get discounts from brutto
							//
							// Can be customized - in $order['product_discounts] - see line cca 210 $price->roundPrice($v->brutto * $r)
							// POSSIBLE CUSTOMIZATION *** a) don't send tax, b) send tax

							/* UNCOMMENT
								$taxSum = $taxSum + $price->roundPrice($v->amount * $r);
								//$f['tax'] = '<input type="hidden" name="tax_cart" value="' . $price->roundPrice($v->amount * $r) . '" />';
								$f['tax'] = '<input type="hidden" name="tax_cart" value="' . $taxSum . '" />';
							*/
						} else {
							// PayPal does not count discount_amount_cart in case of more taxes, so we send only tax amount
							if ($countTax > 1 && $discountAmount == 0) {
								$f[] = '<input type="hidden" name="tax_' . $tI . '" value="' . $price->roundPrice($v->amount * $r) . '" />';
								$tI++;
							} else {
								$taxSum = $taxSum + $price->roundPrice($v->amount * $r);
								//$f[] = '<input type="hidden" name="tax_cart" value="' . $price->roundPrice($v->amount * $r) . '" />';
								$f['tax'] = '<input type="hidden" name="tax_cart" value="' . $taxSum . '" />';
							}
						}
					break;

					// Payment Method, Shipping Method (PLUS)
					case 'sbrutto':
					case 'pbrutto':
						$paymentBrutto 		+= $price->roundPrice($v->amount * $r);

						$f[]	= '<input type="hidden" name="item_name_'.$i.'" value="'.$v->title.'" />';
						$f[]	= '<input type="hidden" name="amount_'.$i.'" value="'. $price->roundPrice($v->amount * $r).'" />';
						$f[]	= '<input type="hidden" name="quantity_'.$i.'" value="1" />';
						$i++;

					break;

					// Rounding (PLUS/MINUS)
					case 'rounding':
						if ($v->amount_currency != 0) {
							// Rounding is set in order currency
							if ($v->amount_currency > 0) {
								$currencyAmount		+= round($v->amount_currency, 2, $rounding_calculation);
								$paymentBrutto 		+= round($v->amount_currency, 2, $rounding_calculation);
							} else if ($v->amount_currency < 0) {
								$discountAmount 	+= round(abs($v->amount_currency), 2, $rounding_calculation);
								$paymentBrutto 		+= round($v->amount_currency, 2, $rounding_calculation);
							}
						} else {
							// Rounding is set in default currency
							if ($v->amount > 0 && round(($v->amount * $r), 2, $rounding_calculation) > 0) {

								$f[]	= '<input type="hidden" name="item_name_'.$i.'" value="'.$v->title.'" />';
								$f[]	= '<input type="hidden" name="amount_'.$i.'" value="'. round(($v->amount * $r), 2, $rounding_calculation).'" />';
								$f[]	= '<input type="hidden" name="quantity_'.$i.'" value="1" />';
								$paymentBrutto 		+= round(($v->amount * $r), 2, $rounding_calculation);
							} else if ($v->amount < 0) {
								$discountAmount 	+= round(abs($v->amount * $r), 2, $rounding_calculation);
								$paymentBrutto 		+= round(($v->amount * $r), 2, $rounding_calculation);
							}
						}
					break;

					// Brutto (total amount)
					case 'brutto':
						if ($v->amount_currency != 0) {
							// Brutto is set in order currency
							$cartBrutto = $price->roundPrice($v->amount_currency);
						} else {
							// Brutto is set in default currency
							$cartBrutto = $price->roundPrice($v->amount * $r);
						}
					break;
				}
			}
		}



		// Comparte cart brutto and payment brutto and correct payment total amount by cart total amount
		if ($cartBrutto > $paymentBrutto) {

			// in PayPal - if currency rounding plus then make new item
			$currencyAmount		+= ($cartBrutto - $paymentBrutto);

		} else if ($cartBrutto < $paymentBrutto) {

			// in PayPal - if currency rounding minus then make it as a part of discount
			$discountAmount 	+= ($paymentBrutto - $cartBrutto);
		}

		$discountAmount = $price->roundPrice($discountAmount);

		if (round($discountAmount, 2, $rounding_calculation) > 0) {
			// Ignored by PayPal if tax for items is used
			$f[]	= '<input type="hidden" name="discount_amount_cart" value="'.round($discountAmount, 2, $rounding_calculation).'" />';
		}

		if (round($currencyAmount, 2, $rounding_calculation) > 0) {
			$f[]	= '<input type="hidden" name="item_name_'.$i.'" value="'.Text::_('COM_PHOCACART_CURRENCY_ROUNDING').'" />';
			$f[]	= '<input type="hidden" name="amount_'.$i.'" value="'. round($currencyAmount, 2, $rounding_calculation).'" />';
			$f[]	= '<input type="hidden" name="quantity_'.$i.'" value="1" />';
			$i++;

		}




		$f[]	= '<input type="hidden" name="currency_code" value="'.$order['common']->currency_code.'" />';

		$b = $order['bas']['b'];


		$f[]	= '<input type="hidden" name="first_name" value="'.$b['name_first'].'" />';
		$f[]	= '<input type="hidden" name="last_name" value="'.$b['name_last'].'" />';
		$f[]	= '<input type="hidden" name="address1" value="'.$b['address_1'].'" />';
		$f[]	= '<input type="hidden" name="address2" value="'.$b['address_2'].'" />';
		$f[]	= '<input type="hidden" name="city" value="'.$b['city'].'" />';
		$f[]	= '<input type="hidden" name="zip" value="'.$b['zip'].'" />';


		if ($country_type == 2) {
			$f[]	= '<input type="hidden" name="country" value="'.$b['countrycode'].'" />';
		} else {
			$f[]	= '<input type="hidden" name="country" value="'.$b['countrytitle'].'" />';
		}

		$f[]	= '<input type="hidden" name="email" value="'.$b['email'].'" />';//$b->email_contact

		$f[]	= '<input type="hidden" name="address_override" value="'.(int)$address_override.'" />';
		$f[]	= '<input type="hidden" name="invoice" value="'.$invoiceNr.'" />';
		$f[]	= '<input type="hidden" name="charset" value="UTF-8" />';

		//$f[]	= '<input type="hidden" name="lc" value="" />';
		$f[]	= '<input type="hidden" name="rm" value="2" />';
		$f[]	= '<input type="hidden" name="no_note" value="1" />';
		$f[]	= '<input type="hidden" name="bn" value="PhocaCart_Cart_PPS" />';
		$f[]	= '<input type="hidden" name="custom" value="'.(int)$order['common']->id.'" />';



		$f[]	= '<input type="hidden" name="return" value="'.$return.'" />';
		$f[]	= '<input type="hidden" name="notify_url" value="'.$notify_url.'" />';
		$f[]	= '<input type="hidden" name="cancel_return" value="'.$cancel_return.'" />';
		//$f[]	= '<input type="hidden" name="paymentaction" value="'.$payment_action.'" />';// sale

		$f[]	= '<div class="ph-center">';
		$f[]	= '<div>'.Text::_('COM_PHOCACART_ORDER_SUCCESSFULLY_PROCESSED').'</div>';
		$f[]	= '<div>'.Text::_('PLG_PCP_PAYPAL_STANDARD_YOU_ARE_NOW_BEING_REDIRECTED_TO_PAYPAL').'</div>';

		$f[]	= '<div class="ph-loader"></div>';

		$f[]	= '<div>'.Text::_('PLG_PCP_PAYPAL_STANDARD_IF_YOU_ARE_NOT_REDIRECTED_WITHIN_A_FEW_SECONDS_PLEASE').' ';
		$f[]	= '<input type="submit" class="btn btn-primary" value="'.Text::_('PLG_PCP_PAYPAL_CLICK_HERE_TO_BE_REDIRECTED_TO_PAYPAL').'" class="button" />';
		$f[]	= '</div>';
		$f[]	= '</div>';

		$f[]	= '</form>';


		$form	= implode("\n", $f);

		$js		= 'window.onload=function(){' . "\n"
				 .'   window.setTimeout(document.phCartPayment.submit.bind(document.phCartPayment), 1100);'. "\n"
				 .'};'. "\n";

		$document->addScriptDeclaration($js);

		/*$form2 = str_replace('<', '&lt;', $form);
		$form2 = str_replace('>', '&gt;', $form2);
		$form2 = '<pre><code>'.$form2.'</code></pre>';
		echo $form2;
		exit;*/
		PhocacartLog::add(1, 'Payment - PayPal Standard - SENDING FORM TO PAYPAL', (int)$order['common']->id, $form);
		return true;

	}

	function onPCPbeforeCheckPayment($pid, $eventData) {

		if (!isset($eventData['pluginname']) || isset($eventData['pluginname']) && $eventData['pluginname'] != $this->name) {
			return false;
		}


		if (! class_exists('PhocacartPaypalStandardIpnListener')) {
			require_once( JPATH_SITE.'/plugins/pcp/paypal_standard/helpers/ipnlistener.php');
		}

		$app		= Factory::getApplication();
		$verified 	= false;
		$listener 	= new PhocacartPaypalStandardIpnListener();

		$paymentTemp		= new PhocacartPayment();
		$paymentOTemp 		= $paymentTemp->getPaymentMethod((int)$pid );
		$paramsPaymentTemp	= $paymentOTemp->params;
		$p['sandbox']		= $paramsPaymentTemp->get('sandbox', 0);
		$p['verify_ssl'] 	= $paramsPaymentTemp->get('verify_ssl', 1);


		if ($p['sandbox'] == 1) {
			$listener->use_sandbox	= true;
		} else {
			$listener->use_sandbox	= false;
		}
		$listener->force_ssl_v3 = false;
		$listener->use_ssl 		= true;

		try {

			$listener->setParams($p);
			$listener->requirePostMethod();
			$verified = $listener->processIpn();

			if (!$verified) {
				PhocacartLog::add(1, 'Payment - PayPal Standard - ERROR', 0, $listener->getTextReport());
			}

		} catch (Exception $e) {
			PhocacartLog::add(1, 'Payment - PayPal Standard - ERROR (Listener Error)', 0, $e->getMessage());
			exit(0);
		}



		if ($verified) {

			$id 			= $app->input->post->get('custom', 0, 'int');
			$paymentStatus	= $app->input->post->get('payment_status', '', 'string');


			if ((int)$id > 0 && $paymentStatus != '') {
				$order 			= new PhocacartOrderView();
				$payment		= new PhocacartPayment();
				$o				= array();
				$o['common']	= $order->getItemCommon($id);
				$o['total'] 	= $order->getItemTotal($id);



				// Order - check if the harder has assigned the payment method
				if (isset($o['common']->payment_id) && (int)$o['common']->payment_id > 0) {
					$paymentO = $payment->getPaymentMethod((int)$o['common']->payment_id );
					// Order - check if the payment method set in Order is the same like this plugin
					if (isset($paymentO->method)) {
						$paramsPayment	= $paymentO->params;
						$statusOption 	= $paramsPayment->get('status_'.$paymentStatus, 0);
						// Status - check if returned status from paypal is assigned to payment method order statuses
						// (see Payment Method Options in Payment - Paypal statuses are assigned to our order statuses )
						// We don't check for "Completed" here, we just set the Order status of eshop by Paypal status
						// So if status option is higher than zero, it is assigned to some

						if($statusOption > 0) {
							// OK - we got status from PayPal which is assigned in our eshop to order status
							// Now - check all possible parameters of PayPal status
							$error = 0;
							$errorMsg = '';


							// Merchant Email
							$receiverEmail	= $app->input->post->get('receiver_email', '', 'string');
							$merchantEmail 	= $paramsPayment->get('merchant_email', '');
							if ($receiverEmail != $merchantEmail) {
								$errorMsg 	.= 'Merchant email does not match' . " \n";
								$error 		= 1;
							}

							// Total Amount
							$mcGross		= $app->input->post->get('mc_gross', '', 'float');
							$totalA 		= array_reverse($o['total']);
							$totalBrutto	= 0;
							foreach($totalA as $k => $v) {
								if ($v->type == 'brutto') {
									$totalBrutto = $v->amount;
									break;
								}

							}
							if ($totalBrutto == 0) {
								$errorMsg 	.= 'Total amount not found in order' . " \n";
								$error 		= 1;
							}

							// Attention Refunded and Reversed have negative amount
							$mcGrossCompare = $mcGross;
							if ($paymentStatus == 'Refunded' || $paymentStatus == 'Reversed') {
								$mcGrossCompare = abs($mcGross);
							}

							// Other currency in order - r = rate
							$r = 1;
							if (isset($o['common']->currency_exchange_rate)) {
								$r = $o['common']->currency_exchange_rate;
							}
							$totalBrutto *= $r;

							if ($totalBrutto != $mcGrossCompare) {
								$errorMsg 	.= 'Total amount does not match' . " \n"
											. 'Total amount in eshop: '.$totalBrutto. " \n"
											. 'Total amount on PayPal: '.$mcGross. " \n";
								$error 		= 1;
							}

							// Currency
							$mcCurrency		= $app->input->post->get('mc_currency', '', 'string');

							if (strtoupper($o['common']->currency_code) != strtoupper($mcCurrency)) {
								$errorMsg 	.= 'Currency does not match' . " \n"
											. 'Currency in eshop: '.$o['common']->currency_code. " \n"
											. 'Currency on PayPal: '.$mcCurrency. " \n";
								$error 		= 1;

							}

							$txnId		= $app->input->post->get('txn_id', '', 'string');
							$mcFee		= $app->input->post->get('mc_fee', '', 'string');


							if ($error == 1) {
								$msg = 'Order Id: '. $id . " \n"
								. 'Txn Id: '.$txnId. " \n"
								. 'Message: '.$errorMsg. " \n"
								//. 'POST: '.$val. " \n"
								. 'Report: '.$listener->getTextReport();
								PhocacartLog::add(1, 'Payment - PayPal Standard - ERROR', (int)$id, $msg);
								exit(0);
							} else {

								// 1) First change the status in database
								//    and create invoice in case invoice is created by status change
								if (PhocacartOrderStatus::changeStatusInOrderTable((int)$id, (int)$statusOption)) {
									PhocacartLog::add(1, 'Payment - PayPal Standard - Order Status Change', (int)$id, 'Order status changed to: '.$statusOption . '('.$paymentStatus.')');
								}

								// 2) Change the status including sending emails
								//    This must be second step so it can include even newly created invoice
								//    (Changing status is diveded into two functions because mostly the status id in database is changed when saving items
								//     models: phocacartorder, phocacarteditstatus, ... So changeStatus method does not change the id in database)
								$notify = false;
								try {
									$notify 	= PhocacartOrderStatus::changeStatus((int)$id, (int)$statusOption, $o['common']->order_token);
								} catch (RuntimeException $e) {
									PhocacartLog::add(1, 'Payment - PayPal Standard - ERROR', (int)$id, $e->getMessage());
								}



								$comment	= Text::_('COM_PHOCACART_ORDER_STATUS_CHANGED_BY_PAYMENT_SERVICE_PROVIDER') . '(Paypal Standard)';

								$comment .= "\n" . Text::_('COM_PHOCACART_INFORMATION');
								$comment .= "\n". Text::_('COM_PHOCACART_PAYMENT_ID'). ': '. $txnId;
								$comment .= "\n". Text::_('COM_PHOCACART_PAYMENT_AMOUNT'). ': '. $mcGross;
								$comment .= "\n". Text::_('COM_PHOCACART_PAYMENT_FEE'). ': '. $mcFee;
								$comment .= "\n". Text::_('COM_PHOCACART_PAYMENT_STATUS'). ': '. $paymentStatus;
								// Add status history
								PhocacartOrderStatus::setHistory((int)$id, (int)$statusOption, (int)$notify, $comment);

								// Add log
								$msg = 'Order Id: '. $id . " \n"
								. 'Txn Id: '.$txnId. " \n"
								. 'Message: Payment successfully made'. " \n"
								//. 'POST: '.$val. " \n"
								. 'Report: '.$listener->getTextReport();
								PhocacartLog::add(1, 'Payment - PayPal Standard - SUCCESS', (int)$id, $msg);
								exit(0);

							}
						}
					}
				}

			} else {
				PhocacartLog::add(1, 'Payment - PayPal Standard - ERROR', 0, 'No order id or payment status found in payment response');
				exit(0);
			}

		} else {
			PhocacartLog::add(1, 'Payment - PayPal Standard - ERROR', 0, $listener->getTextReport());
			exit(0);
		}

		exit(0);
	}

	/* The payment method plugin can decide whether or not to empty the cart when an order is placed.
	 * For example, if the payment gateway returns information about a failed payment,
	 * the cart can remain filled and the customer can try to make the payment again.
	 * However, if the payment method plugin decides not to delete the items in the cart,
	 * then it must use other events to ensure that the cart is deleted. For example, on a successful payment.
	 *
	 * To empty cart:
	 *
	 *  $cart = new PhocacartCart();
	 *	$cart->emptyCart();
     *  PhocacartUserGuestuser::cancelGuestUser();
	 *
	 * For example in following events:
	 * - PCPafterRecievePayment
	 * - PCPafterCancelPayment
	 * - PCPbeforeCheckPayment
	 * - PCPonPaymentWebhook
	 *
	 * If the cart is not emptied and the user re-orders,
	 * then a new order ID is created - which is generally standard procedure
	 */

	function onPCPbeforeEmptyCartAfterOrder(&$form, &$pluginData, $paramsC, $params, $order, $eventData) {

		if (!isset($eventData['pluginname']) || isset($eventData['pluginname']) && $eventData['pluginname'] != $this->name) {
			return false;
		}

		// Uncomment to not empty cart when order is placed
		// $pluginData['emptycart'] = false;

		return true;

	}

	/**
	 * Payment Recieve
	 *
	 * @param   integer	$mid  ID of message - can be set in PCPbeforeSetPaymentForm
	 * @param   string	$message  Custom message array set by plugin to override standard messages made by component
	 *
	 * @return  boolean  True
	 */

	function onPCPafterRecievePayment($mid, &$message, $eventData){

		if (!isset($eventData['pluginname']) || isset($eventData['pluginname']) && $eventData['pluginname'] != $this->name) {
			return false;
		}

		// Uncomment to empty cart when PCPafterRecievePayment is reached
		// $cart = new PhocacartCart();
	 	// $cart->emptyCart();
        // PhocacartUserGuestuser::cancelGuestUser();

		$message = array();

		return true;

	}
/*
	public function onPCPbeforeSaveOrderAdmin($context, $table, $isNew, &$data) {

		if ($context == 'com_phocacart.order.status') {
			// Before save in edit status view (admin)
			// (change comment in order history table)
			if ($data['comment_history'] != '') {
				$data['comment_history'] .= ' ';
			}
			$data['comment_history'] .= 'Comment in order history table changed by payment plugin';
			return true;


		} else if ($context == 'com_phocacart.order') {
			// Before save in order edit view (admin)
		} else {
			return true;
		}
	}
*/
	/*
	function onPCPbeforeShowPossiblePaymentMethod(&$active, $params, $eventData){

		if (!isset($eventData['pluginname']) || isset($eventData['pluginname']) && $eventData['pluginname'] != $this->name) {
			return false;
		}

		// Payment plugin can disable/deactivate current payment method in possible payment method list based on own rules
		// $active = false;

		return true;

	}

	function onPCPonInfoViewDisplayContent($data, $eventData){

		if (!isset($eventData['pluginname']) || isset($eventData['pluginname']) && $eventData['pluginname'] != $this->name) {
			return false;
		}

		$output = array();
		$output['content'] = '';

		return $output;

	}

	/*
	 * Payment plugin wants to display some information on Item View (Detail View) page
	 * */
	/*
	public function onPCPonItemBeforeEndPricePanel($context, &$item, &$params) {
		//return "<div></div>";
	}
	*/
}
?>
