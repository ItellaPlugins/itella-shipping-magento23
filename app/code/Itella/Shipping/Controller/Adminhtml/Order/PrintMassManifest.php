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
use Mijora\Itella\Pdf\Manifest;

/**
 * Class MassManifest
 */
class PrintMassManifest extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction {

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;
    protected $Itella_carrier;
    public $labelsContent = array();

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param OrderManagementInterface $orderManagement
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(Context $context, Filter $filter, CollectionFactory $collectionFactory, OrderManagementInterface $orderManagement, \Itella\Shipping\Model\Carrier $Itella_carrier) {

        $this->collectionFactory = $collectionFactory;
        $this->orderManagement = $orderManagement;
        $this->Itella_carrier = $Itella_carrier;
        parent::__construct($context, $filter);
    }

    public function isItellaMethod($order) {
        $_ItellaMethods = array(
            'itella_parcel_terminal',
            'itella_courier'
        );
        $order_shipping_method = strtolower($order->getData('shipping_method'));

        return in_array($order_shipping_method, $_ItellaMethods);
    }

    private function _collectPostData($post_key = null) {
        return $this->getRequest()->getPost($post_key);
    }

    private function _fillDataBase(AbstractCollection $collection) {
        $pack_data = array();
        $model = $this->_objectManager->create('Magento\Sales\Model\Order');
        $unique = array();
        foreach ($collection->getItems() as $order) {
            if (!$order->getEntityId()) {
                continue;
            }
            if (in_array($order->getEntityId(), $unique))
                continue;
            $unique[] = $order->getEntityId();
            $pack_no = array();

            if (!$this->isItellaMethod($order)) {
                $text = 'Warning: Order ' . $order->getData('increment_id') . ' not Smartpost shipping method.';
                $this->messageManager->addError($text);
                continue;
            }
            if (!$order->getShippingAddress()) { //Is set Shipping adress?
                $items = $order->getAllVisibleItems();
                foreach ($items as $item) {
                    $ordered_items['sku'][] = $item->getSku();
                    $ordered_items['type'][] = $item->getProductType();
                }
                $text = 'Warning: Order ' . $order->getData('increment_id') . ' not have Shipping Address.';
                $this->messageManager->addError($text);
                continue;
            }
            $pack_data[] = $order;
        }

        return $pack_data;
    }

    public function massAction(AbstractCollection $collection) {
        $pack_data = array();
        $success_files = array();
        $order_ids = $this->_collectPostData('order_ids');
        $model = $this->_objectManager->create('Magento\Sales\Model\Order');
        $pack_data = $this->_fillDataBase($collection); //Send data to server and get packs number's

        if (!count($pack_data) || $pack_data === false) {
            $text = 'Warning: No orders selected.';
            $this->messageManager->addWarning($text);
            $this->_redirect($this->_redirect->getRefererUrl());
            return;
        }
        $generation_date = date('Y-m-d H:i:s');

        $count = 0;
        $last_order = false;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order_ids = array();
        $items = array();
        foreach ($pack_data as $order) {
            $track_numer = '';
            foreach ($order->getShipmentsCollection() as $shipment) {
                foreach ($shipment->getAllTracks() as $tracknum) {
                    $track_numer .= $tracknum->getNumber() . ' ';
                }
            }
            if ($track_numer == '') {
                $text = 'Warning: Order ' . $order->getData('increment_id') . ' has no tracking number. Will not be included in manifest.';
                $this->messageManager->addWarning($text);
                continue;
            }
            $order->setManifestGenerationDate($generation_date);
            $order->save();
            $count++;
            $storeManager = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
            $shippingAddress = $order->getShippingAddress();
            $country = $objectManager->create('\Magento\Directory\Model\Country')->load($shippingAddress->getCountryId());
            $street = $shippingAddress->getStreet();
            $parcel_terminal_address = '';
            if (strtoupper($order->getData('shipping_method')) == strtoupper('Itella_PARCEL_TERMINAL')) {
                $terminal_id = $shippingAddress->getItellaParcelTerminal();
                $parcel_terminal = $this->Itella_carrier->_getItellaTerminal($terminal_id, $shippingAddress->getCountryId());
                if ($parcel_terminal) {
                    $parcel_terminal_address = $parcel_terminal['publicName'] . ', ' . $parcel_terminal['address']['streetName'] . ' ' . $parcel_terminal['address']['streetNumber'] . ', ' . $parcel_terminal['address']['postalCode'] . ', ' . $parcel_terminal['address']['postalCodeName'];
                    
                }
            }
            $client_address = $shippingAddress->getName() . ', ' . $street[0] . ', ' . $shippingAddress->getPostcode() . ', ' . $shippingAddress->getCity() . ' ' . $country->getName();
            if ($parcel_terminal_address != '')
                $client_address = '';

            $item = array(
                'track_num' => $track_numer,
                'weight' => $order->getWeight(),
                'delivery_address' => $client_address . $parcel_terminal_address,
            );
            $items[] = $item;
        }
        if (count($items) === 0){
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            return $resultRedirect;
        }
        
        $translation = array(
            'sender_address' => __('Sender address'),
            'nr' => __('No.'),
            'track_num' => __('Tracking number'),
            'date' => __('Date'),
            'amount' => __('Amount'),
            'weight' => __('Weight').'(kg)',
            'delivery_address' => __('Delivery address'),
            'courier' => __('Courier'),
            'sender' => __('Sender'),
            'name_lastname_signature' => __('name, lastname, signature'),
        );

        /*
          $storeInformation = $objectManager->create('\Magento\Framework\App\Config\ScopeConfigInterface');
          $name             = $storeInformation->getValue('general/store_information/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
          $street           = $storeInformation->getValue('general/store_information/street_line1', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
          $postcode         = $storeInformation->getValue('general/store_information/postcode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
          $city             = $storeInformation->getValue('general/store_information/city', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
          $country          = $storeInformation->getValue('general/store_information/country_id', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
         */
        $name = $this->Itella_carrier->getConfigData('cod_company');
        $phone = $this->Itella_carrier->getConfigData('company_phone');
        $street = $this->Itella_carrier->getConfigData('company_address');
        $postcode = $this->Itella_carrier->getConfigData('company_postcode');
        $city = $this->Itella_carrier->getConfigData('company_city');
        $country = $this->Itella_carrier->getConfigData('company_countrycode');

        $manifest = new \Mijora\Itella\Pdf\Manifest();


        $manifest_pdf = $manifest
                ->setStrings($translation)
                ->setSenderName($name)
                ->setSenderAddress($street)
                ->setSenderPostCode($postcode)
                ->setSenderCity($city)
                ->setSenderCountry($country)
                ->addItem($items)
                ->setToString(true)
                ->printManifest('manifest.pdf');

        header("Content-Disposition: attachment; filename=\"Itella_manifest.pdf\"");
        header("Content-Type: application/pdf");
        header("Content-Transfer-Encoding: binary");
        // disable caching on client and proxies, if the download content vary
        header("Expires: 0");
        header("Cache-Control: no-cache, must-revalidate");
        header("Pragma: no-cache");
        echo $manifest_pdf;
        return;
    }

}
