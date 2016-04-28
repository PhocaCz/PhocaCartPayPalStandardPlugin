<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');
jimport( 'joomla.plugin.plugin' );
jimport( 'joomla.filesystem.file');
jimport( 'joomla.html.parameter' );
//jimport('joomla.log.log');
//JLog::addLogger( array('text_file' => 'com_phocacart_error_log.php'), JLog::ALL, array('com_phocacart'));
phocacartimport('phocacart.utils.log');

class plgPCPPaypal_Standard extends JPlugin
{
	function plgPCPPaypal_Standard(& $subject, $config) {
	
		parent :: __construct($subject, $config);
		$this->loadLanguage();
	}
	
	function PCPbeforeProceedToPayment(&$proceed) {
		$proceed = 1;
		return true;
	}
	
	function PCPbeforeSetPaymentForm(&$form, $paramsC, $params, $order) {

		$document			= JFactory::getDocument();
		$sandbox 			= $params->get('sandbox', 0);
		$merchant_email		= $params->get('merchant_email', '');
		$address_override	= $params->get('address_override', '');
		
		
		$invoice_prefix		= $paramsC->get( 'invoice_prefix', '');
		$invoiceNr			= PhocaCartOrder::getInvoiceNumber($order['common']->id, $invoice_prefix);
		$orderNr			= PhocaCartOrder::getOrderNumber($order['common']->id);
		$itemName			= JText::_('COM_PHOCACART_ORDER') . ': ' . $orderNr;
		
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
		
		$return 		= JURI::root(false). 'index.php?option=com_phocacart&view=response&task=response.paymentrecieve';
		$cancel_return 	= JURI::root(false). 'index.php?option=com_phocacart&view=response&task=response.paymentcancel';
		$notify_url 	= JURI::root(false). 'index.php?option=com_phocacart&view=response&task=response.paymentnotify&type=paypal_standard&pid='.(int)$paymentId.'&tmpl=component';
		//$payment_action = '';

		$f		= array();
		$f[]	= '<form action="'.$actionLink.'" name="phCartPayment" id="phCartPayment" method="post">';
		$f[]	= '<input type="hidden" name="cmd" value="_cart" />';
		$f[]	= '<input type="hidden" name="upload" value="1" />';
		$f[]	= '<input type="hidden" name="business" value="'.$merchant_email.'" />';
		
		$f[]	= '<input type="hidden" name="item_name" value="'.$itemName.'" />';
		
		$i = 1;
		foreach ($order['products'] as $k => $v) {
			$f[]	= '<input type="hidden" name="item_name_'.$i.'" value="'.$v->title.'" />';
			$f[]	= '<input type="hidden" name="item_number_'.$i.'" value="'.$v->sku.'" />';
			$f[]	= '<input type="hidden" name="amount_'.$i.'" value="'.$v->brutto * $r.'" />';
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
		
		
		$discount_amount	= 0;
		$discount_title		= '';
		foreach ($order['total'] as $k => $v) {
			if ($v->type == 'sbrutto' || $v->type == 'pbrutto') {
				$f[]	= '<input type="hidden" name="item_name_'.$i.'" value="'.$v->title.'" />';
				$f[]	= '<input type="hidden" name="amount_'.$i.'" value="'. $v->amount * $r.'" />';
				$f[]	= '<input type="hidden" name="quantity_'.$i.'" value="1" />';
				
				$i++;
			}
			
			if ($v->type == 'cbrutto') {
				$discount_amount = $discount_amount + abs($v->amount);
				if ($discount_title != '') {
					$discount_title = $discount_title . ', '. $v->title;
				} else {
					$discount_title = $v->title;
				}
			}
		}
		
		if ($discount_amount > 0) {
			$f[]	= '<input type="hidden" name="discount_amount_cart" value="'.$discount_amount * $r.'" />';
		}
		
		
		
		$f[]	= '<input type="hidden" name="currency_code" value="'.$order['common']->currency_code.'" />';
		
		$b = $order['bas']['b'];
		
		
		$f[]	= '<input type="hidden" name="first_name" value="'.$b['name_first'].'" />';
		$f[]	= '<input type="hidden" name="last_name" value="'.$b['name_last'].'" />';
		$f[]	= '<input type="hidden" name="address1" value="'.$b['address_1'].'" />';
		$f[]	= '<input type="hidden" name="address2" value="'.$b['address_2'].'" />';
		$f[]	= '<input type="hidden" name="city" value="'.$b['city'].'" />';
		$f[]	= '<input type="hidden" name="zip" value="'.$b['zip'].'" />';
		$f[]	= '<input type="hidden" name="country" value="'.$b['countrytitle'].'" />';
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
		$f[]	= '<div>'.JText::_('COM_PHOCACART_ORDER_SUCCESSFULLY_PROCESSED').'</div>';
		$f[]	= '<div>'.JText::_('PLG_PCP_PAYPAL_STANDARD_YOU_ARE_NOW_BEING_REDIRECTED_TO_PAYPAL').'</div>';
		
		$f[]	= '<div class="ph-loader"></div>';
		
		$f[]	= '<div>'.JText::_('PLG_PCP_PAYPAL_STANDARD_IF_YOU_ARE_NOT_REDIRECTED_WITHIN_A_FEW_SECONDS_PLEASE').' ';
		$f[]	= '<input type="submit" class="btn btn-primary" value="'.JText::_('PLG_PCP_PAYPAL_CLICK_HERE_TO_BE_REDIRECTED_TO_PAYPAL').'" class="button" />';
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
		echo $form2;*/
		PhocaCartLog::add(1, 'Payment - PayPal Standard - SENDING FORM TO PAYPAL', (int)$order['common']->id, $form);
		return true;
	
	}
	
	function PCPbeforeCheckPayment($pid) {
			
		if (! class_exists('PhocaCartPaypalStandardIpnListener')) {
			require_once( JPATH_SITE.'/plugins/pcp/paypal_standard/helpers/ipnlistener.php');
		}
		
		$app		= JFactory::getApplication();
		$verified 	= false;
		$listener 	= new PhocaCartPaypalStandardIpnListener();
		
		$paymentTemp		= new PhocaCartPayment();
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
		} catch (Exception $e) {
			PhocaCartLog::add(1, 'Payment - PayPal Standard - ERROR', 0, $e->getMessage());
			exit(0);
		}
	
		if ($verified) {
		
			$id 			= $app->input->post->get('custom', 0, 'int');
			$paymentStatus	= $app->input->post->get('payment_status', '', 'string');
			
			if ((int)$id > 0 && $paymentStatus != '') {
				$order 			= new PhocaCartOrderView();
				$payment		= new PhocaCartPayment();
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
							//$val 		= print_r($_POST,true);
							
							if ($error == 1) {
								$msg = 'Order Id: '. $id . " \n" 
								. 'Txn Id: '.$txnId. " \n"
								. 'Message: '.$errorMsg. " \n"
								//. 'POST: '.$val. " \n"
								. 'Report: '.$listener->getTextReport();
								PhocaCartLog::add(1, 'Payment - PayPal Standard - ERROR', (int)$id, $msg);
								exit(0);
							} else {
								
								// Change the status
								$notify 	= PhocaCartOrderStatus::changeStatus((int)$id, (int)$statusOption);
								PhocaCartOrderStatus::changeStatusInOrderTable((int)$id, (int)$statusOption);
								$comment	= JText::_('COM_PHOCACART_ORDER_STATUS_CHANGED_BY_PAYMENT_SERVICE_PROVIDER') . '(Paypal Standard)';
								$comment .= "\n" . JText::_('COM_PHOCACART_INFORMATION');
								$comment .= "\n". JText::_('COM_PHOCACART_PAYMENT_ID'). ': '. $txnId;
								$comment .= "\n". JText::_('COM_PHOCACART_PAYMENT_AMOUNT'). ': '. $mcGross;
								$comment .= "\n". JText::_('COM_PHOCACART_PAYMENT_FEE'). ': '. $mcFee;
								$comment .= "\n". JText::_('COM_PHOCACART_PAYMENT_STATUS'). ': '. $paymentStatus;
								// Add status history
								PhocaCartOrderStatus::setHistory((int)$id, (int)$statusOption, (int)$notify, $comment);
								
								// Add log
								$msg = 'Order Id: '. $id . " \n" 
								. 'Txn Id: '.$txnId. " \n"
								. 'Message: Payment successfully made'. " \n"
								//. 'POST: '.$val. " \n"
								. 'Report: '.$listener->getTextReport();
								PhocaCartLog::add(1, 'Payment - PayPal Standard - SUCCESS', (int)$id, $msg);
								exit(0);
								
							}
						}
					}
				}
			
			} else {
				PhocaCartLog::add(1, 'Payment - PayPal Standard - ERROR', 0, 'No order id or payment status found in payment response');
				exit(0);
			}
  
		} else {
			PhocaCartLog::add(1, 'Payment - PayPal Standard - ERROR', 0, $listener->getTextReport());
			exit(0);
		}
		
		exit(0);
	}
}
?>