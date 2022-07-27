<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

/**
 * @method Ess_M2ePro_Model_Account getParentObject()
 */
class Ess_M2ePro_Model_Amazon_Account extends Ess_M2ePro_Model_Component_Child_Amazon_Abstract
{
    const OTHER_LISTINGS_MAPPING_GENERAL_ID_MODE_NONE             = 0;
    const OTHER_LISTINGS_MAPPING_GENERAL_ID_MODE_CUSTOM_ATTRIBUTE = 1;

    const OTHER_LISTINGS_MAPPING_SKU_MODE_NONE             = 0;
    const OTHER_LISTINGS_MAPPING_SKU_MODE_DEFAULT          = 1;
    const OTHER_LISTINGS_MAPPING_SKU_MODE_CUSTOM_ATTRIBUTE = 2;
    const OTHER_LISTINGS_MAPPING_SKU_MODE_PRODUCT_ID       = 3;

    const OTHER_LISTINGS_MAPPING_TITLE_MODE_NONE             = 0;
    const OTHER_LISTINGS_MAPPING_TITLE_MODE_DEFAULT          = 1;
    const OTHER_LISTINGS_MAPPING_TITLE_MODE_CUSTOM_ATTRIBUTE = 2;

    const OTHER_LISTINGS_MAPPING_SKU_DEFAULT_PRIORITY        = 1;
    const OTHER_LISTINGS_MAPPING_GENERAL_ID_DEFAULT_PRIORITY = 2;
    const OTHER_LISTINGS_MAPPING_TITLE_DEFAULT_PRIORITY      = 3;

    const MAGENTO_ORDERS_LISTINGS_STORE_MODE_DEFAULT = 0;
    const MAGENTO_ORDERS_LISTINGS_STORE_MODE_CUSTOM  = 1;

    const MAGENTO_ORDERS_LISTINGS_OTHER_PRODUCT_MODE_IGNORE = 0;
    const MAGENTO_ORDERS_LISTINGS_OTHER_PRODUCT_MODE_IMPORT = 1;

    const MAGENTO_ORDERS_NUMBER_SOURCE_MAGENTO = 'magento';
    const MAGENTO_ORDERS_NUMBER_SOURCE_CHANNEL = 'channel';

    const MAGENTO_ORDERS_TAX_MODE_NONE    = 0;
    const MAGENTO_ORDERS_TAX_MODE_CHANNEL = 1;
    const MAGENTO_ORDERS_TAX_MODE_MAGENTO = 2;
    const MAGENTO_ORDERS_TAX_MODE_MIXED   = 3;

    const SKIP_TAX_FOR_UK_SHIPMENT_NONE               = 0;
    const SKIP_TAX_FOR_UK_SHIPMENT                    = 1;
    const SKIP_TAX_FOR_UK_SHIPMENT_WITH_CERTAIN_PRICE = 2;

    const MAGENTO_ORDERS_CUSTOMER_MODE_GUEST      = 0;
    const MAGENTO_ORDERS_CUSTOMER_MODE_PREDEFINED = 1;
    const MAGENTO_ORDERS_CUSTOMER_MODE_NEW        = 2;

    const USE_SHIPPING_ADDRESS_AS_BILLING_ALWAYS                         = 0;
    const USE_SHIPPING_ADDRESS_AS_BILLING_IF_SAME_CUSTOMER_AND_RECIPIENT = 1;

    const MAGENTO_ORDERS_STATUS_MAPPING_MODE_DEFAULT = 0;
    const MAGENTO_ORDERS_STATUS_MAPPING_MODE_CUSTOM  = 1;

    const MAGENTO_ORDERS_STATUS_MAPPING_NEW        = 'pending';
    const MAGENTO_ORDERS_STATUS_MAPPING_PROCESSING = 'processing';
    const MAGENTO_ORDERS_STATUS_MAPPING_SHIPPED    = 'complete';

    const AUTO_INVOICING_DISABLED                = 0;
    const AUTO_INVOICING_VAT_CALCULATION_SERVICE = 1;
    const AUTO_INVOICING_UPLOAD_MAGENTO_INVOICES = 2;

