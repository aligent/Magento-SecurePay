<?php
/**
 * SecurePay Sxml Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category	SecurePay
 * @package		Sxml
 * @author		Andrew Dubbeld (support@securepay.com.au)
 * @license		http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @notes		Partially derived from the Fontis SecurePay module, Copyright (c) 2008 Fontis Pty. Ltd. (http://www.fontis.com.au)
 */

require_once('SecurePay/securexml.php');

define('NO_ANTIFRAUD', 1);
/**
 * SecurePay_Sxml_Model_Sxml
 *
 * The bulk of the SecurePay XML API payment module. It handles Preauth/Advice, Standard, Reverse and Refund transactions in the Magento application.
 */
class SecurePay_Sxml_Model_Sxml extends Mage_Payment_Model_Method_Cc
{	
    protected $_code  = 'Sxml';

    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;
    protected $_canReviewPayment        = true;
	
    protected $_formBlockType = 'Sxml/form_cc';
	
    const STATUS_APPROVED = 'Approved';

	const PAYMENT_ACTION_AUTH_CAPTURE = 'authorize_capture';
	const PAYMENT_ACTION_AUTH = 'authorize';
	
	public function getDebug($iStoreId = 0)
	{
		return Mage::getStoreConfig('payment/Sxml/debug', $iStoreId);
	}
	
	public function isFraudGuard($iStoreId = 0)
	{
		return Mage::getStoreConfig('payment/Sxml/antifraud', $iStoreId);
	}
	
	public function getMode($forceNormal = 0, $iStoreId = 0)
	{
		$fraud = $forceNormal ? 0 : $this->isFraudGuard();
		
		if(Mage::getStoreConfig('payment/Sxml/test', $iStoreId))
		{
			return $fraud ? SECUREPAY_FRAUD_TEST : SECUREPAY_TEST;
		}
		
		return $fraud ? SECUREPAY_FRAUD_LIVE : SECUREPAY_LIVE;
	}
	
	public function getLogPath()
	{
		return Mage::getBaseDir() . '/var/log/Sxml.log';
	}
	
	public function getUsername($iStoreId = 0)
	{
		return trim(Mage::getStoreConfig('payment/Sxml/username', $iStoreId));
	}
	
	public function getPassword($iStoreId = 0)
	{
		return trim(Mage::getStoreConfig('payment/Sxml/password', $iStoreId));
	}
	
	public function getCCVStatus($iStoreId = 0)
	{
		return Mage::getStoreConfig('payment/Sxml/usecvv', $iStoreId);
	}
	
	public function getCurrency()
	{
		return $this->getInfoInstance()->getQuote()->getBaseCurrencyCode();
	}
	
	public function getCheckout()
	{
		return Mage::getSingleton('checkout/session');
	}
	
	public function getQuote()
	{
		return $this->getCheckout()->getQuote();
	}
	
	/**
	 * validate
	 *
	 * Checks form data before it is submitted to processing functions.
	 *
	 * @param Varien_Object $payment
	 * @param int/float $amount
	 *
	 * @return Mage_Payment_Model_Method_Cc $this.
	 */
	public function validate()
    {
    	if($this->getDebug())
		{
	    	$writer = new Zend_Log_Writer_Stream($this->getLogPath());
			$logger = new Zend_Log($writer);
		}
		
        //parent::validate();
        $paymentInfo = $this->getInfoInstance();
		
        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment)
		{
            $currency_code = $paymentInfo->getOrder()->getBaseCurrencyCode();
        }
		else
		{
            $currency_code = $paymentInfo->getQuote()->getBaseCurrencyCode();
        }
		
