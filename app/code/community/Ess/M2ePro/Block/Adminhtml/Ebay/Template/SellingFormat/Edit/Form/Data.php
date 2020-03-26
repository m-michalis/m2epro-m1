<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

class Ess_M2ePro_Block_Adminhtml_Ebay_Template_SellingFormat_Edit_Form_Data extends Mage_Adminhtml_Block_Widget
{
    public $attributes = array();
    public $enabledMarketplaces = null;

    //########################################

    public function __construct()
    {
        parent::__construct();

        $this->setId('ebayTemplateSellingFormatEditFormData');

        $this->setTemplate('M2ePro/ebay/template/sellingFormat/form/data.phtml');

        $this->attributes = Mage::helper('M2ePro/Data_Global')->getValue('ebay_attributes');
    }

    protected function _beforeToHtml()
    {
        parent::_beforeToHtml();

        if ($this->isCharity()) {
            $data = array(
                'label'   => Mage::helper('M2ePro')->__('Add Charity'),
                'onclick' => 'EbayTemplateSellingFormatHandlerObj.addCharityRow();',
                'class'   => 'action primary add_charity_button'
            );
            $buttonBlock = $this->getLayout()->createBlock('adminhtml/widget_button')->setData($data);
            $this->setChild('add_charity_button', $buttonBlock);

            $data = array(
                'label'   => Mage::helper('M2ePro')->__('Remove'),
                'onclick' => 'EbayTemplateSellingFormatHandlerObj.removeCharityRow(this);',
                'class'   => 'delete icon-btn remove_charity_button'
            );
            $buttonBlock = $this->getLayout()->createBlock('adminhtml/widget_button')->setData($data);
            $this->setChild('remove_charity_button', $buttonBlock);
        }
    }

    //########################################

    public function isCustom()
    {
        if (isset($this->_data['is_custom'])) {
            return (bool)$this->_data['is_custom'];
        }

        return false;
    }

    public function getTitle()
    {
        if ($this->isCustom()) {
            return isset($this->_data['custom_title']) ? $this->_data['custom_title'] : '';
        }

        $template = Mage::helper('M2ePro/Data_Global')->getValue('ebay_template_selling_format');

        if ($template === null) {
            return '';
        }

        return $template->getTitle();
    }

    //########################################

    public function getFormData()
    {
        $template = Mage::helper('M2ePro/Data_Global')->getValue('ebay_template_selling_format');

        if ($template === null || $template->getId() === null) {
            return array();
        }

        $data = $template->getData();

        $charity = Mage::helper('M2ePro')->jsonDecode($data['charity']);
        $availableCharity = array();

        foreach ($this->getEnabledMarketplaces() as $marketplace) {
            if (isset($charity[$marketplace->getId()])) {
                $availableCharity[$marketplace->getId()] = $charity[$marketplace->getId()];
            }
        }

        $data['charity'] = $availableCharity;

        return $data;
    }

    public function getDefault()
    {
        return Mage::getSingleton('M2ePro/Ebay_Template_SellingFormat')->getDefaultSettings();
    }

    //########################################

    public function getCurrency()
    {
        $marketplace = Mage::helper('M2ePro/Data_Global')->getValue('ebay_marketplace');

        if ($marketplace === null) {
            return null;
        }

        return $marketplace->getChildObject()->getCurrency();
    }

    public function getCurrencyAvailabilityMessage()
    {
        $marketplace = Mage::helper('M2ePro/Data_Global')->getValue('ebay_marketplace');
        $store = Mage::helper('M2ePro/Data_Global')->getValue('ebay_store');
        $template = Mage::helper('M2ePro/Data_Global')->getValue('ebay_template_selling_format');

        if ($template === null || $template->getId() === null) {
            $templateData = $this->getDefault();
            $templateData['component_mode'] = Ess_M2ePro_Helper_Component_Ebay::NICK;
            $usedAttributes = array();
        } else {
            $templateData = $template->getData();
            $usedAttributes = $template->getUsedAttributes();
        }

        $messagesBlock = $this->getLayout()
            ->createBlock('M2ePro/adminhtml_template_messages')
            ->getResultBlock(
                Ess_M2ePro_Model_Ebay_Template_Manager::TEMPLATE_SELLING_FORMAT,
                Ess_M2ePro_Helper_Component_Ebay::NICK
            );

        $messagesBlock->setData('template_data', $templateData);
        $messagesBlock->setData('used_attributes', $usedAttributes);
        $messagesBlock->setData('marketplace_id', $marketplace ? $marketplace->getId() : null);
        $messagesBlock->setData('store_id', $store ? $store->getId() : null);

        $messages = $messagesBlock->getMessages();

        if (empty($messages)) {
            return '';
        }

        return $messagesBlock->getMessagesHtml($messages);
    }

    /**
     * @return  Ess_M2ePro_Model_Marketplace|null
    **/
    public function getMarketplace()
    {
        return Mage::helper('M2ePro/Data_Global')->getValue('ebay_marketplace');
    }

    public function getMarketplaceId()
    {
        $marketplace = $this->getMarketplace();

        if ($marketplace === null) {
            return null;
        }

        return $marketplace->getId();
    }

    public function getCharityDictionary()
    {
        return Mage::getModel('M2ePro/Ebay_Template_SellingFormat')->getResource()->getCharityDictionary();
    }

    public function getEnabledMarketplaces()
    {
        if ($this->enabledMarketplaces === null) {
            if ($this->getMarketplace() !== null) {
                $this->enabledMarketplaces = array($this->getMarketplace());
            } else {
                $collection = Mage::getModel('M2ePro/Marketplace')->getCollection();
                $collection->addFieldToFilter('component_mode', Ess_M2ePro_Helper_Component_Ebay::NICK);
                $collection->addFieldToFilter('status', Ess_M2ePro_Model_Marketplace::STATUS_ENABLE);
                $collection->setOrder('sorder', 'ASC');

                $this->enabledMarketplaces = $collection->getItems();
            }
        }

        return $this->enabledMarketplaces;
    }

    //########################################

    public function isCharity()
    {
        $marketplace = $this->getMarketplace();

        if ($marketplace === null) {
            return true;
        }

        if ($marketplace->getChildObject()->isCharityEnabled()) {
           return true;
        }

        return false;
    }

    public function isStpAvailable()
    {
        $marketplace = $this->getMarketplace();
        if ($marketplace === null) {
            return true;
        }

        if ($marketplace->getChildObject()->isStpEnabled()) {
            return true;
        }

        return false;
    }

    public function isStpAdvancedAvailable()
    {
        $marketplace = $this->getMarketplace();
        if ($marketplace === null) {
            return true;
        }

        if ($marketplace->getChildObject()->isStpAdvancedEnabled()) {
            return true;
        }

        return false;
    }

    public function isMapAvailable()
    {
        $marketplace = $this->getMarketplace();
        if ($marketplace === null) {
            return true;
        }

        if ($marketplace->getChildObject()->isMapEnabled()) {
            return true;
        }

        return false;
    }

    //########################################

    public function getTaxCategoriesInfo()
    {
        $marketplacesCollection = Mage::helper('M2ePro/Component_Ebay')->getModel('Marketplace')
            ->getCollection()
            ->addFieldToFilter('status', Ess_M2ePro_Model_Marketplace::STATUS_ENABLE)
            ->setOrder('sorder', 'ASC');

        $marketplacesCollection->getSelect()->limit(1);

        $marketplaces = $marketplacesCollection->getItems();

        if (empty($marketplaces)) {
            return array();
        }

        return array_shift($marketplaces)->getChildObject()->getTaxCategoryInfo();
    }

    //########################################
}
