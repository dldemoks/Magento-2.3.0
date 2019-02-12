<?php

namespace Payeer\Payeer\Block\Widget;

use \Magento\Framework\View\Element\Template;

class Redirect extends Template {

    protected $Config;
    protected $_checkoutSession;
    protected $_customerSession;
    protected $_orderFactory;
    protected $_orderConfig;
    protected $httpContext;
    protected $_template = 'html/paw.phtml';

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        \Payeer\Payeer\Model\Payeer $paymentConfig,
        array $data = []
    ){
        parent::__construct($context, $data);
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
        $this->_orderConfig = $orderConfig;
        $this->_isScopePrivate = true;
        $this->httpContext = $httpContext;
        $this->Config = $paymentConfig;
    }

    public function getGateUrl() {
        return $this->Config->getGateUrl();
    }

    public function getAmount() {
		
    	$orderId = $this->_checkoutSession->getLastOrderId(); 
        
		if ($orderId) {
            $incrementId = $this->_checkoutSession->getLastRealOrderId();
        	return $this->Config->getAmount($incrementId);
    	}
		
        return null;
    }

    public function getPostData() {
		
        $orderId = $this->_checkoutSession->getLastOrderId(); 
        
		if ($orderId) {
            $incrementId = $this->_checkoutSession->getLastRealOrderId();
        	return $this->Config->getPostData($incrementId);
	    }
		
        return null;
    }
}
