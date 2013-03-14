<?php

class SecurePay_Sxml_Model_Source_Cctype extends Mage_Payment_Model_Source_Cctype
{
    public function getAllowedTypes()
    {  
        return array('VI', 'DICL', 'AE', 'JCB', 'MC');
    }
}
?>
