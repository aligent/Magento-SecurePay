<?php

class SecurePay_Sxml_Block_Form_Cc extends Mage_Payment_Block_Form_Cc
{
	protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('sxml/form/cc.phtml');
        if (!Mage::app()->getStore()->isAdmin()) {
            $mark = Mage::getConfig()->getBlockClassName('core/template');
            $mark = new $mark;
            $mark->setTemplate('sxml/mark.phtml');
            $this->setMethodLabelAfterHtml($mark->toHtml());
        }
    }
}