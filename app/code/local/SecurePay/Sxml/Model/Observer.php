<?php

class SecurePay_Sxml_Model_Observer {

    /**
     * Listens for controller_action_postdispatch_checkout_onepage_saveOrder Event
     * Redirects the customer to a nominated CMS page if their order is rejected by SecurePay's FraudGuard.
     * Without the redirection, the customer would see a "success" page. Instead we send them to a CMS page which will contain some friendly text and a suggestion to call Customer Service
     *
     * @param Varien_Event_Observer  $oEvent
     * @return SecurePay_Sxml_Model_Observer
     */
    public function redirectFlaggedOrders(Varien_Event_Observer $oEvent){
        $vRedirectUrl = Mage::registry('fraudguard_flagged_url');
        if(!$vRedirectUrl){
            return $this;
        }

        $vJson = Mage::app()->getResponse()->getBody();
        $result = Mage::helper('core')->jsonDecode($vJson);
        $result['redirect'] = $vRedirectUrl;
        $result['success']  = true;
        $result['error']    = false; //don't want to scare the horses
        $result['error_messages'] = '';
        Mage::app()->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        return $this;
    }


}