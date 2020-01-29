<?php

namespace Itella\Shipping\Model;

use Itella\Shipping\Api\ParcelTerminalManagementInterface;
use Itella\Shipping\Api\Data\ParcelTerminalInterfaceFactory;
use Itella\Shipping\Model\Carrier;
use Magento\Framework\Xml\Parser;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Module\Dir;

class ParcelTerminalManagement implements ParcelTerminalManagementInterface
{
    protected $parcelTerminalFactory;

    /**
     * OfficeManagement constructor.
     * @param OfficeInterfaceFactory $officeInterfaceFactory
     */
    public function __construct(ParcelTerminalInterfaceFactory $parcelTerminalInterfaceFactory)
    {
        $this->parcelTerminalFactory = $parcelTerminalInterfaceFactory;
    }

    /**
     * Get offices for the given postcode and city
     *
     * @param string $postcode
     * @param string $limit
     * @param string $country
     * @param string $group
     * @return \Itella\Shipping\Api\Data\OfficeInterface[]
     */
    public function fetchParcelTerminals($group, $city, $country )
    {
        $result = array();
        $result_city = array();
        $parser = new Parser();
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\Module\Dir\Reader $reader */
        $configReader = $om->get('Magento\Framework\Module\Dir\Reader');
        $locationFile = $configReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Itella_Shipping') . '/location_'.strtolower($country).'.json';
        if (!file_exists($locationFile)){
          return array();
        }
        //$Itella_carrier = new Carrier();
        //$terminals = Carrier::getCode('terminal');
        $result = json_decode(file_get_contents($locationFile),true);
        return $result;
    }
}