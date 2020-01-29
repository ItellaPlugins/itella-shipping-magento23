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
use Mijora\Itella\Auth;
use Mijora\Itella\Shipment\Shipment;
use Mijora\Itella\Shipment\GoodsItem;
use Mijora\Itella\Shipment\Party;
use Mijora\Itella\Pdf\Label;

/**
 * Itella shipping implementation
 *
 * @author Magento Core Team <core@magentocommerce.com>
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Carrier extends AbstractCarrierOnline implements \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * Code of the carrier
     *
     * @var string
     */
    const CODE = 'Itella';

    /**
     * Purpose of rate request
     *
     * @var string
     */
    const RATE_REQUEST_GENERAL = 'general';

    /**
     * Purpose of rate request
     *
     * @var string
     */
    const RATE_REQUEST_SMARTPOST = 'SMART_POST';

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Types of rates, order is important
     *
     * @var array
     */
    protected $_ratesOrder = [
        'RATED_ACCOUNT_PACKAGE',
        'PAYOR_ACCOUNT_PACKAGE',
        'RATED_ACCOUNT_SHIPMENT',
        'PAYOR_ACCOUNT_SHIPMENT',
        'RATED_LIST_PACKAGE',
        'PAYOR_LIST_PACKAGE',
        'RATED_LIST_SHIPMENT',
        'PAYOR_LIST_SHIPMENT',
    ];

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
     * Path to wsdl file of rate service
     *
     * @var string
     */
    protected $_rateServiceWsdl;

    /**
     * Path to wsdl file of ship service
     *
     * @var string
     */
    protected $_shipServiceWsdl = null;

    /**
     * Path to wsdl file of track service
     *
     * @var string
     */
    protected $_trackServiceWsdl = null;

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
     * Container types that could be customized for Itella carrier
     *
     * @var string[]
     */
    protected $_customizableContainerTypes = ['YOUR_PACKAGING'];

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

    /**
     * Version of tracking service
     * @var int
     */
    private static $trackServiceVersion = 10;

    /**
     * List of TrackReply errors
     * @var array
     */
    private static $trackingErrors = ['FAILURE', 'ERROR'];



    /**
     * @var \Magento\Framework\Xml\Parser
    */
    private $XMLparser;
    
    protected $configWriter;
    
    protected $cacheManagerFactory;
    /**
     * Session instance reference
     * 
     */
    protected $_checkoutSession;

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
        \Magento\Framework\Xml\Parser $parser,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Cache\ManagerFactory $cacheManagerFactory,
        array $data = []
    ) {
        $this->_checkoutSession = $checkoutSession;

        $this->_storeManager = $storeManager;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->XMLparser = $parser;
        $this->configReader = $configReader;
        $this->configWriter = $configWriter;
        $this->cacheManagerFactory = $cacheManagerFactory;
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
        $this->isTest = $this->getConfigData('is_test');

        $this->_locationFileLt = $configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Itella_Shipping') . '/location_lt.json';
        $this->_locationFileLv = $configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Itella_Shipping') . '/location_lv.json';
        $this->_locationFileEe = $configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Itella_Shipping') . '/location_ee.json';
        $this->_locationFileFi = $configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Itella_Shipping') . '/location_fi.json';
        
        if (!$this->getConfigData('location_update') || ($this->getConfigData('location_update') + 3600 * 24) < time()
          || !file_exists($this->_locationFileLt)
          || !file_exists($this->_locationFileLv)
          || !file_exists($this->_locationFileEe)
          || !file_exists($this->_locationFileFi)
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
            
            $this->configWriter->save("carriers/Itella/location_update", time());
        }
      }
      
      
    private function clearConfigCache() {
        $cacheManager = $this->cacheManagerFactory->create();
        $cacheManager->clean(['config']);
    }



    /**
     * Collect and get rates
     *
     * @param RateRequest $request
     * @return Result|bool|null
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }
        
        $result = $this->_rateFactory->create();
        $packageValue = $request->getBaseCurrency()->convert($request->getPackageValueWithDiscount(), $request->getPackageCurrency());
        $packageValue = $request->getPackageValueWithDiscount(); 
        $this->_updateFreeMethodQuote($request);
        $free = ($this->getConfigData('free_shipping_enable') && $packageValue >= $this->getConfigData('free_shipping_subtotal'));
        $allowedMethods = explode(',', $this->getConfigData('allowed_methods'));
        foreach ($allowedMethods as $allowedMethod){
            $method = $this->_rateMethodFactory->create();
     
            $method->setCarrier('Itella');
            $method->setCarrierTitle($this->getConfigData('title'));
     
            $method->setMethod($allowedMethod);
            $method->setMethodTitle($this->getCode('method', $allowedMethod));
            $amount = $this->getConfigData('price');

            $country_id =  $this->_checkoutSession->getQuote()
            ->getShippingAddress()
            ->getCountryId();
            
            if ($allowedMethod == "COURIER") {
              switch($country_id) {
                case 'LV':
                    $amount = $this->getConfigData('priceLV_C');
                    break;
                case 'EE':
                    $amount = $this->getConfigData('priceEE_C');
                    break;
                default:
                    $amount = $this->getConfigData('price');
              }
            }
            if ($allowedMethod == "PARCEL_TERMINAL") {
              switch($country_id) {
                case 'LV':
                    $amount = $this->getConfigData('priceLV_pt');
                    break;
                case 'EE':
                    $amount = $this->getConfigData('priceEE_pt');
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
     * Prepare and set request to this instance
     *
     * @param RateRequest $request
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function setRequest(RateRequest $request)
    {
        $this->_request = $request;

        $r = new \Magento\Framework\DataObject();

        if ($request->getLimitMethod()) {
            $r->setService($request->getLimitMethod());
        }

        if ($request->getItellaAccount()) {
            $account = $request->getItellaAccount();
        } else {
            $account = $this->getConfigData('account');
        }
        $r->setAccount($account);

        if ($request->getItellaDropoff()) {
            $dropoff = $request->getItellaDropoff();
        } else {
            $dropoff = $this->getConfigData('dropoff');
        }
        $r->setDropoffType($dropoff);

        if ($request->getItellaPackaging()) {
            $packaging = $request->getItellaPackaging();
        } else {
            $packaging = $this->getConfigData('packaging');
        }
        $r->setPackaging($packaging);

        if ($request->getOrigCountry()) {
            $origCountry = $request->getOrigCountry();
        } else {
            $origCountry = $this->_scopeConfig->getValue(
                \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_COUNTRY_ID,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $request->getStoreId()
            );
        }
        $r->setOrigCountry($this->_countryFactory->create()->load($origCountry)->getData('iso2_code'));

        if ($request->getOrigPostcode()) {
            $r->setOrigPostal($request->getOrigPostcode());
        } else {
            $r->setOrigPostal(
                $this->_scopeConfig->getValue(
                    \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_ZIP,
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    $request->getStoreId()
                )
            );
        }

        if ($request->getDestCountryId()) {
            $destCountry = $request->getDestCountryId();
        } else {
            $destCountry = self::USA_COUNTRY_ID;
        }
        $r->setDestCountry($this->_countryFactory->create()->load($destCountry)->getData('iso2_code'));

        if ($request->getDestPostcode()) {
            $r->setDestPostal($request->getDestPostcode());
        } else {
        }

        if ($request->getDestCity()) {
            $r->setDestCity($request->getDestCity());
        }

        $weight = $this->getTotalNumOfBoxes($request->getPackageWeight());
        $r->setWeight($weight);
        if ($request->getFreeMethodWeight() != $request->getPackageWeight()) {
            $r->setFreeMethodWeight($request->getFreeMethodWeight());
        }

        $r->setValue($request->getPackagePhysicalValue());
        $r->setValueWithDiscount($request->getPackageValueWithDiscount());

        $r->setMeterNumber($this->getConfigData('meter_number'));
        $r->setKey($this->getConfigData('key'));
        $r->setPassword($this->getConfigData('password'));

        $r->setIsReturn($request->getIsReturn());

        $r->setBaseSubtotalInclTax($request->getBaseSubtotalInclTax());

        $this->setRawRequest($r);

        return $this;
    }

    /**
     * Get result of request
     *
     * @return Result|TrackingResult
     */
    public function getResult()
    {
        if (!$this->_result) {
            $this->_result = $this->_rateFactory->create();
        }
        return $this->_result;
    }

    /**
     * Get version of rates request
     *
     * @return array
     */
    public function getVersionInfo()
    {
        return ['ServiceId' => 'crs', 'Major' => '10', 'Intermediate' => '0', 'Minor' => '0'];
    }

    /**
     * Set free method request
     *
     * @param string $freeMethod
     * @return void
     */
    protected function _setFreeMethodRequest($freeMethod)
    {
        $this->_rawRequest->setFreeMethodRequest(true);
        $freeWeight = $this->getTotalNumOfBoxes($this->_rawRequest->getFreeMethodWeight());
        $this->_rawRequest->setWeight($freeWeight);
        $this->_rawRequest->setService($freeMethod);
    }

    /**
     * Prepare shipping rate result based on response
     *
     * @param mixed $response
     * @return Result
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _parseXmlResponse($response)
    {
        $costArr = [];
        $priceArr = [];

        if (strlen(trim($response)) > 0) {
            $xml = $this->parseXml($response, 'Magento\Shipping\Model\Simplexml\Element');
            if (is_object($xml)) {
                if (is_object($xml->Error) && is_object($xml->Error->Message)) {
                    $errorTitle = (string)$xml->Error->Message;
                } elseif (is_object($xml->SoftError) && is_object($xml->SoftError->Message)) {
                    $errorTitle = (string)$xml->SoftError->Message;
                } else {
                    $errorTitle = 'Sorry, something went wrong. Please try again or contact us and we\'ll try to help.';
                }

                $allowedMethods = explode(",", $this->getConfigData('allowed_methods'));

                foreach ($xml->Entry as $entry) {
                    if (in_array((string)$entry->Service, $allowedMethods)) {
                        $costArr[(string)$entry->Service] = (string)$entry
                            ->EstimatedCharges
                            ->DiscountedCharges
                            ->NetCharge;
                        $priceArr[(string)$entry->Service] = $this->getMethodPrice(
                            (string)$entry->EstimatedCharges->DiscountedCharges->NetCharge,
                            (string)$entry->Service
                        );
                    }
                }

                asort($priceArr);
            } else {
                $errorTitle = 'Response is in the wrong format.';
            }
        } else {
            $errorTitle = 'For some reason we can\'t retrieve tracking info right now.';
        }

        $result = $this->_rateFactory->create();
        if (empty($priceArr)) {
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier('Itella');
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage($this->getConfigData('specificerrmsg'));
            $result->append($error);
        } else {
            foreach ($priceArr as $method => $price) {
                $rate = $this->_rateMethodFactory->create();
                $rate->setCarrier('Itella');
                $rate->setCarrierTitle($this->getConfigData('title'));
                $rate->setMethod($method);
                $rate->setMethodTitle($this->getCode('method', $method));
                $rate->setCost($costArr[$method]);
                $rate->setPrice($price);
                $result->append($rate);
            }
        }

        return $result;
    }

    /**
     * Get configuration data of carrier
     *
     * @param string $type
     * @param string $code
     * @return array|false
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getCode($type, $code = '')
    {

        $codes = [
            'method' => [
                'COURIER' => __('Courier'),
                'PARCEL_TERMINAL' => __('Parcel terminal'),
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

        $locationsXMLArray = json_decode(file_get_contents($this->_locationFileLt),true);
        $locations = $locationsXMLArray;
        /*
        foreach($locationsXMLArray['LOCATIONS']['_value']['LOCATION'] as $loc_data ){
            $locations[$loc_data['ZIP']] = array(
                'name' => $loc_data['NAME'], 
                'country' => $loc_data['A0_NAME'],
                'x' => $loc_data['X_COORDINATE'],
            );
        }
        */

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
     * Get tracking
     *
     * @param string|string[] $trackings
     * @return Result|null
     */
    public function getTracking($trackings)
    {
        //$this->setTrackingReqeust();

        if (!is_array($trackings)) {
            $trackings = [$trackings];
        }
        $this->_getXMLTracking($trackings);

        return $this->_result;
    }

    /**
     * Set tracking request
     *
     * @return void
     */
    protected function setTrackingReqeust()
    {
        $r = new \Magento\Framework\DataObject();

        $account = $this->getConfigData('account');
        $r->setAccount($account);

        $this->_rawTrackingRequest = $r;
    }

    /**
     * Send request for tracking
     *
     * @param string[] $tracking
     * @return void
     */
    protected function _getXMLTracking($tracking)
    {
        $this->_result = $this->_trackFactory->create();
       

        $url=$this->getConfigData('production_webservices_url').'/epteavitus/events/from/'.date("c", strtotime("-1 week +1 day")).'/for-client-code/'.$this->getConfigData('account');
        $process = curl_init();
        $additionalHeaders = '';
        curl_setopt($process, CURLOPT_URL, $url); 
        curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: application/xml', $additionalHeaders));
        curl_setopt($process, CURLOPT_HEADER, FALSE);
        curl_setopt($process, CURLOPT_USERPWD, $this->getConfigData('account') . ":" . $this->getConfigData('password'));
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        $return = curl_exec($process);
        curl_close($process);
        $return = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $return);
        $xml=simplexml_load_string($return);
        
        $debugData['return'] = $xml;
       
        //$this->_debug($debugData);

        $this->_parseXmlTrackingResponse($tracking, $return);
    }

    /**
     * Parse tracking response
     *
     * @param string $trackingValue
     * @param \stdClass $response
     * @return void
     */
    protected function _parseXmlTrackingResponse($trackings, $response)
    {
        $errorTitle = __('Unable to retrieve tracking');
        $resultArr = [];

        if (strlen(trim($response)) > 0) {
            $response = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);
            $xml = $this->parseXml($response, 'Magento\Shipping\Model\Simplexml\Element');
            if (!is_object($xml)) {
                $errorTitle = __('Response is in the wrong format');
            }
             //$this->_debug($xml);
            if (is_object($xml) && is_object($xml->event)) {
                foreach ($xml->event as $awbinfo) {
                    $awbinfoData = [];
                    $trackNum = isset($awbinfo->packetCode) ? (string)$awbinfo->packetCode : '';
                    if (!in_array($trackNum,$trackings))
                        continue;
                    //$this->_debug($awbinfo);
                    $packageProgress = [];
                    if (isset($resultArr[$trackNum]['progressdetail']))
                        $packageProgress = $resultArr[$trackNum]['progressdetail'];

                    $shipmentEventArray = [];
                    $shipmentEventArray['activity'] = $this->getCode('tracking',(string)$awbinfo->eventCode);
                    $datetime = \DateTime::createFromFormat('U', strtotime($awbinfo->eventDate));
                    $this->_debug(\DateTime::ISO8601);
                    $shipmentEventArray['deliverydate'] = '';//date("Y-m-d", strtotime((string)$awbinfo->eventDate));
                    $shipmentEventArray['deliverytime'] = '';//date("H:i:s", strtotime((string)$awbinfo->eventDate));
                    $shipmentEventArray['deliverylocation'] = $awbinfo->eventSource;
                    $packageProgress[] = $shipmentEventArray;
                        
                    $awbinfoData['progressdetail'] = $packageProgress;
                    
                    $resultArr[$trackNum] = $awbinfoData;
                }
            }
        }

        $result = $this->_trackFactory->create();

        if (!empty($resultArr)) {
            foreach ($resultArr as $trackNum => $data) {
                $tracking = $this->_trackStatusFactory->create();
                $tracking->setCarrier($this->_code);
                $tracking->setCarrierTitle($this->getConfigData('title'));
                $tracking->setTracking($trackNum);
                $tracking->addData($data);
                $result->append($tracking);
            }
        }

        if (!empty($this->_errors) || empty($resultArr)) {
            $resultArr = !empty($this->_errors) ? $this->_errors : $trackings;
            foreach ($resultArr as $trackNum => $err) {
                $error = $this->_trackErrorFactory->create();
                $error->setCarrier($this->_code);
                $error->setCarrierTitle($this->getConfigData('title'));
                $error->setTracking(!empty($this->_errors) ? $trackNum : $err);
                $error->setErrorMessage(!empty($this->_errors) ? $err : $errorTitle);
                $result->append($error);
            }
        }

        $this->_result = $result;
    }

    /**
     * Get tracking response
     *
     * @return string
     */
    public function getResponse()
    {
        $statuses = '';
        if ($this->_result instanceof \Magento\Shipping\Model\Tracking\Result) {
            if ($trackings = $this->_result->getAllTrackings()) {
                foreach ($trackings as $tracking) {
                    if ($data = $tracking->getAllData()) {
                        if (!empty($data['status'])) {
                            $statuses .= __($data['status']) . "\n<br/>";
                        } else {
                            $statuses .= __('Empty response') . "\n<br/>";
                        }
                    }
                }
            }
        }
        if (empty($statuses)) {
            $statuses = __('Empty response');
        }

        return $statuses;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        $allowed = explode(',', $this->getConfigData('allowed_methods'));
        $arr = [];
        foreach ($allowed as $k) {
            $arr[$k] = $this->getCode('method', $k);
        }

        return $arr;
    }

    
  public function call_Itella(){
    return false;
  }
    

    
    protected function _getItellaAuth()
    {
      $auth = new \Mijora\Itella\Auth($this->getConfigData('account'), $this->getConfigData('password'), $this->isTest);
      $current_token = json_decode($this->getConfigData('itella_token'),true);
      if (!isset($current_token['expires']) || $current_token['expires'] <= time()) {
          // Getging new Token
          $new_token_array = $auth->getAuth();
          //$this->setConfigData('itella_token', json_encode($new_token_array, true));
          $this->configWriter->save("carriers/Itella/itella_token", json_encode($new_token_array, true));
          $this->clearConfigCache();
          $auth->setTokenArr($new_token_array);
      } else {
          // Using saved Token
          $auth->setTokenArr($current_token);
      }
      return $auth;
    }
    
    protected function _getItellaSender(\Magento\Framework\DataObject $request)
    {
        $sender = new \Mijora\Itella\Shipment\Party(\Mijora\Itella\Shipment\Party::ROLE_SENDER);
        $sender
          ->setContract($this->getConfigData('contract'))
          ->setName1($this->getConfigData('cod_company'))
          ->setStreet1($this->getConfigData('company_address'))
          ->setPostCode($this->getConfigData('company_postcode'))
          ->setCity($this->getConfigData('company_postcode'))
          ->setCountryCode($this->getConfigData('company_countrycode'));
        return $sender;
    }
    
    protected function _getItellaReceiver(\Magento\Framework\DataObject $request)
    {
        $send_method = trim(str_ireplace('Itella_','',$request->getShippingMethod()));
        
        $receiver = new \Mijora\Itella\Shipment\Party(\Mijora\Itella\Shipment\Party::ROLE_RECEIVER);        
        $receiver
          ->setName1($request->getRecipientContactPersonName())
          ->setStreet1($request->getRecipientAddressStreet1())
          ->setPostCode($request->getRecipientAddressPostalCode())
          ->setCity($request->getRecipientAddressCity())
          ->setCountryCode($request->getRecipientAddressCountryCode())
          ->setContactName($request->getRecipientContactPersonName())
          ->setContactMobile($request->getRecipientContactPhoneNumber());
          //->setContactEmail('testas@testutis.lt');
        if ($send_method == "PARCEL_TERMINAL"){
            $terminal = $this->_getItellaTerminal($request->getOrderShipment()->getOrder()->getShippingAddress()->getItellaParcelTerminal(), $request->getRecipientAddressCountryCode());
            if ($terminal){
                $receiver
                  ->setName1($terminal['publicName'])
                  ->setStreet1($terminal['address']['address'])
                  ->setPostCode($terminal['postalCode']);
            }
        }
        return $receiver;
    }
    
    public function _getItellaTerminal($id, $countryCode){
        $locationFile = $this->configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Itella_Shipping') . '/location_'.strtolower($countryCode).'.json';
        $terminals = array();
        if (file_exists($locationFile)){
            $terminals = json_decode(file_get_contents($locationFile),true);
        } else {
            $itellaPickupPointsObj = new \Mijora\Itella\Locations\PickupPoints('https://locationservice.posti.com/api/2/location');           
            $terminals = $itellaPickupPointsObj->getLocationsByCountry($countryCode);
        }
        if (count($terminals)>0){
            foreach ($terminals as $terminal){
                if ($terminal['id'] == $id){
                    return $terminal;
                }    
            }
        }
        $this->globalErrors[] = "Required terminal not found. Terminal ID: ".$id;
        return false;
    }
    
    protected function _getItellaItems(\Magento\Framework\DataObject $request)
    {
        /*
        Services
        3101 - Cash On Delivery (only by credit card), COD information MUST be set in Shipment object
        3102 - Multi Parcel
        3104 - Fragile
        3166 - Call before Delivery
        3174 - Oversized
        */
        $items = array();
        $services = array();
        $itemsShipment = $request->getPackageItems();
        $send_method = trim(str_ireplace('Itella_','',$request->getShippingMethod()));
        if ($this->_isCod($request)){
            $services[] = 3101;
        }
        if (count($itemsShipment) > 1){
            $services[] = 3102;
        }
        foreach ($itemsShipment as $itemShipment) {
            if ($send_method == "PARCEL_TERMINAL"){
                $item = new \Mijora\Itella\Shipment\GoodsItem(\Mijora\Itella\Shipment\GoodsItem::PRODUCT_PICKUP);           
                $item
                  ->setTrackingNumber($this->_getItellaTrackingNumber());
            } else {
                $item = new \Mijora\Itella\Shipment\GoodsItem(\Mijora\Itella\Shipment\GoodsItem::PRODUCT_COURIER);           
                $item
                  ->addExtraService($services)
                  ->setTrackingNumber($this->_getItellaTrackingNumber());
            }
            $items[] = $item;
        }
        return $items;
    }
    
    protected function _getItellaTrackingNumber(){
        //JJFI 000000 00000010000
        $contract = str_pad($this->getConfigData('contract'), 6, '0', STR_PAD_LEFT);
        $tracking = $this->getConfigData('tracking_start');
        $track_from = str_pad($tracking, 11, '0', STR_PAD_LEFT);
        $next_tracking = (int)$tracking+1;
        //$this->setConfigData('tracking_start', $next_tracking );
        $this->configWriter->save("carriers/Itella/tracking_start", $next_tracking);
        $this->clearConfigCache();
        
        //echo $next_tracking; die;
        return "JJFI".$contract.$track_from;
    }
    
    protected function _isCod(\Magento\Framework\DataObject $request){
        $payment_method = $request->getOrderShipment()->getOrder()->getPayment()->getMethodInstance()->getCode();
        if (stripos($payment_method,'cashondelivery') !== false || stripos($payment_method,'cod') !== false){         
            return true;
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
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        $barcodes = array();
        $this->_prepareShipmentRequest($request);
        $result = new \Magento\Framework\DataObject();
        $auth = $this->_getItellaAuth();
        $sender = $this->_getItellaSender($request);
        $receiver = $this->_getItellaReceiver($request);
        $items = $this->_getItellaItems($request);
        
        $shipment = new \Mijora\Itella\Shipment\Shipment($this->isTest);
        $shipment
          ->setAuth($auth) // previously created Auth object
          ->setSenderId($this->getConfigData('account')) // Itella API user
          ->setReceiverId('ITELLT') // Itella code for Lithuania
          ->setShipmentNumber($request->getOrderShipment()->getOrder()->getId()) // Shipment/waybill identifier
          ->setShipmentDateTime(date('c')) // when shipment is ready for transport. Format must be ISO 8601, e.g. 2019-10-11T10:00:00+03:00
          ->setSenderParty($sender) // previously created Sender object
          ->setReceiverParty($receiver) // previously created Receiver object
          ->addGoodsItem($items); // array of previously created GoodsItem objects, can also be just GoodsItem onject
          
          
        if ($this->_isCod($request)){         
            // needed only if COD extra service is used
            $shipment
              ->setBIC($this->getConfigData('cod_company')) // Bank BIC
              ->setIBAN($this->getConfigData('cod_bank_account')) // Bank account
              ->setValue(round($request->getOrderShipment()->getOrder()->getGrandTotal(), 2)) // Total to pay in EUR
              ->setReference($shipment->gereateCODReference($request->getOrderShipment()->getOrder()->getId()));
        }
        if (empty($this->globalErrors)){
            //var_dump($items); exit;
            //echo $shipment->getXML()->asXML(); exit;
            $documentDateTime = $shipment->getDocumentDateTime();
            $sequence = $shipment->getSequence();
            
            $itellaResult = $shipment->sendShipment();
            if (isset($itellaResult['error'])) {
              $result->setErrors('Shipment Failed with error: ' . $itellaResult['error_description']);
            } 
        } else {
            $result->setErrors(implode('<br/>',$this->globalErrors));
        }
        

        if ($result->hasErrors()) {
            return $result;
        } else {
            if (!empty($items) && isset($items[0]->trackingNumber)){
                $label = new \Mijora\Itella\Pdf\Label($shipment);
                $result->setShippingLabelContent($label->setToString(true)->printLabel('label.pdf'));
                
                $trackings = array();
                foreach ($items as $item){
                    $trackings[] = $item->trackingNumber;
                }
                
                $result->setTrackingNumber( $trackings);
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
    public function rollBack($data)
    {
        
    }

   

    /**
     * Return delivery confirmation types of carrier
     *
     * @param \Magento\Framework\DataObject|null $params
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getDeliveryConfirmationTypes(\Magento\Framework\DataObject $params = null)
    {
        return $this->getCode('delivery_confirmation_types');
    }

    /**
     * Recursive replace sensitive fields in debug data by the mask
     * @param string $data
     * @return string
     */
    protected function filterDebugData($data)
    {
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
    private function appendTrackingError($trackingValue, $errorMessage)
    {
        $error = $this->_trackErrorFactory->create();
        $error->setCarrier(self::CODE);
        $error->setCarrierTitle($this->getConfigData('title'));
        $error->setTracking($trackingValue);
        $error->setErrorMessage($errorMessage);
        $result = $this->getResult();
        $result->append($error);
    }
}
