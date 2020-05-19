<?php

namespace Itella\Shipping\Block;

class Manifest extends \Magento\Framework\View\Element\Template {

    protected $Itella_carrier;
    protected $_orderCollectionFactory;

    public function __construct(\Magento\Framework\View\Element\Template\Context $context,
            \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
            \Itella\Shipping\Model\Carrier $Itella_carrier) {
        $this->Itella_carrier = $Itella_carrier;
        parent::__construct($context);
        $this->_orderCollectionFactory = $orderCollectionFactory;
    }

    public function getOrderAddress($order) {
        $parcel_terminal_address = '';
        $shippingAddress = $order->getShippingAddress();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $country = $objectManager->create('\Magento\Directory\Model\Country')->load($shippingAddress->getCountryId());
        $street = $shippingAddress->getStreet();
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
        return $client_address . $parcel_terminal_address;
    }

    public function getOrders() {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $collection = $this->_orderCollectionFactory->create()->addFieldToFilter('shipping_method', array('like' => 'itella_%'))->addFieldToFilter('state', array('neq' => 'canceled'))->load();
        //$this->orderCollectionFactory->addFieldToFilter($field, $condition);
        return $collection;
    }

}
