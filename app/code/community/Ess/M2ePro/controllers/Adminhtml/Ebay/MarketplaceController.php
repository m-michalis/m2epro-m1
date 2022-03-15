<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

class Ess_M2ePro_Adminhtml_Ebay_MarketplaceController extends Ess_M2ePro_Controller_Adminhtml_Ebay_MainController
{
    //########################################

    protected function _initAction()
    {
        $this->loadLayout()
            ->_title(Mage::helper('M2ePro')->__('Marketplaces'));

        $this->getLayout()->getBlock('head')
            ->addJs('M2ePro/Plugin/ProgressBar.js')
            ->addJs('M2ePro/SynchProgress.js')
            ->addJs('M2ePro/Plugin/AreaWrapper.js')
            ->addJs('M2ePro/Marketplace.js')
            ->addJs('M2ePro/Ebay/Marketplace/SynchProgress.js')
            ->addCss('M2ePro/css/Plugin/ProgressBar.css')
            ->addCss('M2ePro/css/Plugin/AreaWrapper.css');

        $this->setPageHelpLink(null, null, "x/z1oJAg");

        return $this;
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed(
            Ess_M2ePro_Helper_View_Ebay::MENU_ROOT_NODE_NICK . '/configuration'
        );
    }

    //########################################

    public function indexAction()
    {
        $this->_initAction()
            ->_addContent(
                $this->getLayout()->createBlock(
                    'M2ePro/adminhtml_ebay_configuration', '',
                    array('active_tab' => Ess_M2ePro_Block_Adminhtml_Ebay_Configuration_Tabs::TAB_ID_MARKETPLACE)
                )
            )->renderLayout();
    }

    public function saveAction()
    {
        $marketplaces = Mage::getModel('M2ePro/Marketplace')->getCollection();

        foreach ($marketplaces as $marketplace) {
            $newStatus = $this->getRequest()->getParam('status_'.$marketplace->getId());

            if ($newStatus === null) {
                continue;
            }

            if ($marketplace->getStatus() == $newStatus) {
                continue;
            }

            $marketplace->setData('status', $newStatus)->save();
        }
    }

    //########################################

    public function runSynchNowAction()
    {
        // @codingStandardsIgnoreLine
        session_write_close();

        /** @var Ess_M2ePro_Model_Marketplace $marketplace */
        $marketplace = Mage::helper('M2ePro/Component')->getUnknownObject(
            'Marketplace',
            (int)$this->getRequest()->getParam('marketplace_id')
        );

        $synchronization = Mage::getModel('M2ePro/Ebay_Marketplace_Synchronization');
        $synchronization->setMarketplace($marketplace);

        if ($synchronization->isLocked()) {
            $synchronization->getlog()->addMessage(
                Mage::helper('M2ePro')->__(
                    'Marketplaces cannot be updated now. '
                    . 'Please wait until another marketplace synchronization is completed, then try again.'
                ),
                Ess_M2ePro_Model_Log_Abstract::TYPE_ERROR
            );

            return $this->getResponse()->setBody(Mage::helper('M2ePro')->jsonEncode(array('result' => 'error')));
        }

        try {
            $synchronization->process();
        } catch (Exception $e) {
            $synchronization->getlog()->addMessageFromException($e);

            $synchronization->getLockItemManager()->remove();

            Mage::getModel('M2ePro/Servicing_Dispatcher')->processTask(
                Mage::getModel('M2ePro/Servicing_Task_License')->getPublicNick()
            );

            return $this->getResponse()->setBody(Mage::helper('M2ePro')->jsonEncode(array('result' => 'error')));
        }

        return $this->getResponse()->setBody(Mage::helper('M2ePro')->jsonEncode(array('result' => 'success')));
    }

    public function synchGetExecutingInfoAction()
    {
        $synchronization = Mage::getModel('M2ePro/Ebay_Marketplace_Synchronization');
        if (!$synchronization->isLocked()) {
            return $this->getResponse()->setBody(Mage::helper('M2ePro')->jsonEncode(array('mode' => 'inactive')));
        }

        $contentData = $synchronization->getLockItemManager()->getContentData();
        $progressData = $contentData[Ess_M2ePro_Model_Lock_Item_Progress::CONTENT_DATA_KEY];

        $response = array('mode' => 'executing');
        if (!empty($progressData)) {
            $response['title'] = 'Marketplace Synchronization';
            $response['percents'] = $progressData[key($progressData)]['percentage'];
            $response['status'] = key($progressData);
        }

        return $this->getResponse()->setBody(Mage::helper('M2ePro')->jsonEncode($response));
    }

    public function isExistDeletedCategoriesAction()
    {
        if (Mage::helper('M2ePro/Component_Ebay_Category_Ebay')->isExistDeletedCategories()) {
            return $this->getResponse()->setBody('1');
        }

        return $this->getResponse()->setBody('0');
    }

    //########################################
}