    const INVOICE_GENERATION_BY_AMAZON    = 1;
    const INVOICE_GENERATION_BY_EXTENSION = 2;

    /**
     * @var Ess_M2ePro_Model_Marketplace
     */
    protected $_marketplaceModel = null;

    /**
     * @var Ess_M2ePro_Model_Amazon_Account_Repricing
     */
    protected $_repricingModel = null;

    /** @var Ess_M2ePro_Model_ActiveRecord_Factory */
    protected $_activeRecordFactory;

    //########################################

    public function _construct()
    {
        parent::_construct();
        $this->_init('M2ePro/Amazon_Account');
        $this->_activeRecordFactory = Mage::getSingleton('M2ePro/ActiveRecord_Factory');
    }

    //########################################

    public function deleteInstance()
    {
        if ($this->isLocked()) {
            return false;
        }

        $itemCollection = $this->_activeRecordFactory->getObjectCollection('Amazon_Item');
        $itemCollection->addFieldToFilter('account_id', $this->getId());
        foreach ($itemCollection->getItems() as $item) {
            $item->deleteInstance();
        }

        if ($this->isRepricing()) {
            $this->getRepricing()->deleteInstance();
            $this->_repricingModel = null;
        }

        $this->_marketplaceModel = null;

        $this->delete();

        return true;
    }

    //########################################

    /**
     * @return Ess_M2ePro_Model_Marketplace
     */
    public function getMarketplace()
    {
        if ($this->_marketplaceModel === null) {
            $this->_marketplaceModel = Mage::helper('M2ePro/Component_Amazon')->getCachedObject(
                'Marketplace',
                $this->getMarketplaceId()
            );
        }

        return $this->_marketplaceModel;
    }

    /**
     * @param Ess_M2ePro_Model_Marketplace $instance
     */
    public function setMarketplace(Ess_M2ePro_Model_Marketplace $instance)
    {
        $this->_marketplaceModel = $instance;
    }

    //########################################

    /**
     * @return bool
     */
    public function isRepricing()
    {
        $cacheKey = 'amazon_account_' . $this->getId() . '_is_repricing';
        $cacheData = Mage::helper('M2ePro/Data_Cache_Permanent')->getValue($cacheKey);

        if ($cacheData !== false) {
            return (bool)$cacheData;
        }

        $repricingCollection = Mage::getResourceModel('M2ePro/Amazon_Account_Repricing_Collection');
        $repricingCollection->addFieldToFilter('account_id', $this->getId());
        $isRepricing = (int)(bool)$repricingCollection->getSize();

        Mage::helper('M2ePro/Data_Cache_Permanent')->setValue($cacheKey, $isRepricing, array('account'));

        return (bool)$isRepricing;
    }

    /**
     * @return Ess_M2ePro_Model_Amazon_Account_Repricing
     */
    public function getRepricing()
    {
        if ($this->_repricingModel === null) {
            $this->_repricingModel = Mage::helper('M2ePro')->getCachedObject(
                'Amazon_Account_Repricing',
                $this->getId(),
                null,
                array('account')
            );
        }

        return $this->_repricingModel;
    }

    //########################################

    public function getServerHash()
    {
        return $this->getData('server_hash');
    }

    /**
     * @return int
     */
    public function getMarketplaceId()
    {
        return (int)$this->getData('marketplace_id');
    }

    public function getMerchantId()
    {
        return $this->getData('merchant_id');
    }

    public function getToken()
    {
        return $this->getData('token');
    }

    /**
     * @return int
     */
    public function getRelatedStoreId()
    {
        return (int)$this->getData('related_store_id');
    }

    // ---------------------------------------

    public function getInfo()
    {
        return $this->getData('info');
    }

    /**
     * @return array|null
     */
    public function getDecodedInfo()
    {
        $tempInfo = $this->getInfo();

        return $tempInfo === null ? null : Mage::helper('M2ePro')->jsonDecode($tempInfo);
    }

    //########################################

    /**
     * @return int
     */
    public function getOtherListingsSynchronization()
    {
        return (int)$this->getData('other_listings_synchronization');
    }

