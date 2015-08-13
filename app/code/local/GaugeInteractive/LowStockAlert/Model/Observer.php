<?php

class GaugeInteractive_LowStockAlert_Model_Observer
{
    const XML_PATH_LOWSTOCKALERT_ENABLED = 'low_qty_alert/settings/enabled';
    const XML_PATH_RECIPIENT_EMAIL = 'low_qty_alert/settings/recipient_email';
    const XML_PATH_RECIPIENT_NAME = 'low_qty_alert/settings/recipient_name';
    const XML_PATH_SENDER_EMAIL = 'low_qty_alert/settings/sender_email';
    const XML_PATH_EMAIL_TEMPLATE_ID = 'low_qty_alert/settings/low_inventory_email_template';
    const XML_PATH_BCC_LIST = 'low_qty_alert/settings/bcc_list';
    const XML_PATH_MIN_QTY_THRESHOLD = 'low_qty_alert/settings/min_qty_threshold';


    function checkQuantity($observer)
    {
        $enabled = Mage::getStoreConfig(self::XML_PATH_LOWSTOCKALERT_ENABLED);
        $recipientEmail = Mage::getStoreConfig(self::XML_PATH_RECIPIENT_EMAIL);
        $recipientName = Mage::getStoreConfig(self::XML_PATH_RECIPIENT_NAME);
        $senderEmail = Mage::getStoreConfig(self::XML_PATH_SENDER_EMAIL);
        $emailTemplateId = Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE_ID);
        $minQtyThreshold = Mage::getStoreConfig(self::XML_PATH_MIN_QTY_THRESHOLD);
        $bccList = Mage::getStoreConfig(self::XML_PATH_BCC_LIST);

        if ($enabled) {
            $order = $observer->getOrder();
            $items = $order->getAllVisibleItems();

            foreach ($items as $item) {
                $sku = $item->getSku();
                $product = Mage::getModel('catalog/product')->load($item->getProductId());
                $qty = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getQty();

                if ($qty < $minQtyThreshold) {

                    $bccList = explode(';', $bccList);

                    // Store all variables in an array
                    $vars = array(
                        'sku' => $sku,
                        'sender_email' => $senderEmail,
                        'recipient_email' => $recipientEmail,
                        'recipient_name' => $recipientName,
                        'email_template_id' => $emailTemplateId,
                        'qty' => $qty,
                        'bcc' => $bccList,
                    );

                    $this->sendTransactionalEmail($vars);
                }
            }
        }
    }


    function sendTransactionalEmail($vars)
    {
        $storeId = Mage::app()->getStore()->getStoreId();

        // Set sender information
        $senderName = Mage::getStoreConfig('trans_email/ident_support/name');

        $sender = array(
            'name' => $senderName,
            'email' => $vars['sender_email']
        );

        // Set variables that can be used in email template
        $templateVariables = array(
            'qty' => $vars['qty'],
            'sku' => $vars['sku'],
            'customerName' => $vars['recipient_name'],
            'customerEmail' => $vars['recipient_email']
        );

        $translate = Mage::getSingleton('core/translate');

        // Send Transactional Email
        $send = Mage::getModel('core/email_template');
        $send->addBcc($vars['bcc']);
        $send->sendTransactional($vars['email_template_id'], $sender, $vars['recipient_email'], $vars['recipient_name'], $templateVariables, $storeId);

        $translate->setTranslateInline(true);
        Mage::log($vars['sku'] . ' has a qty of ' . $vars['qty'], null, 'ethanLowQty.log');
    }
}