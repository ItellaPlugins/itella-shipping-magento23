<?php

namespace Itella\Shipping\Api\Data;

/**
 * Office Interface
 */
interface ParcelTerminalInterface
{
	/**
     * @return string
     */
    public function getZip();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getLocation();
    
    public function getX();
    
    public function getY();
}