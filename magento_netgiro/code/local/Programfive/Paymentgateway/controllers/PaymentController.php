<?php

class Programfive_Paymentgateway_PaymentController extends Mage_Core_Controller_Front_Action {
	
	public function redirectAction() {
		$this->loadLayout();
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','paymentgateway',array('template' => 'paymentgateway/redirect.phtml'));
		$this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
	}
	
public function responseAction() {  
   
        $validated = false;       
        $orderId =  $this->getRequest()->getParam('orderid'); 
        $signatureFromRespone = $this->getRequest()->getParam('signature'); 
    
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($orderId);

        $grandTotal = Mage::helper('paymentgateway/data')->FormatAmount($order->getBaseGrandTotal());
        $magentoSignature = Mage::helper('paymentgateway/data')->GetSignatureOrderIdSecretKey($orderId);

        $validated = $magentoSignature == $signatureFromRespone;
        if($validated) {		
			// save this fields if needed
			$confirmationCode = $this->getRequest()->getParam('confirmationCode'); 
			$invoiceNumber = $this->getRequest()->getParam('invoiceNumber'); 
			$name = $this->getRequest()->getParam('name'); 
			$email = $this->getRequest()->getParam('email'); 
			$address = $this->getRequest()->getParam('address'); 
			$address2 = $this->getRequest()->getParam('address2'); 
			$city = $this->getRequest()->getParam('city'); 
			$country = $this->getRequest()->getParam('country'); 
			$zip = $this->getRequest()->getParam('zip'); 
						
            // Payment was successful, so update the order's state, send order email and move to the success page
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Netgiro gateway has authorized the payment.');
            $order->sendNewOrderEmail();
            $order->setEmailSent(true);
            $order->save();

            Mage::getSingleton('checkout/session')->unsQuoteId();
            Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure'=>true));
        }
        else {           
            // There is a problem in the response we got
           	$order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Error in validating signature')->save();
            Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure'=>true));
        }
}

	public function cancelAction() {	
        if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());			
            if($order->getId()) {
				// Flag the order as 'cancelled' and save it
				$order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
				$this->_redirect('checkout/cart');
			}
        }
	}
}