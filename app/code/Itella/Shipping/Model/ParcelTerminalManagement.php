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
    protected $scopeConfig;

    /**
     * ParcelTerminalInterfaceFactory constructor.
     * 
     * @param ParcelTerminalInterfaceFactory $parcelTerminalInterfaceFactory
     */
    public function __construct(
        ParcelTerminalInterfaceFactory $parcelTerminalInterfaceFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->parcelTerminalFactory = $parcelTerminalInterfaceFactory;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get offices for the given postcode and city
     *
     * @param string $group
     * @param string $city
     * @param string $country
     * @return Array
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
        $filtered_terminals = $this->filterParcelTerminals($result);
        return $filtered_terminals;
    }

    private function getConfig($config_path) {
        return $this->scopeConfig->getValue(
            'carriers/itella/' . $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    private function filterParcelTerminals($terminals)
    {
        if (!is_array($terminals)) {
            return $terminals;
        }

        $exclude_outdoors = $this->getConfig('exclude_outdoors');

        $remove_terminals = array();
        foreach ($terminals as $key => $terminal) {
            if (!isset($terminal['capabilities'])) {
                continue;
            }
            foreach ($terminal['capabilities'] as $capability) {
                if ($exclude_outdoors && $capability['name'] == 'outdoors' && $capability['value'] == 'OUTDOORS') {
                    $remove_terminals[] = $key;
                    break;
                }
            }
        }
        foreach ($remove_terminals as $key) {
            unset($terminals[$key]);
        }
        $terminals = array_values($terminals);

        return $terminals;
    }
}