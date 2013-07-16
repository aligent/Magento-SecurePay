<?php
$installer = new Mage_Catalog_Model_Resource_Eav_Mysql4_Setup('core_setup');
$installer->startSetup();

$installer->run("DELETE FROM core_resource WHERE code='Sxml_setup'");

$aStoreIds = Mage::getModel('core/store')->getCollection()->getAllIds();

ob_start();
?>
    <p>A problem occurred with your order.  Please contact our customer service representatives for assistance to resolve this issue.</p>
<?php

$vContents = ob_get_clean();

Mage::getModel('cms/page')
    ->load('order_problem', 'identifier') // This should makes it safe to run this script
    // more than once without creating multiple pages.
    ->setTitle('Problem with order')
    ->setIdentifier('order_problem')
    ->setIsActive(true)
    ->setUnderVersionControl(false)
    ->setStores(array($aStoreIds))
    ->setContent($vContents)
    ->setRootTemplate('one_column')
    ->save();

$installer->setConfigData('payment/Sxml/antifraud_page', 'order_problem');

$installer->endSetup();
