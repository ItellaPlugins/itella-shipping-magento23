<?php

/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
// @codingStandardsIgnoreFile

namespace Itella\Shipping\Model;

use Magento\Framework\Module\Dir;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Tracking\Result as TrackingResult;
use Mijora\Itella\Locations\PickupPoints;
use Mijora\Itella\Shipment\Shipment;
use Mijora\Itella\Shipment\GoodsItem;
use Mijora\Itella\Shipment\Party;
use Mijora\Itella\Pdf\Label;
use Mijora\Itella\CallCourier;
use Mijora\Itella\ItellaException;
use Mijora\Itella\Shipment\AdditionalService;
use Mijora\Itella\Helper;
use Mijora\Itella\Pdf\Manifest;

/**
 * Itella shipping implementation
 *
 * @author Magento Core Team <core@magentocommerce.com>
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Carrier extends AbstractCarrierOnline implements \Magento\Shipping\Model\Carrier\CarrierInterface {

    /**
     * Code of the carrier
     *
     * @var string
     */
    const CODE = 'itella';

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Rate request data
     *
     * @var RateRequest|null
     */
    protected $_request = null;

    /**
     * Rate result data
     *
     * @var Result|TrackingResult
     */
    protected $_result = null;

    /**
     * Path to locations
     *
     * @var string
     */
    protected $_locationFileLt;
    protected $_locationFileEe;
    protected $_locationFileLv;
    protected $_locationFileFi;
    protected $isTest = 0;

    /**
     * Errors
     *
     * @var string
     */
    protected $globalErrors = [];

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    protected $configReader;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $_productCollectionFactory;

    /**
     * @inheritdoc
     */
    protected $_debugReplacePrivateDataKeys = [
        'Account', 'Password'
    ];
    protected $configWriter;
    protected $cacheManagerFactory;

    /**
     * Session instance reference
     * 
     */
    protected $_checkoutSession;
    protected $scopeConfig;
    protected $_orderCollectionFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param Security $xmlSecurity
     * @param \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory
     * @param \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Directory\Helper\Data $directoryData
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Module\Dir\Reader $configReader
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
            \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
            \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
            \Psr\Log\LoggerInterface $logger,
            Security $xmlSecurity,
            \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
            \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
            \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
            \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
            \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
            \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
            \Magento\Directory\Model\RegionFactory $regionFactory,
            \Magento\Directory\Model\CountryFactory $countryFactory,
            \Magento\Directory\Model\CurrencyFactory $currencyFactory,
            \Magento\Directory\Helper\Data $directoryData,
            \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
            \Magento\Store\Model\StoreManagerInterface $storeManager,
            \Magento\Framework\Module\Dir\Reader $configReader,
            \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
            \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
            \Magento\Checkout\Model\Session $checkoutSession,
            \Magento\Framework\App\Cache\ManagerFactory $cacheManagerFactory,
            \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
            array $data = []
    ) {
        $this->_checkoutSession = $checkoutSession;

        $this->_storeManager = $storeManager;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->configReader = $configReader;
        $this->configWriter = $configWriter;
        $this->cacheManagerFactory = $cacheManagerFactory;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        //$this->scopeConfig = $scopeConfig
        parent::__construct(
                $scopeConfig,
                $rateErrorFactory,
                $logger,
                $xmlSecurity,
                $xmlElFactory,
                $rateFactory,
                $rateMethodFactory,
                $trackFactory,
                $trackErrorFactory,
                $trackStatusFactory,
                $regionFactory,
                $countryFactory,
                $currencyFactory,
                $directoryData,
                $stockRegistry,
                $data
        );
        $this->scopeConfig = $scopeConfig;
        $this->isTest = $this->getConfigData('is_test');

        $this->_locationFileLt = $configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Itella_Shipping') . '/location_lt.json';
        $this->_locationFileLv = $configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Itella_Shipping') . '/location_lv.json';
        $this->_locationFileEe = $configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Itella_Shipping') . '/location_ee.json';
        $this->_locationFileFi = $configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Itella_Shipping') . '/location_fi.json';
        if (!$this->getConfigData('location_update') || ($this->getConfigData('location_update') + 3600 * 24) < time() || !file_exists($this->_locationFileLt) || !file_exists($this->_locationFileLv) || !file_exists($this->_locationFileEe) || !file_exists($this->_locationFileFi)
        ) {
            $itellaPickupPointsObj = new \Mijora\Itella\Locations\PickupPoints('https://locationservice.posti.com/api/2/location');

            $itellaLoc = $itellaPickupPointsObj->getLocationsByCountry('LT');
            $itellaPickupPointsObj->saveLocationsToJSONFile($this->_locationFileLt, json_encode($itellaLoc));

            $itellaLoc = $itellaPickupPointsObj->getLocationsByCountry('LV');
            $itellaPickupPointsObj->saveLocationsToJSONFile($this->_locationFileLv, json_encode($itellaLoc));

            $itellaLoc = $itellaPickupPointsObj->getLocationsByCountry('EE');
            $itellaPickupPointsObj->saveLocationsToJSONFile($this->_locationFileEe, json_encode($itellaLoc));

            $itellaLoc = $itellaPickupPointsObj->getLocationsByCountry('FI');
            $itellaPickupPointsObj->saveLocationsToJSONFile($this->_locationFileFi, json_encode($itellaLoc));

            $this->configWriter->save("carriers/itella/location_update", time());
        }
    }

    private function clearConfigCache() {
        $cacheManager = $this->cacheManagerFactory->create();
        $cacheManager->clean(['config']);
    }

    private function getConfig($config_path) {
        return $this->scopeConfig->getValue(
                        $config_path,
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     * @return Result|bool|null
     */
    public function collectRates(RateRequest $request) {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $max_weight = $this->getConfigData('max_package_weight');
        if ($max_weight && $request->getPackageWeight() > $max_weight) {
            return false;
        }

        $result = $this->_rateFactory->create();
        $packageValue = $request->getBaseCurrency()->convert($request->getPackageValueWithDiscount(), $request->getPackageCurrency());
        $packageValue = $request->getPackageValueWithDiscount();
        $this->_updateFreeMethodQuote($request);
        $free = ($this->getConfigData('free_shipping_enable') && $packageValue >= $this->getConfigData('free_shipping_subtotal'));
        $allowedMethods = explode(',', $this->getConfigData('allowed_methods'));
        foreach ($allowedMethods as $allowedMethod) {
            $method = $this->_rateMethodFactory->create();

            $method->setCarrier('itella');
            $method->setCarrierTitle($this->getConfigData('title'));

            $method->setMethod($allowedMethod);
            $method->setMethodTitle($this->getCode('method', $allowedMethod));
            $amount = $this->getConfigData('price');

            $country_id = $this->_checkoutSession->getQuote()
                    ->getShippingAddress()
                    ->getCountryId();

            if ($allowedMethod == "COURIER") {
                switch ($country_id) {
                    case 'LV':
                        $amount = $this->getConfigData('priceLV_C');
                        break;
                    case 'EE':
                        $amount = $this->getConfigData('priceEE_C');
                        break;
                    case 'FI':
                        $amount = $this->getConfigData('priceFI_C');
                        break;
                    default:
                        $amount = $this->getConfigData('price');
                }
            }
            if ($allowedMethod == "PARCEL_TERMINAL") {
                switch ($country_id) {
                    case 'LV':
                        $amount = $this->getConfigData('priceLV_pt');
                        break;
                    case 'EE':
                        $amount = $this->getConfigData('priceEE_pt');
                        break;
                    case 'FI':
                        $amount = $this->getConfigData('priceFI_pt');
                        break;
                    default:
                        $amount = $this->getConfigData('price2');
                }
            }
            if ($free)
                $amount = 0;

            $method->setPrice($amount);
            $method->setCost($amount);

            $result->append($method);
        }
        return $result;
    }

    /**
     * Get result of request
     *
     * @return Result|TrackingResult
     */
    public function getResult() {
        if (!$this->_result) {
            $this->_result = $this->_rateFactory->create();
        }
        return $this->_result;
    }

    /**
     * Set free method request
     *
     * @param string $freeMethod
     * @return void
     */
    protected function _setFreeMethodRequest($freeMethod) {
        $this->_rawRequest->setFreeMethodRequest(true);
        $freeWeight = $this->getTotalNumOfBoxes($this->_rawRequest->getFreeMethodWeight());
        $this->_rawRequest->setWeight($freeWeight);
        $this->_rawRequest->setService($freeMethod);
    }

    /**
     * Get configuration data of carrier
     *
     * @param string $type
     * @param string $code
     * @return array|false
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getCode($type, $code = '') {

        $codes = [
            'method' => [
                'COURIER' => __('Smartpost courier'),
                'PARCEL_TERMINAL' => __('Smartpost pickup point'),
            ],
            'country' => [
                'EE' => __('Estonia'),
                'LV' => __('Latvia'),
                'LT' => __('Lithuania'),
                'FI' => __('Finland')
            ],
            'tracking' => [
            ],
            'terminal' => [],
        ];

        $locations = json_decode(file_get_contents($this->_locationFileLt), true);


        $codes['terminal'] = $locations;

        if (!isset($codes[$type])) {
            return false;
        } elseif ('' === $code) {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            return false;
        } else {
            return $codes[$type][$code];
        }
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods() {
        $allowed = explode(',', $this->getConfigData('allowed_methods'));
        $arr = [];
        foreach ($allowed as $k) {
            $arr[$k] = $this->getCode('method', $k);
        }

        return $arr;
    }

    public function call_Itella() {
        $sendTo = $this->getConfigData('courier_email');
        $result = new \Magento\Framework\DataObject();
        $manifest = new \Mijora\Itella\Pdf\Manifest();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $items = array();

        if (isset($_POST['date'])) {
            $date = $_POST['date'];
            $orders = $this->_orderCollectionFactory->create()
                    ->addAttributeToSelect('*')
                    ->addFieldToFilter('manifest_generation_date', ['like' => $date . '%']);
            foreach ($orders as $order) {
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
                $storeManager = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
                $shippingAddress = $order->getShippingAddress();
                $country = $objectManager->create('\Magento\Directory\Model\Country')->load($shippingAddress->getCountryId());
                $street = $shippingAddress->getStreet();
                $parcel_terminal_address = '';
                if (strtoupper($order->getData('shipping_method')) == strtoupper('Itella_PARCEL_TERMINAL')) {
                    $shippingAddress = $order->getShippingAddress();
                    $terminal_id = $shippingAddress->getItellaParcelTerminal();
                    $parcel_terminal = $this->_getItellaTerminal($terminal_id, $shippingAddress->getCountryId());
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
        } else {
            return false;
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
            'name_lastname_signature' => __('name, lastname, signature')
        );

        $name = $this->getConfigData('cod_company');
        $phone = $this->getConfigData('company_phone');
        $street = $this->getConfigData('company_address');
        $postcode = $this->getConfigData('company_postcode');
        $city = $this->getConfigData('company_city');
        $country = $this->getConfigData('company_countrycode');

        $manifest_string = $manifest
                ->setStrings($translation)
                ->setSenderName($name)
                ->setSenderAddress($street)
                ->setSenderPostCode($postcode)
                ->setSenderCity($city)
                ->setSenderCountry($country)
                ->addItem($items)
                ->setToString(true)
                ->setBase64(true)
                ->printManifest('manifest.pdf')
        ;
        try {
            $caller = new \Mijora\Itella\CallCourier($sendTo);
            $result = $caller
                    ->setSenderEmail($this->getConfigData('company_email'))
                    ->setSubject('E-com order booking')
                    ->setPickUpAddress(array(
                        'sender' => $name,
                        'address_1' => $street,
                        'postcode' => $postcode,
                        'city' => $city,
                        'country' => $country,
                        'pickup_time' => '8:00 - 17:00',
                        'contact_phone' => $phone,
                    ))
                    ->setAttachment($manifest_string, true)
                    ->setItems($items)
                    ->callCourier();
            if ($result) {
                return true;
            }
        } catch (Exception $e) {
            $this->globalErrors[] = 'Failed to send email, reason: ' . $e->getMessage();
        }
        return false;
    }

    protected function _getItellaSender(\Magento\Framework\DataObject $request) {
        try {
            $contract = '';
            if ($this->_getItellaShippingType($request) == Shipment::PRODUCT_PICKUP) {
                $contract = $this->getConfigData('itella_contract_2711');
            }
            if ($this->_getItellaShippingType($request) == Shipment::PRODUCT_COURIER) {
                $contract = $this->getConfigData('itella_contract_2317');
            }
            $sender = new \Mijora\Itella\Shipment\Party(\Mijora\Itella\Shipment\Party::ROLE_SENDER);
            $sender
                    ->setContract($contract)  
                    ->setName1($this->getConfigData('cod_company'))
                    ->setStreet1($this->getConfigData('company_address'))
                    ->setPostCode($this->getConfigData('company_postcode'))
                    ->setCity($this->getConfigData('company_city'))
                    ->setCountryCode($this->getConfigData('company_countrycode'))
                    ->setContactMobile($this->getConfigData('company_phone'))
                    ->setContactEmail($this->getConfigData('company_email'));
        } catch (ItellaException $e) {
            $this->globalErrors[] = 'Sender error: ' . $e->getMessage();
        }
        return $sender;
    }

    protected function _getItellaReceiver(\Magento\Framework\DataObject $request) {
        try {
            $send_method = trim(str_ireplace('Itella_', '', $request->getShippingMethod()));

            $receiver = new \Mijora\Itella\Shipment\Party(\Mijora\Itella\Shipment\Party::ROLE_RECEIVER);
            $receiver
                    ->setName1($request->getRecipientContactPersonName())
                    ->setStreet1($request->getRecipientAddressStreet1())
                    ->setPostCode($request->getRecipientAddressPostalCode())
                    ->setCity($request->getRecipientAddressCity())
                    ->setCountryCode($request->getRecipientAddressCountryCode())
                    ->setContactName($request->getRecipientContactPersonName())
                    ->setContactMobile($request->getRecipientContactPhoneNumber())
                    ->setContactEmail($request->getOrderShipment()->getOrder()->getCustomerEmail());
        } catch (Exception $e) {
            $this->globalErrors[] = 'Receiver error: ' . $e->getMessage();
        }
        return $receiver;
    }

    public function _getItellaTerminal($id, $countryCode) {
        $locationFile = $this->configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Itella_Shipping') . '/location_' . strtolower($countryCode) . '.json';
        $terminals = array();
        if (file_exists($locationFile)) {
            $terminals = json_decode(file_get_contents($locationFile), true);
        } else {
            $itellaPickupPointsObj = new \Mijora\Itella\Locations\PickupPoints('https://locationservice.posti.com/api/2/location');
            $terminals = $itellaPickupPointsObj->getLocationsByCountry($countryCode);
        }
        if (count($terminals) > 0) {
            foreach ($terminals as $terminal) {
                if ($terminal['pupCode'] == $id) {
                    return $terminal;
                }
            }
        }
        $this->globalErrors[] = "Required terminal not found. Terminal ID: " . $id;
        return false;
    }
    
    static public function _getItellaTerminals($countryCode) {
        $locationFile = Dir::MODULE_ETC_DIR. 'Itella_Shipping' . '/location_' . strtolower($countryCode) . '.json';
        $terminals = array();
        if (file_exists($locationFile)) {
            $terminals = json_decode(file_get_contents($locationFile), true);
        } else {
            $itellaPickupPointsObj = new \Mijora\Itella\Locations\PickupPoints('https://locationservice.posti.com/api/2/location');
            $terminals = $itellaPickupPointsObj->getLocationsByCountry($countryCode);
        }
        return $terminals;
    }

    protected function _getItellaServices(\Magento\Framework\DataObject $request) {
        /*
          Must be set manualy
          3101 - Cash On Delivery (only by credit card). Requires array with this information:
          amount => amount to be payed in EUR,
          account => bank account (IBAN),
          codbic => bank BIC,
          reference => COD Reference, can be used Helper::generateCODReference($id) where $id can be Order ID.
          3104 - Fragile
          3166 - Call before Delivery
          3174 - Oversized
          Will be set automatically
          3102 - Multi Parcel, will be set automatically if Shipment has more than 1 and up to 10 GoodsItem. Requires array with this information:
          count => Total of registered GoodsItem.
         */
        $services = array();
        $send_method = trim(str_ireplace('Itella_', '', $request->getShippingMethod()));
        if ($send_method == "COURIER") {
            try {
                $itemsShipment = $request->getPackageItems();


                $order_services = $request->getOrderShipment()->getOrder()->getItellaServices();
                if ($order_services == null){
                    $order_services = array('services'=> array(), 'parcel_count' => '');
                } else {
                    $order_services = json_decode($order_services, true);
                }
                $multi_parcel_count = $order_services['parcel_count'];
                $order_services = $order_services['services'];

                if ($this->_isCod($request) || in_array(3101, $order_services)) {
                    $service_cod = new AdditionalService(
                            AdditionalService::COD,
                            array(
                        'amount' => round($request->getOrderShipment()->getOrder()->getGrandTotal(), 2),
                        'codbic' => $this->getConfigData('cod_company'),
                        'account' => $this->getConfigData('cod_bank_account'),
                        'reference' => Helper::generateCODReference($request->getOrderShipment()->getOrder()->getId())
                            )
                    );
                    $services[] = $service_cod;
                }
                if (in_array(3104, $order_services)) {
                    $service_fragile = new AdditionalService(AdditionalService::FRAGILE);
                    $services[] = $service_fragile;
                }
                if (in_array(3166, $order_services)) {
                    $service = new AdditionalService(AdditionalService::CALL_BEFORE_DELIVERY);
                    $services[] = $service;
                }
                if (in_array(3174, $order_services)) {
                    $service = new AdditionalService(AdditionalService::OVERSIZED);
                    $services[] = $service;
                }
            } catch (Exception $e) {
                $this->globalErrors[] = 'Services error: ' . $e->getMessage();
            }
        }
        return $services;
    }

    protected function _getItellaItems(\Magento\Framework\DataObject $request) {

        $items = array();
        try {
            $itemsShipment = $request->getPackageItems();
            $send_method = trim(str_ireplace('Itella_', '', $request->getShippingMethod()));
            $total_weight = 0;
            foreach ($itemsShipment as $itemShipment) {
                $order_item = new \Magento\Framework\DataObject();
                $order_item->setData($itemShipment);
                $total_weight += $order_item->getWeight() * $order_item->getQty();
            }
            if ($send_method == "COURIER") {
                $order_services = $request->getOrderShipment()->getOrder()->getItellaServices();
                if ($order_services == null){
                    $order_services = array('services'=> array(), 'parcel_count' => '');
                } else {
                    $order_services = json_decode($order_services, true);
                }
                $multi_parcel_count = $order_services['parcel_count'];
                $order_services = $order_services['services'];
                if (in_array(3102, $order_services) && $multi_parcel_count > 1 && $multi_parcel_count <=10) {
                    for ($i=1;$i<=$multi_parcel_count;$i++){
                        $item = new \Mijora\Itella\Shipment\GoodsItem();
                        $item->setGrossWeight(round($total_weight/$multi_parcel_count,3));
                        $items[] = $item;
                    }
                } else {
                    $item = new \Mijora\Itella\Shipment\GoodsItem();
                    $item->setGrossWeight($total_weight);
                    $items[] = $item;
                }
            } else {
                $item = new \Mijora\Itella\Shipment\GoodsItem();
                $item->setGrossWeight($total_weight);
                $items[] = $item;
            }
        } catch (Exception $e) {
            $this->globalErrors[] = 'Items error: ' . $e->getMessage();
        }
        return $items;
    }

    protected function _isCod(\Magento\Framework\DataObject $request) {
        $payment_method = $request->getOrderShipment()->getOrder()->getPayment()->getMethodInstance()->getCode();
        if (stripos($payment_method, 'cashondelivery') !== false || stripos($payment_method, 'cod') !== false) {
            return true;
        }
        return false;
    }

    protected function _getItellaShippingType(\Magento\Framework\DataObject $request) {
        $send_method = trim(str_ireplace('Itella_', '', $request->getShippingMethod()));
        if ($send_method == "PARCEL_TERMINAL") {
            return Shipment::PRODUCT_PICKUP;
        }
        if ($send_method == "COURIER") {
            return Shipment::PRODUCT_COURIER;
        }
        return false;
    }

    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
     *
     * @param \Magento\Framework\DataObject $request
     * @return \Magento\Framework\DataObject
     * @throws \Exception
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request) {
        $tracking_number = false;
        try {
            $barcodes = array();
            $this->_prepareShipmentRequest($request);
            $result = new \Magento\Framework\DataObject();
            $sender = $this->_getItellaSender($request);
            $receiver = $this->_getItellaReceiver($request);
            $items = $this->_getItellaItems($request);
            $services = $this->_getItellaServices($request);
            
            if (!empty($this->globalErrors)) {
                $error_msg = 'Error: Order '.$request->getOrderShipment()->getOrder()->getIncrementId().' has errors';
                if ( is_array($this->globalErrors) ) {
                    $error_msg .= ':<br/>' . implode('.<br/>', $this->globalErrors);
                }
                throw new \Exception($error_msg . '.');
            }

            if ($this->_getItellaShippingType($request) == Shipment::PRODUCT_PICKUP) {
                $terminal_id = $request->getOrderShipment()->getOrder()->getShippingAddress()->getItellaParcelTerminal();
                $terminal = str_pad($terminal_id, 9, "0", STR_PAD_LEFT);
                $shipment = new \Mijora\Itella\Shipment\Shipment($this->getConfigData('user_2711'), $this->getConfigData('password_2711'));
                $shipment
                        ->setProductCode(Shipment::PRODUCT_PICKUP)
                        ->setPickupPoint($terminal);
            }
            if ($this->_getItellaShippingType($request) == Shipment::PRODUCT_COURIER) {
                $shipment = new \Mijora\Itella\Shipment\Shipment($this->getConfigData('user_2317'), $this->getConfigData('password_2317'));
                $shipment->setProductCode(Shipment::PRODUCT_COURIER);
            }
            $shipment
                    ->setShipmentNumber($request->getOrderShipment()->getOrder()->getId()) // Shipment/waybill identifier
                    ->setSenderParty($sender) // previously created Sender object
                    ->setReceiverParty($receiver) // previously created Receiver object
                    ->addAdditionalServices($services)
                    ->addGoodsItems($items); // array of previously created GoodsItem objects, can also be just GoodsItem onject

            $tracking_number = $shipment->registerShipment();
        } catch (Exception $e) {
            $this->globalErrors[] = $e->getMessage();
        }

        if (empty($this->globalErrors)) {
            //var_dump($items); exit;
            //echo $shipment->getXML()->asXML(); exit;
            $documentDateTime = $shipment->getDocumentDateTime();
            $sequence = $shipment->getSequence();
        } else {
            $result->setErrors(implode('<br/>', $this->globalErrors));
        }


        if ($result->hasErrors()) {
            return $result;
        } else {
            if ($tracking_number) {
                $label = $shipment->downloadLabels($tracking_number);
                $result->setShippingLabelContent(base64_decode($label));

                $result->setTrackingNumber(array($tracking_number));
                //var_dump($result); die;
                return $result;
            }
            $result->setErrors(__('No saved barcodes received'));
            return $result;
        }
    }

    /**
     * @param array|object $trackingIds
     * @return string
     */
    private function getTrackingNumber($trackingIds) {
        return is_array($trackingIds) ? array_map(
                        function($val) {
                    return $val->TrackingNumber;
                },
                        $trackingIds
                ) : $trackingIds->TrackingNumber;
    }

    /**
     * For multi package shipments. Delete requested shipments if the current shipment
     * request is failed
     *
     * @param array $data
     * @return bool
     */
    public function rollBack($data) {
        
    }

    /**
     * Return delivery confirmation types of carrier
     *
     * @param \Magento\Framework\DataObject|null $params
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDeliveryConfirmationTypes(\Magento\Framework\DataObject $params = null) {
        return $this->getCode('delivery_confirmation_types');
    }

    /**
     * Recursive replace sensitive fields in debug data by the mask
     * @param string $data
     * @return string
     */
    protected function filterDebugData($data) {
        foreach (array_keys($data) as $key) {
            if (is_array($data[$key])) {
                $data[$key] = $this->filterDebugData($data[$key]);
            } elseif (in_array($key, $this->_debugReplacePrivateDataKeys)) {
                $data[$key] = self::DEBUG_KEYS_MASK;
            }
        }
        return $data;
    }

    /**
     * Append error message to rate result instance
     * @param string $trackingValue
     * @param string $errorMessage
     * @return void
     */
    private function appendTrackingError($trackingValue, $errorMessage) {
        $error = $this->_trackErrorFactory->create();
        $error->setCarrier(self::CODE);
        $error->setCarrierTitle($this->getConfigData('title'));
        $error->setTracking($trackingValue);
        $error->setErrorMessage($errorMessage);
        $result = $this->getResult();
        $result->append($error);
    }

}
