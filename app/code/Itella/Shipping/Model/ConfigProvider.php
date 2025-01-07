<?php

namespace Itella\Shipping\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class SampleConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    protected $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $showMapValue = $this->scopeConfig->getValue('carriers/itella/show_map') ?? "1";

        $config = [
            'itellaGlobalData' => [
                'show_map' => $showMapValue === "1",
            ]
        ];

        return $config;
    }
}
