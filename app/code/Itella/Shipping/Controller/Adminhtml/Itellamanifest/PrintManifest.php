<?php
namespace Itella\Shipping\Controller\Adminhtml\Itellamanifest;

use \Magento\Framework\App\Action\Action;
use \Magento\Framework\App\Action\Context;
use \Magento\Framework\View\Result\PageFactory;
use \Itella\Shipping\Controller\Adminhtml\Order\PrintMassManifest;

class PrintManifest extends  \Magento\Framework\App\Action\Action
{

  protected $resultPageFactory;
  protected $massManifest;
  protected $_orderCollectionFactory;

  public function __construct(
              \Magento\Backend\App\Action\Context $context,
              \Magento\Framework\View\Result\PageFactory $resultPageFactory,
              \Itella\Shipping\Controller\Adminhtml\Order\PrintMassManifest $massManifest,
              \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
              
  ){
    
      $this->resultPageFactory = $resultPageFactory;
      $this->massManifest = $massManifest;
      $this->_orderCollectionFactory = $orderCollectionFactory;
       parent::__construct($context);
  }

  public function execute()
  {
      $order_ids = $this->getRequest()->getPost('order_ids');
      $collection = $this->_orderCollectionFactory->create()->addFieldToFilter('entity_id',array('in' => $order_ids))->load();
      
      return $this->massManifest->massAction($collection);
  }
}