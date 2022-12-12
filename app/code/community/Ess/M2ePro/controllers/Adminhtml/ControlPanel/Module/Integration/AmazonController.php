<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

class Ess_M2ePro_Adminhtml_ControlPanel_Module_Integration_AmazonController
    extends Ess_M2ePro_Controller_Adminhtml_ControlPanel_CommandController
{
    //########################################

    /**
     * @title "Show Duplicates [listing_id/sku]"
     * @description "[MAX(id) will be saved]"
     */
    public function showAmazonDuplicatesAction()
    {
        /** @var $writeConnection Varien_Db_Adapter_Pdo_Mysql */
        $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $structureHelper = Mage::helper('M2ePro/Module_Database_Structure');

        $lp = $structureHelper->getTableNameWithPrefix('m2epro_listing_product');
        $alp = $structureHelper->getTableNameWithPrefix('m2epro_amazon_listing_product');
        $alpr = $structureHelper->getTableNameWithPrefix('m2epro_amazon_listing_product_repricing');

        $subQuery = $writeConnection
            ->select()
            ->from(
                array('malp' => $alp),
                array('general_id','sku')
            )
            ->joinInner(
                array('mlp' => $lp),
                'mlp.id = malp.listing_product_id',
                array('listing_id',
                              'product_id',
                              new Zend_Db_Expr('COUNT(product_id) - 1 AS count_of_duplicates'),
                              new Zend_Db_Expr('MIN(mlp.id) AS save_this_id'),
                )
            )
            ->group(array('mlp.listing_id', 'malp.sku'))
            ->having(new Zend_Db_Expr('count_of_duplicates > 0'));

        $query = $writeConnection
            ->select()
            ->from(
                array('malp' => $alp),
                array('listing_product_id')
            )
            ->joinInner(
                array('mlp' => $lp),
                'mlp.id = malp.listing_product_id',
                array('status')
            )
            ->joinInner(
                array('templ_table' => $subQuery),
                'malp.sku = templ_table.sku AND mlp.listing_id = templ_table.listing_id'
            )
            ->where('malp.listing_product_id <> templ_table.save_this_id')
            ->query();

        $removed = 0;
        $duplicated = array();

        while ($row = $query->fetch()) {
            if ((bool)$this->getRequest()->getParam('remove', false)) {
                $writeConnection->delete(
                    $lp, array('id = ?' => $row['listing_product_id'])
                );

                $writeConnection->delete(
                    $alp, array('listing_product_id = ?' => $row['listing_product_id'])
                );

                $writeConnection->delete(
                    $alpr, array('listing_product_id = ?' => $row['listing_product_id'])
                );

                $removed++;
                continue;
            }

            $duplicated[$row['save_this_id']] = $row;
        }

        if (empty($duplicated)) {
            $message = 'There are no duplicates.';
            $removed > 0 && $message .= ' Removed: ' . $removed;

            return $this->getResponse()->setBody($this->getEmptyResultsHtml($message));
        }

        $tableContent = <<<HTML
<tr>
    <th>Listing ID</th>
    <th>Magento Product ID</th>
    <th>SKU</th>
    <th>Count Of Copies</th>
</tr>
HTML;
        foreach ($duplicated as $row) {
            $tableContent .= <<<HTML
<tr>
    <td>{$row['listing_id']}</td>
    <td>{$row['product_id']}</td>
    <td>{$row['sku']}</td>
    <td>{$row['count_of_duplicates']}</td>
</tr>
HTML;
        }

        $url = Mage::helper('adminhtml')->getUrl('*/*/*', array('remove' => '1'));
        $html = $this->getStyleHtml() . <<<HTML
<html>
    <body>
        <h2 style="margin: 20px 0 0 10px">Amazon Duplicates [group by SKU and listing_id]
            <span style="color: #808080; font-size: 15px;">(#count# entries)</span>
        </h2>
        <br/>
        <table class="grid" cellpadding="0" cellspacing="0">
            {$tableContent}
        </table>
        <form action="{$url}" method="get" style="margin-top: 1em;">
            <button type="submit">Remove</button>
        </form>
    </body>
</html>
HTML;
        return $this->getResponse()->setBody(str_replace('#count#', count($duplicated), $html));
    }

    /**
     * @title "Fix Amazon Items"
     * @description "Insert records in amazon_items table"
     */
    public function fixAmazonItemsAction()
    {
        /** @var Ess_M2ePro_Model_Resource_Listing_Collection $listingProductCollection */
        $listingProductCollection = Mage::getModel(
            'M2ePro/Listing_Product',
            array('child_mode' => Ess_M2ePro_Helper_Component_Amazon::NICK)
        )->getCollection();

        $listingProductCollection
            ->addFieldToFilter('status', array('neq' => Ess_M2ePro_Model_Listing_Product::STATUS_NOT_LISTED));

        $listingProductCollection->getSelect()->joinLeft(
            array('l' => Mage::getResourceModel('M2ePro/Listing')->getMainTable()),
            'main_table.listing_id = l.id',
            array()
        );
        $listingProductCollection->getSelect()->joinLeft(
            array('ai' => Mage::getResourceModel('M2ePro/Amazon_Item')->getMainTable()),
            <<<SQL
second_table.sku = ai.sku
AND l.account_id = ai.account_id
AND l.marketplace_id = ai.marketplace_id
SQL
            ,
            array()
        );
        $listingProductCollection->addFieldToFilter('ai.sku', array('null' => true));

        $startFix = (bool)$this->getRequest()->getParam('start_fix', false);
        /** @var Mage_Core_Model_Session_Abstract $session */
        $session = Mage::getSingleton('customer/session');
        if ($startFix) {
            $start = microtime(true);
            /** @var Ess_M2ePro_Model_Amazon_Listing_Product_Action_Type_List_Linking $linkingObject */
            $linkingObject = Mage::getModel('M2ePro/Amazon_Listing_Product_Action_Type_List_Linking');
            /** @var Ess_M2ePro_Helper_Module_Configuration $moduleConfiguration */
            $moduleConfiguration = Mage::helper('M2ePro/Module_Configuration');
            foreach ($listingProductCollection->getItems() as $listingProduct) {
                if ($listingProduct->getMagentoProduct()->isGroupedType()
                    && $moduleConfiguration->isGroupedProductModeSet()
                ) {
                    $listingProduct->setSetting('additional_data', 'grouped_product_mode', 1);
                    $listingProduct->save();
                }

                $linkingObject->setListingProduct($listingProduct);
                $linkingObject->createAmazonItem();
            }

            $session->addSuccess(sprintf(
                'Listing product fixed. Executed time %01.4f sec',
                microtime(true) - $start
            ));

            return $this->_redirectUrl($this->_getRefererUrl());
        }

        $messagesCollection = $session->getMessages(true);
        $successMessage = '';
        if ($messagesCollection->count() > 0) {
            $successMessages = array_map(function (Mage_Core_Model_Message_Abstract $message) {
                return $message->getText();
            }, $messagesCollection->getItems());

            $successMessage = '<p class="success">';
            $successMessage .= implode('<br>', $successMessages);
            $successMessage .= '</p>';
        }

        $backUrl = Mage::helper('M2ePro/View_ControlPanel')->getPageToolsTabUrl();
        $count = $listingProductCollection->getSize();

        $html = <<<HTML
<html>
    <head>
        <title>M2E Pro | Fix Amazon Items</title>
        <style>
            button {
                border-radius: 3px;
                padding: 7px;
                border: 1px solid grey;
                cursor: pointer;
            }
            button:hover {
                background-color: lightgrey;
            }
            p.success {
                color: darkgreen
            }
        </style>
    </head>
    <body>
        <a href="$backUrl">⇦ Back to Control Panel</a>
        <h2>Fix Amazon Items</h2>
        <p>Listing products without record in <code>amazon_item</code> table: <strong>$count</strong></p>
        $successMessage
        <form method="get">
            <input type="hidden" name="start_fix" value="1">
            <button type="submit">Start Fix</button>
        </form>
    </body>
</html>
HTML;

        return $this->getResponse()->setBody($html);
    }

    //########################################

    protected function getEmptyResultsHtml($messageText)
    {
        $backUrl = Mage::helper('M2ePro/View_ControlPanel')->getPageToolsTabUrl();

        return <<<HTML
    <h2 style="margin: 20px 0 0 10px">
        {$messageText} <span style="color: grey; font-size: 10px;">
        <a href="{$backUrl}">[back]</a>
    </h2>
HTML;
    }

    //########################################
}
