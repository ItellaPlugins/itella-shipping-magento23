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
use Magento\Framework\FlagManager;
use Magento\Framework\Lock\LockManagerInterface;

/**
 * Class CallItella
 */
class CallItella extends \Magento\Framework\App\Action\Action
{
  /**
   * Minimum time (in seconds) between two courier calls
   */
  const CALL_COOLDOWN = 60;

  const FLAG_LAST_CALL = 'itella_courier_call_last_time';

  const LOCK_NAME = 'itella_courier_call';

  protected $Itella_carrier;

  protected $flagManager;

  protected $lockManager;

  public function __construct(
    Context $context,
    Filter $filter,
    CollectionFactory $collectionFactory,
    OrderManagementInterface $orderManagement,
    \Itella\Shipping\Model\Carrier $Itella_carrier,
    FlagManager $flagManager,
    LockManagerInterface $lockManager
  ) {
    $this->Itella_carrier = $Itella_carrier;
    $this->flagManager = $flagManager;
    $this->lockManager = $lockManager;
    parent::__construct($context);
  }

  public function execute()
  {
    $wait_time = $this->reserveCall();
    if ($wait_time > 0) {
      $this->messageManager->addError(
        __('Smartposti courier call has already been started. Please wait %1 seconds before calling again.', $wait_time)
      );
      $this->_redirect($this->_redirect->getRefererUrl());
      return;
    }

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

  /**
   * Checks whether courier call was started within cooldown period and, if not, marks a new call as started.
   * Check and mark are done under a lock, so parallel requests (e.g. double-click) can not both pass.
   *
   * @return int Seconds left to wait. 0 if call is allowed.
   */
  protected function reserveCall()
  {
    $locked = $this->lockManager->lock(self::LOCK_NAME, 10);
    if (!$locked) {
      return self::CALL_COOLDOWN;
    }

    try {
      $now = time();
      $last_call = (int) $this->flagManager->getFlagData(self::FLAG_LAST_CALL);
      $passed = $now - $last_call;
      if ($last_call > 0 && $passed >= 0 && $passed < self::CALL_COOLDOWN) {
        return self::CALL_COOLDOWN - $passed;
      }
      $this->flagManager->saveFlag(self::FLAG_LAST_CALL, $now);
      return 0;
    } finally {
      $this->lockManager->unlock(self::LOCK_NAME);
    }
  }

}
