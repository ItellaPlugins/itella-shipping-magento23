<?php

namespace Itella\Shipping\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;

class SaveServicesAjax extends \Magento\Sales\Controller\Adminhtml\Order
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */

    public function execute()
    {
        $order = $this->_initOrder();
        if ($order) {
            
        
        
            $params = $this->getRequest()->getParams();
            $services = array();
            if (isset($params['itella_services'])){
                $services = $params['itella_services'];
            }
            $resultJson = $this->resultJsonFactory->create();
            $order->setItellaServices(json_encode($services));
            $order->save();
            return $resultJson->setData([
                'messages' => 'Successfully.' ,
                'error' => false
            ]);
        }
        return false;
    }
}