    /**
     * @return int
     */
    public function getOtherListingsMappingMode()
    {
        return (int)$this->getData('other_listings_mapping_mode');
    }

    /**
     * @return array
     * @throws Ess_M2ePro_Model_Exception_Logic
     */
    public function getOtherListingsMappingSettings()
    {
        return $this->getSettings('other_listings_mapping_settings');
    }

    // ---------------------------------------

    /**
     * @return int
     */
    public function getOtherListingsMappingGeneralIdMode()
    {
        $setting = $this->getSetting(
            'other_listings_mapping_settings',
            array('general_id', 'mode'),
            self::OTHER_LISTINGS_MAPPING_GENERAL_ID_MODE_NONE
        );

        return (int)$setting;
    }

    /**
     * @return int
     */
    public function getOtherListingsMappingGeneralIdPriority()
    {
        $setting = $this->getSetting(
            'other_listings_mapping_settings',
            array('general_id', 'priority'),
            self::OTHER_LISTINGS_MAPPING_GENERAL_ID_DEFAULT_PRIORITY
        );

        return (int)$setting;
    }

    public function getOtherListingsMappingGeneralIdAttribute()
    {
        $setting = $this->getSetting('other_listings_mapping_settings', array('general_id', 'attribute'));

        return $setting;
    }

    // ---------------------------------------

    /**
     * @return int
     */
    public function getOtherListingsMappingSkuMode()
    {
        $setting = $this->getSetting(
            'other_listings_mapping_settings',
            array('sku', 'mode'),
            self::OTHER_LISTINGS_MAPPING_SKU_MODE_NONE
        );

        return (int)$setting;
    }

    /**
     * @return int
     */
    public function getOtherListingsMappingSkuPriority()
    {
        $setting = $this->getSetting(
            'other_listings_mapping_settings',
            array('sku', 'priority'),
            self::OTHER_LISTINGS_MAPPING_SKU_DEFAULT_PRIORITY
        );

        return (int)$setting;
    }

    public function getOtherListingsMappingSkuAttribute()
    {
        $setting = $this->getSetting(
            'other_listings_mapping_settings',
            array('sku', 'attribute')
        );

        return $setting;
    }

    // ---------------------------------------

    /**
     * @return int
     */
    public function getOtherListingsMappingTitleMode()
    {
        $setting = $this->getSetting(
            'other_listings_mapping_settings',
            array('title', 'mode'),
            self::OTHER_LISTINGS_MAPPING_TITLE_MODE_NONE
        );

        return (int)$setting;
    }

    /**
     * @return int
     */
    public function getOtherListingsMappingTitlePriority()
    {
        $setting = $this->getSetting(
            'other_listings_mapping_settings',
            array('title', 'priority'),
            self::OTHER_LISTINGS_MAPPING_TITLE_DEFAULT_PRIORITY
        );

        return (int)$setting;
    }

    public function getOtherListingsMappingTitleAttribute()
    {
        $setting = $this->getSetting('other_listings_mapping_settings', array('title', 'attribute'));

        return $setting;
    }

    //########################################

    /**
     * @return bool
     */
    public function isOtherListingsSynchronizationEnabled()
    {
        return $this->getOtherListingsSynchronization() == 1;
    }

    /**
     * @return bool
     */
    public function isOtherListingsMappingEnabled()
    {
        return $this->getOtherListingsMappingMode() == 1;
    }

    /**
     * @return bool
     */
    public function isImportShipByDateToMagentoOrder()
    {
        return (bool)$this->getSetting(
            'magento_orders_settings',
            array('shipping_information', 'ship_by_date'),
            true
        );
    }

    // ---------------------------------------

    /**
     * @return bool
     */
    public function isOtherListingsMappingGeneralIdModeNone()
    {
        return $this->getOtherListingsMappingGeneralIdMode() == self::OTHER_LISTINGS_MAPPING_GENERAL_ID_MODE_NONE;
    }

    /**
     * @return bool
     */
    public function isOtherListingsMappingGeneralIdModeCustomAttribute()
    {
        return $this->getOtherListingsMappingGeneralIdMode() ==
            self::OTHER_LISTINGS_MAPPING_GENERAL_ID_MODE_CUSTOM_ATTRIBUTE;
    }

