<?php
class Programfive_Paymentgateway_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'paymentgateway';
	protected $_formBlockType = 'paymentgateway/form_pay';
	protected $_infoBlockType = 'paymentgateway/info_pay';
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = false;
	
	public function getOrderPlaceRedirectUrl() {
		return Mage::getUrl('paymentgateway/payment/redirect', array('_secure' => true));
	}
	
	public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
		$info = $this->getInfoInstance();
		$info->setNetgiroPaymentType($data->getNetgiroPaymentType());		
        return $this;
    }

}
?>