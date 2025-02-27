<?php

namespace Itella\Shipping\Controller\Adminhtml\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class CallItella
 */
class CallItella extends \Magento\Framework\App\Action\Action
{
  protected $Itella_carrier;
   
  public function __construct(Context $context, Filter $filter, CollectionFactory $collectionFactory, OrderManagementInterface $orderManagement, \Itella\Shipping\Model\Carrier $Itella_carrier)
  {
    $this->Itella_carrier = $Itella_carrier;
    parent::__construct($context);
  }
  public function execute()
  {
    $result = $this->Itella_carrier->call_Itella();
    if ($result){
      $text = __('Smartposti courier called');
      $this->messageManager->addSuccess($text);
    } else {
      $text = __('Failed to call Smartposti courier');
      $this->messageManager->addWarning($text);
    }
    $this->_redirect($this->_redirect->getRefererUrl());
    return;
   
  }
  
}