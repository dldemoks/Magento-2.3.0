<?php

namespace Payeer\Payeer\Controller\Url;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Status extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface {

    public function __construct(\Magento\Framework\App\Action\Context $context){
        parent::__construct($context);
    }

    public function execute(){
		$paymentMethod = $this->_objectManager->create('Payeer\Payeer\Model\Payeer');
		$data = $this->getRequest()->getPostValue();
        $result = $paymentMethod->statusAction($data);
		echo $result;
    }
	
	public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool {
        return true;
    }
}
