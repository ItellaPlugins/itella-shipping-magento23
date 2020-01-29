<?php

namespace Itella\Shipping\Model;

use Magento\Framework\DataObject;
use Itella\Shipping\Api\Data\ParcelTerminalInterface;

class ParcelTerminal extends DataObject implements ParcelTerminalInterface
{
    /**
     * @return string
     */
    public function getZip()
    {
        return (string)$this->_getData('zip');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return (string)$this->_getData('name');
    }

    /**
     * @return string
     */
    public function getLocation()
    {
        return (string)$this->_getData('location');
    }
    
    public function getX()
    {
        return (string)$this->_getData('x');
    }
    
    public function getY()
    {
        return (string)$this->_getData('y');
    }
}