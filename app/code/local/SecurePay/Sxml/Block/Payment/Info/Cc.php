<?php

class SecurePay_Sxml_Block_Payment_Info_Cc extends Mage_Payment_Block_Info_Cc {

    protected function _prepareSpecificInformation($transport = null) {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }

        $transport = parent::_prepareSpecificInformation($transport);
        $data = array();
        $vAdditionalInformation = $this->getInfo()->getAdditionalInformation('fraud_markers');
        if ($vAdditionalInformation && Mage::app()->getStore()->isAdmin()) {
            $data[Mage::helper('Sxml')->__('Fraud Status')] = $vAdditionalInformation;
        }
        return $transport->setData(array_merge($transport->getData(), $data));

    }
}