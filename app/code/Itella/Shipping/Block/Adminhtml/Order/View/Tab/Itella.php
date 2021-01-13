<?php

namespace Itella\Shipping\Block\Adminhtml\Order\View\Tab;


class Itella extends \Magento\Backend\Block\Template implements \Magento\Backend\Block\Widget\Tab\TabInterface
{
    /**
     * Template
     *
     * @var string
     */
    protected $_template = 'order/view/tab/itella.phtml';

    protected $Itella_carrier;
    
   
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry = null;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = [], 
        \Itella\Shipping\Model\Carrier $Itella_carrier
    ) {
        $this->coreRegistry = $registry;
        $this->Itella_carrier = $Itella_carrier;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve order model instance
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->coreRegistry->registry('current_order');
    }
    
    
    public function getTerminal($order)
    {
        $shippingAddress = $order->getShippingAddress();
        $terminal_id = $shippingAddress->getItellaParcelTerminal();
        $parcel_terminal = $this->Itella_carrier->_getItellaTerminal($terminal_id,$shippingAddress->getCountryId());
        return $parcel_terminal;
   }       

    public function getServices(){
        return array('3101'=>__("Cash On Delivery"),
        '3104' => __("Fragile"),
        '3166' => __("Call before Delivery"),
        '3174' => __("Oversized"),
        '3102' => __("Multi Parcel"));
    }
    
    public function isItellaMethod($order)
      {
        $_ItellaMethods      = array(
          //'Itella_PARCEL_TERMINAL',
          'itella_COURIER'
        );
        $order_shipping_method = $order->getData('shipping_method');
        return in_array($order_shipping_method, $_ItellaMethods);
      }

    /**
     * {@inheritdoc}
     */
    public function getTabLabel()
    {
        return __('Smartpost carrier services');
    }

    /**
     * {@inheritdoc}
     */
    public function getTabTitle()
    {
        return __('Smartpost carrier services');
    }

    /**
     * {@inheritdoc}
     */
    public function canShowTab()
    {
        // For me, I wanted this tab to always show
        // You can play around with the ACL settings 
        // to selectively show later if you want
        //return true;
        return $this->isItellaMethod($this->getOrder());
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        // For me, I wanted this tab to always show
        // You can play around with conditions to
        // show the tab later
        return false;
    }

    /**
     * Get Tab Class
     *
     * @return string
     */
    public function getTabClass()
    {
        // I wanted mine to load via AJAX when it's selected
        // That's what this does
        //return 'ajax only';
        return '';
    }

    /**
     * Get Class
     *
     * @return string
     */
    public function getClass()
    {
        return $this->getTabClass();
    }

    /**
     * View URL getter
     *
     * @param int $orderId
     * @return string
     */
    public function getViewUrl($orderId)
    {
        return $this->getUrl('itellatab/*/*', ['order_id' => $orderId]);
    }
}