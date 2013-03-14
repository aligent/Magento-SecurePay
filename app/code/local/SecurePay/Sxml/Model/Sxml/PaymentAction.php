<?php
/**
 * SecurePay SecurePayXML Extension
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
 * @category   SecurePay
 * @package    SecurePay_SecurePayXML
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class SecurePay_Sxml_Model_Sxml_PaymentAction
{
	public function toOptionArray()
	{
		return array(
			array(
				'value' => SecurePay_Sxml_Model_Sxml::PAYMENT_ACTION_AUTH_CAPTURE,
				'label' => Mage::helper('Sxml')->__('Authorize and Capture')
			),
			array(
				'value' => SecurePay_Sxml_Model_Sxml::PAYMENT_ACTION_AUTH,
				'label' => Mage::helper('Sxml')->__('Authorize')
			)
		);
	}
}

?>
