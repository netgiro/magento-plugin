<?php 

class Programfive_Paymentgateway_Model_Options
{
    public function toOptionArray()
    {
        $options =  array(
            array('value'=>1, 'label'=>Mage::helper('paymentgateway')->__('14 days')),
            array('value'=>2, 'label'=>Mage::helper('paymentgateway')->__('Partial payments')),
            array('value'=>3, 'label'=>Mage::helper('paymentgateway')->__('Partial payments without interest'))                     
        );
			
		return $options;
    }

}