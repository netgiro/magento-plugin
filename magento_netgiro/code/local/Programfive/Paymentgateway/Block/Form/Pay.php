<?php
class Programfive_Paymentgateway_Block_Form_Pay extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('paymentgateway/Form/pay.phtml');
    }
}
