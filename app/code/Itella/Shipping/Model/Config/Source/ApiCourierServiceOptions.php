<?php
namespace Itella\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Mijora\Itella\Shipment\Shipment;

class ApiCourierServiceOptions implements OptionSourceInterface
{    
    public function toOptionArray()
    {
        return [
            [
                'value' => Shipment::PRODUCT_EXPRESS_BUSINESS_DAY,
                'label' => 'Express Business Day Parcel'
            ],
            [
                'value' => Shipment::PRODUCT_HOME_PARCEL,
                'label' => 'Home Parcel'
            ]
        ];
    }
}