    // ---------------------------------------

    /**
     * @return bool
     */
    public function isOtherListingsMappingSkuModeNone()
    {
        return $this->getOtherListingsMappingSkuMode() == self::OTHER_LISTINGS_MAPPING_SKU_MODE_NONE;
    }

    /**
     * @return bool
     */
    public function isOtherListingsMappingSkuModeDefault()
    {
        return $this->getOtherListingsMappingSkuMode() == self::OTHER_LISTINGS_MAPPING_SKU_MODE_DEFAULT;
    }

    /**
     * @return bool
     */
    public function isOtherListingsMappingSkuModeCustomAttribute()
    {
        return $this->getOtherListingsMappingSkuMode() == self::OTHER_LISTINGS_MAPPING_SKU_MODE_CUSTOM_ATTRIBUTE;
    }

    /**
     * @return bool
     */
    public function isOtherListingsMappingSkuModeProductId()
    {
        return $this->getOtherListingsMappingSkuMode() == self::OTHER_LISTINGS_MAPPING_SKU_MODE_PRODUCT_ID;
    }

    // ---------------------------------------

    /**
     * @return bool
     */
    public function isOtherListingsMappingTitleModeNone()
    {
        return $this->getOtherListingsMappingTitleMode() == self::OTHER_LISTINGS_MAPPING_TITLE_MODE_NONE;
    }

    /**
     * @return bool
     */
    public function isOtherListingsMappingTitleModeDefault()
    {
        return $this->getOtherListingsMappingTitleMode() == self::OTHER_LISTINGS_MAPPING_TITLE_MODE_DEFAULT;
    }

    /**
     * @return bool
     */
    public function isOtherListingsMappingTitleModeCustomAttribute()
    {
        return $this->getOtherListingsMappingTitleMode() == self::OTHER_LISTINGS_MAPPING_TITLE_MODE_CUSTOM_ATTRIBUTE;
    }

    //########################################

