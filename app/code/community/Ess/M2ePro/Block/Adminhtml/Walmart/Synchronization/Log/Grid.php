<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

class Ess_M2ePro_Block_Adminhtml_Walmart_Synchronization_Log_Grid
    extends Ess_M2ePro_Block_Adminhtml_Synchronization_Log_Grid
{
    //########################################

    public function getComponentMode()
    {
        return Ess_M2ePro_Helper_Component_Walmart::NICK;
    }

    //########################################

    protected function getActionTitles()
    {
        $allTitles = Mage::helper('M2ePro/Module_Log')->getActionsTitlesByClass('Synchronization_Log');

        $excludeTitles = array(
            Ess_M2ePro_Model_Synchronization_Log::TASK_REPRICING => '',
        );

        return array_diff_key($allTitles, $excludeTitles);
    }

    //########################################
}
