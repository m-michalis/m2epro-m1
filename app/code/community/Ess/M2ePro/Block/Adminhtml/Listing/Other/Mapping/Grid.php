<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

class Ess_M2ePro_Block_Adminhtml_Listing_Other_Mapping_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    //########################################

    public function __construct()
    {
        parent::__construct();

        // Initialization block
        // ---------------------------------------
        $this->setId('listingOtherMappingGrid');
        // ---------------------------------------

        // Set default values
        // ---------------------------------------
        $this->setDefaultSort('product_id');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
        // ---------------------------------------
    }

    protected function _prepareCollection()
    {
        /** @var Ess_M2ePro_Model_Account $account */
        $accountId = $this->getRequest()->getParam('account');
        $marketplaceId = $this->getRequest()->getParam('marketplace');

        $storeId = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;
        if ($account = Mage::helper('M2ePro/Component')->getCachedUnknownObject('Account', $accountId)) {
            $storeId = $account->getChildObject()->getRelatedStoreId($marketplaceId);
        }

        /** @var $collection Ess_M2ePro_Model_Resource_Magento_Product_Collection */
        $collection = Mage::getConfig()->getModelInstance(
            'Ess_M2ePro_Model_Resource_Magento_Product_Collection',
            Mage::getModel('catalog/product')->getResource()
        );

        $collection->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('type_id');

        $collection->joinStockItem(
            array(
            'qty' => 'qty',
            'is_in_stock' => 'is_in_stock'
            )
        );

        $collection->addFieldToFilter(
            array(
                array(
                    'attribute' => 'type_id',
                    'in' => Mage::helper('M2ePro/Magento_Product')->getOriginKnownTypes()
                ),
            )
        );

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn(
            'product_id', array(
            'header'       => Mage::helper('M2ePro')->__('Product ID'),
            'align'        => 'right',
            'type'         => 'number',
            'width'        => '100px',
            'index'        => 'entity_id',
            'filter_index' => 'entity_id',
            'frame_callback' => array($this, 'callbackColumnProductId')
            )
        );

        $this->addColumn(
            'title', array(
            'header'       => Mage::helper('M2ePro')->__('Product Title / Product SKU'),
            'align'        => 'left',
            'type'         => 'text',
            'width'        => '200px',
            'index'        => 'name',
            'filter_index' => 'name',
            'frame_callback' => array($this, 'callbackColumnTitle'),
            'filter_condition_callback' => array($this, 'callbackFilterTitle')
            )
        );

        $this->addColumn(
            'type', array(
            'header'    => Mage::helper('M2ePro')->__('Type'),
            'align'     => 'left',
            'width'     => '120px',
            'type'      => 'options',
            'sortable'  => false,
            'index'     => 'type_id',
            'filter_index' => 'type_id',
            'options'   => Mage::helper('M2ePro/Magento_Product')->getTypesOptionArray()
            )
        );

        $this->addColumn(
            'stock_availability', array(
            'header'=> Mage::helper('M2ePro')->__('Stock Availability'),
            'width' => '100px',
            'index' => 'is_in_stock',
            'filter_index' => 'is_in_stock',
            'type'  => 'options',
            'sortable'  => false,
            'options' => array(
                1 => Mage::helper('M2ePro')->__('In Stock'),
                0 => Mage::helper('M2ePro')->__('Out of Stock')
            ),
            'frame_callback' => array($this, 'callbackColumnStockAvailability')
            )
        );

        $this->addColumn(
            'actions', array(
            'header'       => Mage::helper('M2ePro')->__('Actions'),
            'align'        => 'left',
            'type'         => 'text',
            'width'        => '125px',
            'filter'       => false,
            'sortable'     => false,
            'frame_callback' => array($this, 'callbackColumnActions'),
            )
        );

    }

    //########################################

    public function callbackColumnProductId($productId, $product, $column, $isExport)
    {
        $url = $this->getUrl('adminhtml/catalog_product/edit', array('id' => $productId));
        $withoutImageHtml = '<a href="'.$url.'" target="_blank">'.$productId.'</a>&nbsp;';

        $showProductsThumbnails = (bool)(int)Mage::helper('M2ePro/Module')->getConfig()->getGroupValue(
            '/view/', 'show_products_thumbnails'
        );
        if (!$showProductsThumbnails) {
            return $withoutImageHtml;
        }

        /** @var $magentoProduct Ess_M2ePro_Model_Magento_Product */
        $magentoProduct = Mage::getModel('M2ePro/Magento_Product');
        $magentoProduct->setProduct($product);

        $imageResized = $magentoProduct->getThumbnailImage();
        if ($imageResized === null) {
            return $withoutImageHtml;
        }

        $imageHtml = $productId.'<hr /><img style="max-width: 100px; max-height: 100px;" src="'.
            $imageResized->getUrl().'" />';
        $withImageHtml = str_replace('>'.$productId.'<', '>'.$imageHtml.'<', $withoutImageHtml);

        return $withImageHtml;
    }

    public function callbackColumnTitle($value, $row, $column, $isExport)
    {
        $value = '<div style="margin-left: 3px">'.Mage::helper('M2ePro')->escapeHtml($value);

        $tempSku = $row->getData('sku');
        if ($tempSku === null) {
            $tempSku = Mage::getModel('M2ePro/Magento_Product')->setProductId($row->getData('entity_id'))->getSku();
        }

        $value .= '<br/><strong>'.Mage::helper('M2ePro')->__('SKU').':</strong> ';
        $value .= Mage::helper('M2ePro')->escapeHtml($tempSku).'</div>';

        return $value;
    }

    public function callbackColumnType($value, $row, $column, $isExport)
    {
        return '<div style="margin-left: 3px">'.Mage::helper('M2ePro')->escapeHtml($value).'</div>';
    }

    public function callbackColumnStockAvailability($value, $row, $column, $isExport)
    {
        if ((int)$row->getData('is_in_stock') <= 0) {
            return '<span style="color: red;">'.$value.'</span>';
        }

        return $value;
    }

    public function callbackColumnActions($value, $row, $column, $isExport)
    {
        $return = '&nbsp;<a href="javascript:void(0);" ';
        $return .= 'onclick="$(\'mapped_product_id\').setValue(\''.$row->getId().'\'); ';
        $return .= '$(\'sku\').setValue(\'\'); ';
        $return .= '$$(\'.mapping_submit_button\')[0].click(); ">';
        $return .= Mage::helper('M2ePro')->__('Map To This Product');
        $return .= '</a>';
        return $return;
    }

    protected function callbackFilterTitle($collection, $column)
    {
        $value = $column->getFilter()->getValue();

        if ($value == null) {
            return;
        }

        $collection->addFieldToFilter(
            array(
                array('attribute'=>'sku','like'=>'%'.$value.'%'),
                array('attribute'=>'name', 'like'=>'%'.$value.'%')
            )
        );

    }

    //########################################

    protected function _toHtml()
    {
        $javascriptsMain = <<<HTML
<script type="text/javascript">

    $$('#listingOtherMappingGrid div.grid th').each(function(el) {
        el.style.padding = '2px 4px';
    });

    $$('#listingOtherMappingGrid div.grid td').each(function(el) {
        el.style.padding = '2px 4px';
    });

</script>
HTML;

        return parent::_toHtml() . $javascriptsMain;
    }

    //########################################

    public function getGridUrl()
    {
        return $this->getUrl('*/adminhtml_listing_other_mapping/mapGrid', array('_current'=>true));
    }

    public function getRowUrl($row)
    {
        return false;
    }

    //########################################
}
