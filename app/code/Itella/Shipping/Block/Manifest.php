<?php
namespace Itella\Shipping\Block;

class Manifest extends \Magento\Framework\View\Element\Template
{

  protected $_orderCollectionFactory;
	public function __construct(\Magento\Framework\View\Element\Template\Context $context,
  \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory )
	{
		parent::__construct($context);
    $this->_orderCollectionFactory = $orderCollectionFactory;
	}
  
    public function getOrders()
    {
      $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
      $collection = $this->_orderCollectionFactory->create()->addFieldToFilter('shipping_method', array('like' => 'itella_%'))->addFieldToFilter('state',array('neq' => 'canceled'))->load();
      //$this->orderCollectionFactory->addFieldToFilter($field, $condition);
      return $collection;
    }
}