<?php

namespace Itella\Shipping\Api;

interface ParcelTerminalManagementInterface
{

    /**
     * Find parcel terminals for the customer
     *
     * @param string $group
     * @param string $city
     * @param string $country
     * @return Array
     */
    public function fetchParcelTerminals($group, $city, $country );
}