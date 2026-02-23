<?php
namespace Itella\Shipping\Controller\Adminhtml\Itellamanifest;


class Index extends  \Magento\Backend\App\Action
{

  protected $resultPageFactory;

  public function __construct(
              \Magento\Backend\App\Action\Context $context,
              \Magento\Framework\View\Result\PageFactory $resultPageFactory
  ){
       parent::__construct($context);
      $this->resultPageFactory = $resultPageFactory;
  }

  public function execute()
  {
      $requiredApiVersion = '2.5.0';
      $apiVersion = null;
      try {
          $apiVersion = \Composer\InstalledVersions::getPrettyVersion('mijora/itella-api');
      } catch (\OutOfBoundsException $e) {
          $apiVersion = null;
      }

      if ($apiVersion === null || version_compare($apiVersion, $requiredApiVersion, '<')) {
          $message = sprintf(__('Installed Smartposti API library ( %1$s ) is outdated. Minimum required version is %2$s. If the library is not updated, errors may occur when registering shipments or downloading shipping labels.'), 'mijora/itella-api', $requiredApiVersion);

          $this->messageManager->addErrorMessage($message);
      }

      $resultPage = $this->resultPageFactory->create();
      $resultPage->getConfig()->getTitle()->prepend(__('Smartposti manifest'));

      return $resultPage;
  }
}