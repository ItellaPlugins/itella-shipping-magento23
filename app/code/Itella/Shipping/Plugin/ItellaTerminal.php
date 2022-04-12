<?php

namespace Itella\Shipping\Plugin;

class ItellaTerminal
{

    public function afterGet(
            \Magento\Sales\Model\OrderRepository\Interceptor $subject,
            \Magento\Sales\Model\Order\Interceptor $order
    ) {
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $terminal_id = $shippingAddress->getItellaParcelTerminal();
            $extensionAttributes = $order->getExtensionAttributes();
            $extensionAttributes->setSmartpostParcelTerminal($terminal_id); // custom field value set
            $order->setExtensionAttributes($extensionAttributes);
        }
        return $order;
    }

    public function afterGetList(
            \Magento\Sales\Model\OrderRepository\Interceptor $subject,
            \Magento\Sales\Model\ResourceModel\Order\Collection $searchCriteria
    ): \Magento\Sales\Model\ResourceModel\Order\Collection {
        $orders = [];
        foreach ($searchCriteria->getItems() as $order) {
            $shippingAddress = $order->getShippingAddress();
            if ($shippingAddress) {
                $terminal_id = $shippingAddress->getItellaParcelTerminal();
                $extensionAttributes = $order->getExtensionAttributes();
                $extensionAttributes->setSmartpostParcelTerminal($terminal_id);
                $order->setExtensionAttributes($extensionAttributes);
            }
        }
        $searchCriteria->setItems($orders);
        return $searchCriteria;
    }

}