        return $this;
    }
	
	/**
	 * authorize
	 *
	 * Sends a preauth transaction to the SecurePay Gateway. Only called in "Authorize" mode (See module settings).
	 *
	 * @param Varien_Object $payment
	 * @param int/float $amount
	 *
	 * @return Mage_Payment_Model_Method_Cc $this. Failure will throw Mage::throwException(), and the except value will be displayed to the customer. (!)
	 */
	public function authorize(Varien_Object $payment, $amount)
	{
		if($this->getDebug())
		{
			$writer = new Zend_Log_Writer_Stream($this->getLogPath());
			$logger = new Zend_Log($writer);
		}
		
		$approved = true;
		$iStoreId = $payment->getOrder()->getStoreId();
		$transaction_id = $payment->getOrder()->getIncrementId();

        $bFraudguardPassed = true;
		if($this->isFraudGuard($iStoreId))
		{
			//Create the fraudguard transaction object
			$sxml = new securexml_transaction ($this->getMode(0, $iStoreId),$this->getUsername($iStoreId),$this->getPassword($iStoreId));
			//Populate fraud-check with user's details, if available
			
			$shipping_address = $this->getQuote()->getShippingAddress();
			$billing_address = $this->getQuote()->getBillingAddress();

            $vRemoteIp = Mage::helper('core/http')->getRemoteAddr();
            $sxml->initFraud($vRemoteIp, $billing_address->getFirstname(), $billing_address->getLastname(), $billing_address->getPostcode(), $billing_address->getCity(), $billing_address->getCountry(), $shipping_address->getCountry(), $billing_address->getEmail());
			
			//Issue check
			$bRequestSuccessful = $sxml->processCreditFraudCheck($amount, $transaction_id, $payment->getCcNumber(), $payment->getCcExpMonth(), $payment->getCcExpYear(), $payment->getCcCid(), Mage::app()->getStore()->getBaseCurrency()->getCurrencyCode());

            $iFraudguardResponse = $sxml->getResult('antiFraudText');
            $iFraudguardScore = $sxml->getResult('antiFraudScore');
            $bFraudguardPassed = ($iFraudguardScore < 10);

            if($this->getDebug()) {
                $logger->info("FraudGuard result: " . $iFraudguardResponse.' ('.$iFraudguardScore.')');
            }
		}
		
        //Get the preauth transaction object
        $sxml = new securexml_transaction ($this->getMode(NO_ANTIFRAUD),$this->getUsername($iStoreId),$this->getPassword($iStoreId));
        //Issue the preauth
        $approved = $sxml->processPreauth($amount, $transaction_id, $payment->getCcNumber(), $payment->getCcExpMonth(), $payment->getCcExpYear(), $payment->getCcCid(), Mage::app()->getStore()->getBaseCurrency()->getCurrencyCode());

		if($approved && $sxml->getResult('preauth_id'))
		{
			$preauth_id = $sxml->getResult('preauth_id');
			$payment->setCcTransId(''.$preauth_id);
			$payment->setTransactionId(''.$preauth_id);
			$payment->setIsTransactionClosed(false);
            if($bFraudguardPassed == false){
                $payment->setIsTransactionPending(true);
                $payment->setIsFraudDetected(true);
            }
			
            if($this->getDebug())
            {
                $logger->info("Preauth Approved. #: " . $payment->getCcTransId());
            }
		}
		else
		{
			$error = $sxml->getError();
			
			if($this->getDebug())
			{
				$logger->info("Preauth Declined. " . $error . $sxml->getResult('request'));
			}
			
			Mage::throwException("" . $error);
		}
		
		return $this;
	}
	
	/**
	 * capture
	 *
	 * Completes a preauthorised transaction in preauth (Authorize) mode OR processes a standard transaction in standard (Authorize+Capture) mode.
	 * This function can be called in two possible situations:
	 * 		1. When the payment module is set to "Authorize", the module is in Preauth/Advice mode, and payments are preauthorized ($this->authorize()) when a user
	 *			 submits an order. Later on, the store owner needs to manually capture the payment, and this function is called.
	 *		2. When the payment module is set to "Authorize & Capture", or Standard mode, credit-card/order details are passed directly to this function after
	 *			 customer form submission.
	 * This function will store a gateway response id in $payment->CcTransId to facilitate void/refunds.
	 *
	 * @param Varien_Object $payment
	 * @param int/float $amount
	 *
	 * @return Mage_Payment_Model_Method_Cc $this. Failure will throw Mage::throwException(); in Standard mode the except value is displayed to the customer (!)
	 */
	public function capture(Varien_Object $payment, $amount)
	{
		if($this->getDebug())
		{
			$writer = new Zend_Log_Writer_Stream($this->getLogPath());
			$logger = new Zend_Log($writer);
		}
		
		$iStoreId = $payment->getOrder()->getStoreId();
		$preauth = $payment->getCcTransId();
		
		$txnType = "Advice";
		
		if(!$preauth)
		{
			if($payment->getCcExpYear())
			{
				$txnType = "Standard";
			}
			else
			{
				if($this->getDebug())
				{
					$logger->info( "SecurePay_Sxml_Model_Sxml->capture(): CC details are missing in 'Preauth + Capture'. This should not happen.");
				}
				Mage::throwException("CC details missing.");
			}
		}
		
		//Create the transaction object
		$sxml = new securexml_transaction($this->getMode(NO_ANTIFRAUD), $this->getUsername($iStoreId), $this->getPassword($iStoreId));
        $approved = false;
        $bFraudguardPassed = true;

        $transaction_id = $payment->getOrder()->getIncrementId();

        if($txnType == "Advice")
		{
			// Issue an advice transaction (captures a pre-existing auth)
			$approved = $sxml->processAdvice($amount, $transaction_id, $preauth);
		}
		else
		{
			if($this->isFraudGuard($iStoreId))
			{
				// Issue a fraudguard transaction
				$sxml = new securexml_transaction ($this->getMode(),$this->getUsername($iStoreId),$this->getPassword($iStoreId));
				$shipping_address = $this->getQuote()->getShippingAddress();
				$billing_address = $this->getQuote()->getBillingAddress();

                $vRemoteIp = Mage::helper('core/http')->getRemoteAddr();
                $sxml->initFraud($vRemoteIp, $billing_address->getFirstname(), $billing_address->getLastname(), $billing_address->getPostcode(), $billing_address->getCity(), $billing_address->getCountry(), $shipping_address->getCountry(), $billing_address->getEmail());

                // request a fraudguard check
                $bRequestSuccessful = $sxml->processCreditFraudCheck($amount, $transaction_id, $payment->getCcNumber(), $payment->getCcExpMonth(), $payment->getCcExpYear(), $payment->getCcCid(), Mage::app()->getStore()->getBaseCurrency()->getCurrencyCode());

                $iFraudguardResponse = $sxml->getResult('antiFraudText');
                $iFraudguardScore = $sxml->getResult('antiFraudScore');
                $bFraudguardPassed = ($iFraudguardScore < 10);

                if($this->getDebug()) {
                    $logger->info("FraudGuard result: " . $iFraudguardResponse.' ('.$iFraudguardScore.')');
                }

                if ($bFraudguardPassed) {
                    //Fraudguard passed, so now capture the funds
                    $sxml = new securexml_transaction($this->getMode(NO_ANTIFRAUD), $this->getUsername($iStoreId), $this->getPassword($iStoreId));
                    $approved = $sxml->processCredit($amount, $transaction_id, $payment->getCcNumber(), $payment->getCcExpMonth(), $payment->getCcExpYear(), $payment->getCcCid(), Mage::app()->getStore()->getBaseCurrency()->getCurrencyCode());

                } else {
                    // Only request authorization for transactions that fail Fraudguard checks
                    $sxml = new securexml_transaction ($this->getMode(NO_ANTIFRAUD),$this->getUsername($iStoreId),$this->getPassword($iStoreId));
                    $approved = $sxml->processPreauth($amount, $transaction_id, $payment->getCcNumber(), $payment->getCcExpMonth(), $payment->getCcExpYear(), $payment->getCcCid(), Mage::app()->getStore()->getBaseCurrency()->getCurrencyCode());

                    if ($approved) {
                        $preauth_id = (string) $sxml->getResult('preauth_id');
                        $payment->setCcTransId($preauth_id);
                        $payment->setTransactionId($preauth_id);

                        // We only get here if fraudguard thinks this transaction is fraudulent
                        $payment->setIsTransactionPending(true);
                        $payment->setIsFraudDetected(true);
                        return $this;
                    }
                }
			} else {
				//Issue a standard transaction
                $approved = $sxml->processCredit($amount, $transaction_id, $payment->getCcNumber(), $payment->getCcExpMonth(), $payment->getCcExpYear(), $payment->getCcCid(), Mage::app()->getStore()->getBaseCurrency()->getCurrencyCode());
			}
		}
		
		if($approved) {
			$transaction_id = (string) $sxml->getResult('transaction_id');
			$payment->setCcTransId($transaction_id);
			$payment->setTransactionId($transaction_id);
			if($this->getDebug())
			{
				$logger->info("Advice/Standard Approved. Currency = " . Mage::app()->getStore()->getBaseCurrency()->getCurrencyCode() . " Response ID: " . $payment->getCcTransId());
			}
		}
		else
		{
			$error = $sxml->getError();
			
			if($this->getDebug())
			{
				$logger->info("Advice/Standard Declined. " . $error);
			}
			
			Mage::throwException("" . $error);
		}
		
		return $this;
	}
	
	/**
	 * void
	 *
	 * Handles reverse transactions.
	 *
	 * @param Varien_Object $payment
	 * @param int/float $amount
	 *
	 * @return Mage_Payment_Model_Method_Cc $this. Failure will throw Mage::throwException('description')
	 */
	public function void(Varien_Object $payment)
	{
		if($this->getDebug())
		{
			$writer = new Zend_Log_Writer_Stream($this->getLogPath());
			$logger = new Zend_Log($writer);
		}
		$amount = $payment->getOrder()->getData('grand_total');
		$iStoreId = $payment->getOrder()->getStoreId();
		$bankRespID = $payment->getCcTransId();
		
		if(!$bankRespID)
		{
			Mage::throwException("Cannot issue a void on this transaction: bank response id is missing.");
		}
		
		if(!$amount)
		{
			Mage::throwException("Cannot issue a void on this transaction: transaction amount is missing.");
		}
		
		//Create the transaction object
		$sxml = new securexml_transaction ($this->getMode(NO_ANTIFRAUD),$this->getUsername($iStoreId),$this->getPassword($iStoreId));
		
		$transaction_id = $payment->getOrder()->getIncrementId();
		
		//Issue a reverse transaction
		if($sxml->processReverse($amount,$transaction_id,$bankRespID))
		{
			$transaction_id = $sxml->getResult('transaction_id');
			
			$payment->setCcTransId(''.$transaction_id);
			$payment->setTransactionId(''.$transaction_id);
			
			if($this->getDebug())
			{
				$logger->info( "Reverse Approved. Response ID: ".$transaction_id );
			}
		}
		else
		{
			$error = $sxml->getError();
			
			if($this->getDebug())
			{
				$logger->info("Reverse Declined. ".$error);
			}
			
			Mage::throwException("" . $error);
		}
		
		return $this;
	}
	
	/**
	 * refund
	 *
	 * Processes a partial or whole refund on an existing transaction.
	 *
	 * @param Varien_Object $payment
	 * @param int/float $amount
	 *
	 * @return Mage_Payment_Model_Method_Cc $this. Failure will throw Mage::throwException('description')
	 */
	public function refund(Varien_Object $payment, $amount)
	{
		if($this->getDebug())
		{
			$writer = new Zend_Log_Writer_Stream($this->getLogPath());
			$logger = new Zend_Log($writer);
		}
		
		$bankRespID = $payment->getCcTransId();
		$iStoreId = $payment->getOrder()->getStoreId();
		if(!$bankRespID)
		{
			Mage::throwException("Cannot issue a refund on this transaction: bank response id is missing.");
		}
		
		//Create the transaction object
		$sxml = new securexml_transaction ($this->getMode(NO_ANTIFRAUD), $this->getUsername($iStoreId), $this->getPassword($iStoreId));
		
		$transaction_id = $payment->getOrder()->getIncrementId();
		
		if($sxml->processRefund($amount,$transaction_id,$bankRespID))
		{
			$transaction_id = $sxml->getResult('transaction_id');
            $payment->setCcTransId(''.$transaction_id);
            $payment->setTransactionId(''.$transaction_id);
			
			if($this->getDebug())
			{
				$logger->info( "Refund Approved. Response ID: ".$transaction_id );
			}
			
			/* Don't reset $payment->CcTransId for refunds, so that more than one is possible. This means that the gateway response id ($transaction_id) is not stored here. If necessary, it can be recovered from the SecurePay Merchant Management Facility. http://securepay.com.au */
		}
		else
		{
			$error = $sxml->getError();
			
			if($this->getDebug())
			{
				$logger->info("Refund Declined. ".$error);
			}
			
			Mage::throwException("" . $error);
		}
		
		return $this;
	}


    public function acceptPayment(Mage_Payment_Model_Info $payment) {
        parent::acceptPayment($payment);

        $iStoreId = $payment->getOrder()->getStoreId();
        if (Mage::getStoreConfig('payment/Sxml/payment_action', $iStoreId) == self::PAYMENT_ACTION_AUTH_CAPTURE) {
            // Perform gateway actions to remove Fraud flags, in this case that means capturing
            // against the existing authorization if we're in "Auth and Capture" mode.
            $fAmountToCapture = $payment->getAmountAuthorized() - $payment->getAmountPaid();
            $this->capture($payment, $fAmountToCapture);
        }
        return true;
    }

    public function denyPayment(Mage_Payment_Model_Info $payment) {
        parent::denyPayment($payment);
        //SecurePay doesn't support voiding an auth
        return true;
    }
}
