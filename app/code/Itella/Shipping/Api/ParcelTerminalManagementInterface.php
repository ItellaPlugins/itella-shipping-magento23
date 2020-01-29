<?php

namespace Itella\Shipping\Api;

interface ParcelTerminalManagementInterface
{

    /**
     * Find parcel terminals for the customer
     *
     * @param string $postcode
     * @param string $city
     * @param string $country
     * @return \Itella\Shipping\Api\Data\ParcelTerminalInterface[]
     */
    public function fetchParcelTerminals($group, $city, $country );
}