<?php

namespace Itella\Shipping\Controller\Adminhtml\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Controller\ResultFactory;
use Magento\Shipping\Model\Shipping\LabelGenerator;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Class MassDelete
 */
class PrintMassLabels extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction {

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;
    public $labelsContent = array();

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(Context $context, Filter $filter, CollectionFactory $collectionFactory, OrderManagementInterface $orderManagement) {
        $this->collectionFactory = $collectionFactory;
        $this->orderManagement = $orderManagement;
        parent::__construct($context, $filter);
    }

    public function isItellaMethod($order) {
        $_ItellaMethods = array(
            'itella_parcel_terminal',
            'itella_courier'
        );
        $order_shipping_method = strtolower($order->getData('shipping_method'));
        return in_array($order_shipping_method, $_ItellaMethods);
    }

    private function _collectPostData($post_key = null) {
        return $this->getRequest()->getPost($post_key);
    }

    private function _fillDataBase(AbstractCollection $collection) {
        $pack_data = array();
        $model = $this->_objectManager->create('Magento\Sales\Model\Order');
        $unique = array();
        foreach ($collection->getItems() as $order) {
            if (!$order->getEntityId()) {
                continue;
            }
            if (in_array($order->getEntityId(), $unique))
                continue;
            $unique[] = $order->getEntityId();
            $pack_no = array();

            if (!$this->isItellaMethod($order)) {
                $text = 'Warning: Order ' . $order->getData('increment_id') . ' not Smartpost shipping method.';
                $this->messageManager->addError($text);
                continue;
            }
            if (!$order->getShippingAddress()) { //Is set Shipping adress?
                $items = $order->getAllVisibleItems();
                foreach ($items as $item) {
                    $ordered_items['sku'][] = $item->getSku();
                    $ordered_items['type'][] = $item->getProductType();
                }
                $text = 'Warning: Order ' . $order->getData('increment_id') . ' not have Shipping Address.';
                $this->messageManager->addError($text);
                continue;
            }
            $pack_data[] = $order;
        }

        return $pack_data;
    }

