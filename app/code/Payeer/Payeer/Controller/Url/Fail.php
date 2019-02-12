<?php

namespace Payeer\Payeer\Controller\Url;

class Fail extends \Magento\Framework\App\Action\Action {

    public function __construct(\Magento\Framework\App\Action\Context $context){
        parent::__construct($context);
    }

    public function execute(){
		$this->_redirect('checkout/onepage/failure');
    }
}
