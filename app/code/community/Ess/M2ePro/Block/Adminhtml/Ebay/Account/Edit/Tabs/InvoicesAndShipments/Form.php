<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

class Ess_M2ePro_Block_Adminhtml_Ebay_Account_Edit_Tabs_InvoicesAndShipments_Form extends
    Mage_Adminhtml_Block_Widget_Form
{
    //########################################

    protected function _prepareForm()
    {
        $formData = $this->getFormData();

        $form = new Ess_M2ePro_Block_Adminhtml_Magento_Form_Element_Form(
            array(
                'id'      => 'edit_form',
                'action'  => '#',
                'method'  => 'post',
                'enctype' => 'multipart/form-data'
            )
        );

        $fieldset = $form->addFieldset(
            'invoices',
            array(
                'legend'      => Mage::helper('M2ePro')->__('Invoices'),
                'collapsable' => false
            )
        );

        $fieldset->addField(
            'create_magento_invoice',
            'select',
            array(
                'label'   => Mage::helper('M2ePro')->__('Magento Invoice Creation') . ':',
                'title'   => Mage::helper('M2ePro')->__('Magento Invoice Creation'),
                'name'    => 'create_magento_invoice',
                'options' => array(
                    0 => Mage::helper('M2ePro')->__('Disabled'),
                    1 => Mage::helper('M2ePro')->__('Enabled'),
                ),
                'tooltip' => Mage::helper('M2ePro')->__(
                    <<<HTML
Enable to automatically create Magento Invoices when payment is completed.
HTML
                )
            )
        );

        $fieldset = $form->addFieldset(
            'shipments',
            array(
                'legend'      => Mage::helper('M2ePro')->__('Shipments'),
                'collapsable' => false
            )
        );

        $fieldset->addField(
            'create_magento_shipment',
            'select',
            array(
                'label'              => Mage::helper('M2ePro')->__('Magento Shipment Creation') . ':',
                'title'              => Mage::helper('M2ePro')->__('Magento Shipment Creation'),
                'name'               => 'create_magento_shipment',
                'options'            => array(
                    0 => Mage::helper('M2ePro')->__('Disabled'),
                    1 => Mage::helper('M2ePro')->__('Enabled'),
                ),
                'tooltip'            => Mage::helper('M2ePro')->__(
                    <<<HTML
Enable to automatically create shipment for the Magento order when the associated order on Channel is shipped.
HTML
                )
            )
        );

        $form->setValues($formData);

        $form->setUseContainer(false);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    //########################################

    protected function _toHtml()
    {
        $helpBlock = $this->getLayout()->createBlock(
            'M2ePro/adminhtml_helpBlock',
            '',
            array(
                'content' => Mage::helper('M2ePro')->__(
                    <<<HTML
    <p>Under this tab, you can set M2E Pro to automatically create invoices and shipments in your Magento.
     To do that, keep Magento <i>Invoice/Shipment Creation</i> options enabled.</p>
HTML
                ),
                'title'   => Mage::helper('M2ePro')->__('Invoices & Shipments')
            )
        );

        return $helpBlock->toHtml() . parent::_toHtml();
    }

    //########################################

    protected function getFormData()
    {
        $formData = Mage::helper('M2ePro/Data_Global')->getValue('temp_data')
            ? Mage::helper('M2ePro/Data_Global')->getValue('temp_data')->toArray()
            : array();

        /** @var Ess_M2ePro_Model_Ebay_Account_Builder $defaults */
        $defaults = Mage::getModel('M2ePro/Ebay_Account_Builder')->getDefaultData();

        return array_merge($defaults, $formData);
    }

    //########################################
}
