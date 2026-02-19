<?php
namespace Itella\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Mijora\Itella\Shipment\Shipment;

class ApiPickupServiceOptions implements OptionSourceInterface
{    
    public function toOptionArray()
    {
        return [
            [
                'value' => Shipment::PRODUCT_PARCEL_CONNECT,
                'label' => 'Parcel Connect'
            ],
            [
                'value' => Shipment::PRODUCT_POSTAL_PARCEL,
                'label' => 'Postal Parcel'
            ]
        ];
    }
}
