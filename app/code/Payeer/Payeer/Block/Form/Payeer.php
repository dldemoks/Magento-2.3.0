<?php

namespace Payeer\Payeer\Block\Form;

abstract class Payeer extends \Magento\Payment\Block\Form {
	
    protected $_instructions;
    protected $_template = 'form/paw.phtml';

    public function getInstructions(){
		
        if ($this->_instructions === null) {
            $method = $this->getMethod();
            $this->_instructions = $method->getConfigData('instructions');
        }
		
        return $this->_instructions;
    }
}
