<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

use Ess_M2ePro_Helper_Component_Amazon as ComponentAmazon;

class Ess_M2ePro_Adminhtml_Amazon_Listing_OtherController
    extends Ess_M2ePro_Controller_Adminhtml_Amazon_MainController
{
    //########################################

    protected function _initAction()
    {
        $this->loadLayout()
            ->_title(Mage::helper('M2ePro')->__('Manage Listings'))
            ->_title(Mage::helper('M2ePro')->__('Unmanaged Listings'));

        $this->getLayout()->getBlock('head')
            ->addJs('M2ePro/Plugin/ProgressBar.js')
            ->addCss('M2ePro/css/Plugin/ProgressBar.css')
            ->addJs('M2ePro/Plugin/AreaWrapper.js')
            ->addCss('M2ePro/css/Plugin/AreaWrapper.css')
            ->addJs('M2ePro/Grid.js')
            ->addJs('M2ePro/Listing/Other/Grid.js')
            ->addJs('M2ePro/Amazon/Listing/Other/Grid.js')
            ->addJs('M2ePro/Amazon/Listing/AfnQty.js')
            ->addJs('M2ePro/Amazon/Listing/RepricingPrice.js')
            ->addJs('M2ePro/Amazon/Listing/Other/Grid.js')
            ->addJs('M2ePro/Action.js')
            ->addJs('M2ePro/Listing/Moving.js')
            ->addJs('M2ePro/Listing/Mapping.js')

            ->addJs('M2ePro/Listing/Other/AutoMapping.js')
            ->addJs('M2ePro/Listing/Other/Removing.js')
            ->addJs('M2ePro/Listing/Other/Unmapping.js');

        $this->_initPopUp();

        $this->setPageHelpLink(null, null, "x/IOMVAg");

        return $this;
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed(
            Ess_M2ePro_Helper_View_Amazon::MENU_ROOT_NODE_NICK . '/listings'
        );
    }

    //########################################

    public function indexAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $this->_redirect('*/adminhtml_amazon_listing_other/index');
        }

        /** @var $block Ess_M2ePro_Block_Adminhtml_Amazon_Listing_Other */
        $block = $this->loadLayout()->getLayout()->createBlock('M2ePro/adminhtml_amazon_listing_other');
        $block->enableAmazonTab();

        $this->getResponse()->setBody($block->getAmazonTabHtml());
    }

    public function gridAction()
    {
        $response = $this->loadLayout()->getLayout()
            ->createBlock('M2ePro/adminhtml_amazon_listing_other_view_grid')->toHtml();
        $this->getResponse()->setBody($response);
    }

    //########################################

    public function viewAction()
    {
        /** @var $block Ess_M2ePro_Block_Adminhtml_Amazon_Listing_Other_View */
        $block = $this->getLayout()->createBlock('M2ePro/adminhtml_amazon_listing_other_view');
        $this->_initAction()->_addContent($block)->renderLayout();
    }

    //########################################

    public function removingAction()
    {
        $productIds = $this->getRequest()->getParam('product_ids');

        if (!$productIds) {
            return $this->getResponse()->setBody('0');
        }

        $productArray = explode(',', $productIds);

        if (empty($productArray)) {
            return $this->getResponse()->setBody('0');
        }

        foreach ($productArray as $productId) {
            /** @var $listingOther Ess_M2ePro_Model_Listing_Other */
            $listingOther = Mage::helper('M2ePro/Component')->getComponentObject(
                Ess_M2ePro_Helper_Component_Amazon::NICK,
                'Listing_Other',
                $productId
            );

            if ($listingOther->getProductId() !== null) {
                $listingOther->unmapProduct();
            }

            $listingOther->deleteInstance();
        }

        return $this->getResponse()->setBody('1');
    }

    //########################################

    public function moveToListingAction()
    {
        $sessionHelper = Mage::helper('M2ePro/Data_Session');
        $sessionKey = ComponentAmazon::NICK . '_' . Ess_M2ePro_Helper_View::MOVING_LISTING_OTHER_SELECTED_SESSION_KEY;
        $selectedProducts = $sessionHelper->getValue($sessionKey);

        /** @var Ess_M2ePro_Model_Listing $listingInstance */
        $listingInstance = Mage::helper('M2ePro/Component_Amazon')->getCachedObject(
            'Listing',
            (int)$this->getRequest()->getParam('listingId')
        );

        $errorsCount = 0;
        foreach ($selectedProducts as $otherListingProduct) {

            /** @var Ess_M2ePro_Model_Listing_Other $listingOther */
            $listingOther = Mage::helper('M2ePro/Component_Amazon')->getObject(
                'Listing_Other',
                $otherListingProduct
            );

            $listingProduct = $listingInstance->getChildObject()->addProductFromOther(
                $listingOther,
                Ess_M2ePro_Helper_Data::INITIATOR_USER
            );

            if (!($listingProduct instanceof Ess_M2ePro_Model_Listing_Product)) {
                $errorsCount++;
                continue;
            }

            $listingOther->moveToListingSucceed();
        }

        $sessionHelper->removeValue($sessionKey);

        $result = array('result' => true);

        if ($errorsCount) {
            if (count($selectedProducts) == $errorsCount) {
                $result['result'] = false;
                $result['message'] = array(
                    'text' => Mage::helper('M2ePro')->__(
                        'Products were not moved because they already exist in the selected Listing.'
                    ),
                    'type' => 'error'
                );
            } else {
                $result['message'] = array(
                    'text' => Mage::helper('M2ePro')->__(
                        'Some products were not moved because they already exist in the selected Listing.'
                    ),
                    'type' => 'warning'
                );
            }
        } else {
            $result['message'] = array(
                'text' => Mage::helper('M2ePro')->__('Product(s) was Moved.'),
                'type' => 'success'
            );
        }

        return $this->_addJsonContent($result);
    }

    //########################################

    public function resetAction()
    {
        Mage::getResourceModel('M2ePro/Amazon_Listing_Other')->resetEntities();

        $this->_getSession()->addSuccess(
            Mage::helper('M2ePro')->__('Amazon Unmanaged Listings were reset.')
        );

        $this->_redirect(
            '*/adminhtml_amazon_listing/index',
            array(
                'tab' => Ess_M2ePro_Block_Adminhtml_Amazon_ManageListings::TAB_ID_LISTING_OTHER
            )
        );
    }

    //########################################
}