    /**
     * Generate ShipmentXML
     *
     * Test Data if all correct, @return Itella labels
     */
    public function massAction(AbstractCollection $collection) {
        $pack_data = array();
        $success_files = array();
        $order_ids = $this->_collectPostData('order_ids');
        $model = $this->_objectManager->create('Magento\Sales\Model\Order');
        $pack_data = $this->_fillDataBase($collection); //Send data to server and get packs number's

        if (!count($pack_data) || $pack_data === false) { //If nothing to print
            $this->_redirect($this->_redirect->getRefererUrl());
            return;
        } else { //If found Order who can get Label so Do it
            $order_ids = array();
            foreach ($pack_data as $order) {
                $this->_createShipment($order);
            }
        }
        if (!empty($this->labelsContent)) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $labelGenerator = $objectManager->create('Magento\Shipping\Model\Shipping\LabelGenerator');
            $fileFactory = $objectManager->create('Magento\Framework\App\Response\Http\FileFactory');
            $outputPdf = $this->_combineLabelsPdfZend($this->labelsContent);
            //$outputPdf->Output('Itella_labels.pdf','D');
            header("Content-Disposition: attachment; filename=\"Itella_labels.pdf\"");
            header("Content-Type: application/pdf");
            header("Content-Transfer-Encoding: binary");
            // disable caching on client and proxies, if the download content vary
            header("Expires: 0");
            header("Cache-Control: no-cache, must-revalidate");
            header("Pragma: no-cache");
            echo $outputPdf->render();
            return;
            //return $fileFactory->create('ItellaShippingLabels.pdf', $outputPdf->Output('S'), DirectoryList::VAR_DIR, 'application/pdf');
        }
        $this->messageManager->addError(__('There are no shipping labels related to selected orders.'));
        $this->_redirect($this->_redirect->getRefererUrl());
        return;
    }

    public function _createShipment($order) {
        $shipmentItems = array();
        foreach ($order->getAllItems() as $item) {
            $shipmentItems[$item->getId()] = $item->getQtyToShip();
        }
        // Prepear shipment and save ....
        if ($order->getId() && !empty($shipmentItems)) {
            $shipment = false;
            if ($order->hasShipments()) {
                foreach ($order->getShipmentsCollection() as $_shipment) {
                    $shipment = $_shipment; //get last shipment            
                }
            }

            $label = $this->_createShippingLabel($shipment, $order);
            if (!$label) {
                $this->messageManager->addWarning('Warning: Shipment label not generated for order ' . $order->getData('increment_id'));
            } else {
                $this->messageManager->addSuccess('Success: Order ' . $order->getData('increment_id') . ' shipment generated');
                $order->setIsInProcess(true);
                $order->addStatusHistoryComment('Automatically SHIPPED by Smartpost mass action.', false);
                $order->setState(Order::STATE_COMPLETE)->setStatus(Order::STATE_COMPLETE);
            }
            $order->save();
        } else {
            $this->messageManager->addWarning('Warning: Order ' . $order->getData('increment_id') . ' is empty or cannot be shipped or has been shipped already');
        }
    }

    protected function _createShippingLabel($shipment, $order) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $convertOrder = $objectManager->create('Magento\Sales\Model\Convert\Order');
        $subtotal = 0;
        $packaging = array(
                'items' => array()
            );
        //var_dump($shipment->getItems()); exit;
        if (!$shipment) {
            $shipment = $convertOrder->toShipment($order);
            $shipment->register();
            $shipment->getOrder()->setIsInProcess(true);

            
            
            foreach ($order->getAllItems() AS $orderItem) {
                if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                    continue;
                }
                $qtyShipped = $orderItem->getQtyToShip();
                $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);
                $packaging['items'][$shipmentItem->getOrderItemId()] = array(
                    'qty' => $shipmentItem->getQty(),
                    'custom_value' => $shipmentItem->getPrice(),
                    'price' => $shipmentItem->getPrice(),
                    'name' => $shipmentItem->getName(),
                    'weight' => $shipmentItem->getWeight(),
                    'product_id' => $shipmentItem->getProductId(),
                    'order_item_id' => $shipmentItem->getOrderItemId()
                );
                $subtotal += $shipmentItem->getRowTotal();
                
                $shipment->addItem($shipmentItem);
            }
        } else {
            foreach ($order->getAllItems() AS $orderItem) {
                if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                    continue;
                }
                $qtyShipped = $orderItem->getQtyToShip();
                $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);
                $packaging['items'][$shipmentItem->getOrderItemId()] = array(
                    'qty' => $shipmentItem->getQty(),
                    'custom_value' => $shipmentItem->getPrice(),
                    'price' => $shipmentItem->getPrice(),
                    'name' => $shipmentItem->getName(),
                    'weight' => $shipmentItem->getWeight(),
                    'product_id' => $shipmentItem->getProductId(),
                    'order_item_id' => $shipmentItem->getOrderItemId()
                );
                $subtotal += $shipmentItem->getRowTotal();
            }
        }
        //for sample, not used in label
        $package = array();
        $packaging['params'] = array(
                'container' => '',
                'weight' => 1,
                'custom_value' => $subtotal,
                'length' => 0,
                'width' => 0,
                'height' => 0,
                'weight_units' => 'KILOGRAM',
                'dimension_units' => 'CENTIMETER',
                'content_type' => '',
                'content_type_other' => ''
        );
        $package[] = $packaging;
        $shipment->setPackages($package);
        
        $labelFactory = $objectManager->create('\Magento\Shipping\Model\Shipping\Labels');
        try {
            $response = $labelFactory->requestToShipment($shipment);
        } catch (\Exception $e) {
            $this->messageManager->addWarning('Warning: Order ' . $shipment->getOrder()->getData('increment_id') . ': ' . $e->getMessage());
            return false;
        }
        if ($response->hasErrors()) {
            $this->messageManager->addWarning('Warning: Order ' . $shipment->getOrder()->getData('increment_id') . ': ' . $response->getErrors());
            return false;
        }
        if (!$response->hasInfo()) {
            return false;
        }
        $labelsContent = array();
        $trackingNumbers = array();
        $info = $response->getInfo();
        foreach ($info as $inf) {
            if (!empty($inf['tracking_number'])) {
                if (is_array($inf['tracking_number'])) {
                    $trackingNumbers = array_merge($inf['tracking_number'], $trackingNumbers);
                } else {
                    $trackingNumbers[] = $inf['tracking_number'];
                }
            }
            if (!empty($inf['label_content'])) {
                $labelsContent[] = $inf['label_content'];
            }
        }
        $outputPdf = $this->_combineLabelsPdfZend($labelsContent);
        $shipment->setShippingLabel($outputPdf->render());
        $shipment->getExtensionAttributes()->setSourceCode('default');
        $shipment->save();
        if ($trackingNumbers) {
            foreach ($shipment->getAllTracks() as $track) {
                $track->delete();
            }
            foreach ($trackingNumbers as $trackingNumber) {
                $track = $objectManager->create('Magento\Sales\Model\Order\Shipment\Track')->setShipment($shipment)->setTitle('Itella')->setNumber($trackingNumber)->setCarrierCode('Itella')->setOrderId($shipment->getData('order_id'))->save();
            }
        } else {
            $text = 'Warning: Order ' . $shipment->getOrder()->getData('increment_id') . ' has not received tracking numbers.';
            $this->messageManager->addWarning($text);
        }
        $this->labelsContent[] = $shipment->getShippingLabel();
        return true;
    }

    private function _combineLabelsPdf(array $labelsContent) {
        $pdf = new \setasign\Fpdi\TcpdfFpdi(PDF_PAGE_ORIENTATION, 'mm', array(110, 190), true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $tmp_files = array();
        $count = 1;
        $labels = 4;
        foreach ($labelsContent as $content) {
            if (!$content)
                continue;
            $prefix = rand(100, 999) . time();
            $label_url = realpath(dirname(__FILE__)) . '/' . $prefix . '.pdf';
            file_put_contents($label_url, $content);
            $pagecount = $pdf->setSourceFile($label_url);
            for ($i = 1; $i <= $pagecount; $i++) {
                $tplidx = $pdf->ImportPage($i);
                $pdf->AddPage();
                $pdf->useTemplate($tplidx);

                $labels++;
            }
            unlink($label_url);
        }
        return $pdf;
    }

    private function _combineLabelsPdfZend(array $labelsContent) {
        $outputPdf = new \Zend_Pdf();
        foreach ($labelsContent as $content) {
            if (stripos($content, '%PDF-') !== false) {
                $pdfLabel = \Zend_Pdf::parse($content);
                foreach ($pdfLabel->pages as $page) {
                    $outputPdf->pages[] = clone $page;
                }
            } else {
                $page = $this->createPdfPageFromImageString($content);
                if ($page) {
                    $outputPdf->pages[] = $page;
                }
            }
        }
        return $outputPdf;
    }

}
