<?php
class Programfive_Paymentgateway_Helper_Data extends Mage_Core_Helper_Abstract
{

 public function FormatAmount($amount)
 {
 //Base currency is used for all online payment transactions. Scope is defined by the catalog price scope ("Catalog" > "Price" > "Catalog Price Scope").
$baseCurrency =  Mage::app()->getStore()->getCurrentCurrencyCode();
	if($baseCurrency == "ISK")
	{
		return round($amount);
	}
	else
	{
		$result = str_replace(',', '', $amount);
		return number_format((float)$result, 2, '', '');		
	}
 }
 
 public function GetSignature($orderId,$total)
 {
	$appID = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/paymentgateway/applicationid'));
	$secretKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/paymentgateway/secretkey'));

	return hash('sha256', $secretKey.$orderId.$total.$appID);
 }
 
 public function GetSignatureOrderIdSecretKey($orderId)
 {
	$secretKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/paymentgateway/secretkey'));
	return hash('sha256', $secretKey.$orderId);
 }
}