    /**
     * @return bool
     */
    public function isMagentoOrdersListingsModeEnabled()
    {
        $setting = $this->getSetting('magento_orders_settings', array('listing', 'mode'), 1);

        return $setting == 1;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersListingsStoreCustom()
    {
        $setting = $this->getSetting(
            'magento_orders_settings',
            array('listing', 'store_mode'),
            self::MAGENTO_ORDERS_LISTINGS_STORE_MODE_DEFAULT
        );

        return $setting == self::MAGENTO_ORDERS_LISTINGS_STORE_MODE_CUSTOM;
    }

    /**
     * @return int
     */
    public function getMagentoOrdersListingsStoreId()
    {
        $setting = $this->getSetting('magento_orders_settings', array('listing', 'store_id'), 0);

        return (int)$setting;
    }

    // ---------------------------------------

    /**
     * @return bool
     */
    public function isMagentoOrdersListingsOtherModeEnabled()
    {
        $setting = $this->getSetting('magento_orders_settings', array('listing_other', 'mode'), 1);

        return $setting == 1;
    }

    /**
     * @return int
     */
    public function getMagentoOrdersListingsOtherStoreId()
    {
        $setting = $this->getSetting('magento_orders_settings', array('listing_other', 'store_id'), 0);

        return (int)$setting;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersListingsOtherProductImportEnabled()
    {
        $setting = $this->getSetting(
            'magento_orders_settings',
            array('listing_other', 'product_mode'),
            self::MAGENTO_ORDERS_LISTINGS_OTHER_PRODUCT_MODE_IMPORT
        );

        return $setting == self::MAGENTO_ORDERS_LISTINGS_OTHER_PRODUCT_MODE_IMPORT;
    }

    /**
     * @return int
     */
    public function getMagentoOrdersListingsOtherProductTaxClassId()
    {
        $setting = $this->getSetting(
            'magento_orders_settings',
            array('listing_other', 'product_tax_class_id'),
            Ess_M2ePro_Model_Magento_Product::TAX_CLASS_ID_NONE
        );

        return (int)$setting;
    }

    // ---------------------------------------

    public function getMagentoOrdersNumberSource()
    {
        $setting = $this->getSetting(
            'magento_orders_settings',
            array('number', 'source'),
            self::MAGENTO_ORDERS_NUMBER_SOURCE_MAGENTO
        );

        return $setting;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersNumberSourceMagento()
    {
        return $this->getMagentoOrdersNumberSource() == self::MAGENTO_ORDERS_NUMBER_SOURCE_MAGENTO;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersNumberSourceChannel()
    {
        return $this->getMagentoOrdersNumberSource() == self::MAGENTO_ORDERS_NUMBER_SOURCE_CHANNEL;
    }

    // ---------------------------------------

    /**
     * @return string
     */
    public function getMagentoOrdersNumberRegularPrefix()
    {
        $settings = $this->getSetting('magento_orders_settings', array('number', 'prefix'));

        return isset($settings['prefix']) ? $settings['prefix'] : '';
    }

    /**
     * @return string
     */
    public function getMagentoOrdersNumberAfnPrefix()
    {
        $settings = $this->getSetting('magento_orders_settings', array('number', 'prefix'));

        return isset($settings['afn-prefix']) ? $settings['afn-prefix'] : '';
    }

    /**
     * @return string
     */
    public function getMagentoOrdersNumberPrimePrefix()
    {
        $settings = $this->getSetting('magento_orders_settings', array('number', 'prefix'));

        return isset($settings['prime-prefix']) ? $settings['prime-prefix'] : '';
    }

    /**
     * @return string
     */
    public function getMagentoOrdersNumberB2bPrefix()
    {
        $settings = $this->getSetting('magento_orders_settings', array('number', 'prefix'));

        return isset($settings['b2b-prefix']) ? $settings['b2b-prefix'] : '';
    }

    // ---------------------------------------

    /**
     * @return bool
     */
    public function isMagentoOrdersNumberApplyToAmazonOrderEnable()
    {
        $setting = $this->getSetting('magento_orders_settings', array('number', 'apply_to_amazon'), 0);

        return $setting == 1;
    }

    // ---------------------------------------

    /**
     * @return int
     */
    public function getQtyReservationDays()
    {
        $setting = $this->getSetting('magento_orders_settings', array('qty_reservation', 'days'), 1);

        return (int)$setting;
    }

    // ---------------------------------------

    /**
     * @return bool
     */
    public function isRefundEnabled()
    {
        $setting = $this->getSetting('magento_orders_settings', array('refund_and_cancellation', 'refund_mode'));

        return (bool)$setting;
    }

    // ---------------------------------------

    /**
     * @return bool
     */
    public function isMagentoOrdersTaxModeNone()
    {
        $setting = $this->getSetting('magento_orders_settings', array('tax', 'mode'));

        return $setting == self::MAGENTO_ORDERS_TAX_MODE_NONE;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersTaxModeChannel()
    {
        $setting = $this->getSetting('magento_orders_settings', array('tax', 'mode'));

        return $setting == self::MAGENTO_ORDERS_TAX_MODE_CHANNEL;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersTaxModeMagento()
    {
        $setting = $this->getSetting('magento_orders_settings', array('tax', 'mode'));

        return $setting == self::MAGENTO_ORDERS_TAX_MODE_MAGENTO;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersTaxModeMixed()
    {
        $setting = $this->getSetting('magento_orders_settings', array('tax', 'mode'));

        return $setting == self::MAGENTO_ORDERS_TAX_MODE_MIXED;
    }

    // ---------------------------------------

    /**
     * @return bool
     */
    public function isAmazonCollectsEnabled()
    {
        return (bool)$this->getSetting('magento_orders_settings', array('tax', 'amazon_collects'), 0);
    }

    /**
     * @return array
     */
    public function getExcludedStates()
    {
        return $this->getSetting('magento_orders_settings', array('tax', 'excluded_states'), array());
    }

    public function isAmazonCollectsTaxForUKShipmentAvailable()
    {
        $setting = $this->getSetting('magento_orders_settings', array('tax', 'amazon_collect_for_uk'), 0);

        return $setting == self::SKIP_TAX_FOR_UK_SHIPMENT;
    }

    public function isAmazonCollectsTaxForUKShipmentWithCertainPrice()
    {
        $setting = $this->getSetting('magento_orders_settings', array('tax', 'amazon_collect_for_uk'), 0);

        return $setting == self::SKIP_TAX_FOR_UK_SHIPMENT_WITH_CERTAIN_PRICE;
    }

    /**
     * @return array
     */
    public function getExcludedCountries()
    {
        return $this->getSetting(
            'magento_orders_settings',
            array('tax', 'excluded_countries'),
            array()
        );
    }

    /**
     * @return bool
     */
    public function isAmazonCollectsTaxForEEAShipmentFromUkSite()
    {
        $setting = $this->getSetting(
            'magento_orders_settings',
            array('tax', 'amazon_collect_for_eea'),
            0
        );

        return $setting == 1;
    }

    // ---------------------------------------

    /**
     * @return bool
     */
    public function isMagentoOrdersCustomerGuest()
    {
        $setting = $this->getSetting(
            'magento_orders_settings',
            array('customer', 'mode'),
            self::MAGENTO_ORDERS_CUSTOMER_MODE_GUEST
        );

        return $setting == self::MAGENTO_ORDERS_CUSTOMER_MODE_GUEST;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersCustomerPredefined()
    {
        $setting = $this->getSetting(
            'magento_orders_settings',
            array('customer', 'mode'),
            self::MAGENTO_ORDERS_CUSTOMER_MODE_GUEST
        );

        return $setting == self::MAGENTO_ORDERS_CUSTOMER_MODE_PREDEFINED;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersCustomerNew()
    {
        $setting = $this->getSetting(
            'magento_orders_settings',
            array('customer', 'mode'),
            self::MAGENTO_ORDERS_CUSTOMER_MODE_GUEST
        );

        return $setting == self::MAGENTO_ORDERS_CUSTOMER_MODE_NEW;
    }

    /**
     * @return int
     */
    public function getMagentoOrdersCustomerId()
    {
        $setting = $this->getSetting('magento_orders_settings', array('customer', 'id'));

        return (int)$setting;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersCustomerNewSubscribed()
    {
        $setting = $this->getSetting('magento_orders_settings', array('customer', 'subscription_mode'), 0);

        return $setting == 1;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersCustomerNewNotifyWhenCreated()
    {
        $setting = $this->getSetting('magento_orders_settings', array('customer', 'notifications', 'customer_created'));

        return (bool)$setting;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersCustomerNewNotifyWhenOrderCreated()
    {
        $setting = $this->getSetting('magento_orders_settings', array('customer', 'notifications', 'order_created'));

        return (bool)$setting;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersCustomerNewNotifyWhenInvoiceCreated()
    {
        $setting = $this->getSetting('magento_orders_settings', array('customer', 'notifications', 'invoice_created'));

        return (bool)$setting;
    }

    /**
     * @return int
     */
    public function getMagentoOrdersCustomerNewWebsiteId()
    {
        $setting = $this->getSetting('magento_orders_settings', array('customer', 'website_id'));

        return (int)$setting;
    }

    /**
     * @return int
     */
    public function getMagentoOrdersCustomerNewGroupId()
    {
        $setting = $this->getSetting('magento_orders_settings', array('customer', 'group_id'));

        return (int)$setting;
    }

    // ---------------------------------------

    /**
     * @return bool
     */
    public function useMagentoOrdersShippingAddressAsBillingAlways()
    {
        return $this->getBillingAddressMode() == self::USE_SHIPPING_ADDRESS_AS_BILLING_ALWAYS;
    }

    /**
     * @return bool
     */
    public function useMagentoOrdersShippingAddressAsBillingIfSameCustomerAndRecipient()
    {
        return $this->getBillingAddressMode() == self::USE_SHIPPING_ADDRESS_AS_BILLING_IF_SAME_CUSTOMER_AND_RECIPIENT;
    }

    protected function getBillingAddressMode()
    {
        return $this->getSetting(
            'magento_orders_settings',
            array('customer', 'billing_address_mode'),
            self::USE_SHIPPING_ADDRESS_AS_BILLING_IF_SAME_CUSTOMER_AND_RECIPIENT
        );
    }

    // ---------------------------------------

    /**
     * @return bool
     */
    public function isMagentoOrdersStatusMappingDefault()
    {
        $setting = $this->getSetting(
            'magento_orders_settings',
            array('status_mapping', 'mode'),
            self::MAGENTO_ORDERS_STATUS_MAPPING_MODE_DEFAULT
        );

        return $setting == self::MAGENTO_ORDERS_STATUS_MAPPING_MODE_DEFAULT;
    }

    public function getMagentoOrdersStatusProcessing()
    {
        if ($this->isMagentoOrdersStatusMappingDefault()) {
            return self::MAGENTO_ORDERS_STATUS_MAPPING_PROCESSING;
        }

        return $this->getSetting('magento_orders_settings', array('status_mapping', 'processing'));
    }

    public function getMagentoOrdersStatusShipped()
    {
        if ($this->isMagentoOrdersStatusMappingDefault()) {
            return self::MAGENTO_ORDERS_STATUS_MAPPING_SHIPPED;
        }

        return $this->getSetting('magento_orders_settings', array('status_mapping', 'shipped'));
    }

    // ---------------------------------------

    public function isMagentoOrdersFbaModeEnabled()
    {
        $setting = $this->getSetting('magento_orders_settings', array('fba', 'mode'), 1);

        return $setting == 1;
    }

    public function isMagentoOrdersFbaStoreModeEnabled()
    {
        $setting = $this->getSetting('magento_orders_settings', array('fba', 'store_mode'), 0);

        return $setting == 1;
    }

    /**
     * @return int
     */
    public function getMagentoOrdersFbaStoreId()
    {
        $setting = $this->getSetting('magento_orders_settings', array('fba', 'store_id'), 0);

        return (int)$setting;
    }

    public function isMagentoOrdersFbaStockEnabled()
    {
        $setting = $this->getSetting('magento_orders_settings', array('fba', 'stock_mode'));

        return $setting == 1;
    }

    //########################################

    /**
     * @return int
     */
    public function getAutoInvoicing()
    {
        return (int)$this->getData('auto_invoicing');
    }

    /**
     * @return bool
     */
    public function isAutoInvoicingDisabled()
    {
        return $this->getAutoInvoicing() == self::AUTO_INVOICING_DISABLED;
    }

    /**
     * @return bool
     */
    public function isVatCalculationServiceEnabled()
    {
        return $this->getAutoInvoicing() == self::AUTO_INVOICING_VAT_CALCULATION_SERVICE;
    }

    /**
     * @return bool
     */
    public function isUploadMagentoInvoices()
    {
        return $this->getAutoInvoicing() == self::AUTO_INVOICING_UPLOAD_MAGENTO_INVOICES;
    }

    /**
     * @return int
     */
    public function getInvoiceGeneration()
    {
        return (int)$this->getData('invoice_generation');
    }

    /**
     * @return bool
     */
    public function isInvoiceGenerationByAmazon()
    {
        return $this->getInvoiceGeneration() == self::INVOICE_GENERATION_BY_AMAZON;
    }

    /**
     * @return bool
     */
    public function isInvoiceGenerationByExtension()
    {
        return $this->getInvoiceGeneration() == self::INVOICE_GENERATION_BY_EXTENSION;
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersInvoiceEnabled()
    {
        return (bool)$this->getData('create_magento_invoice');
    }

    /**
     * @return bool
     */
    public function isMagentoOrdersShipmentEnabled()
    {
        return (bool)$this->getData('create_magento_shipment');
    }

    /**
     * @return bool
     */
    public function isRemoteFulfillmentProgramEnabled()
    {
        return (bool)$this->getData('remote_fulfillment_program_mode');
    }

    //########################################

    public function save()
    {
        Mage::helper('M2ePro/Data_Cache_Permanent')->removeTagValues('account');

        return parent::save();
    }

    public function delete()
    {
        Mage::helper('M2ePro/Data_Cache_Permanent')->removeTagValues('account');

        return parent::delete();
    }

    //########################################
}
