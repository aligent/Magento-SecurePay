<?php

class SecurePay_Sxml_Block_Form_Cc extends Mage_Payment_Block_Form_Cc
{
	protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('sxml/form/cc.phtml');
    }
} 

?>