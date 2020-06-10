<?php

namespace Itella\Shipping\Block\Adminhtml\Order\Create\Shipping\Method;

use Magento\Quote\Model\Quote\Address\Rate;
use Itella\Shipping\Model\Carrier;

/**
 * Class Form
 * @package MagePal\CustomShippingRate\Block\Adminhtml\Order\Create\Shipping\Method
 */
class Form extends \Magento\Sales\Block\Adminhtml\Order\Create\Shipping\Method\Form
{
    protected $Itella_carrier;
    
    
    public function getCurrentTerminal(){
        return $this->getAddress()->getItellaParcelTerminal();
    }
    
    public function getTerminals()
    {
        $rate = $this->getActiveMethodRate();
        $parcel_terminals = Carrier::_getItellaTerminals($this->getAddress()->getCountryId());
        return $parcel_terminals;
   } 
    
}