<?php

namespace Payeer\Payeer\Model;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order;

class Payeer extends AbstractMethod {
	
	protected $_isGateway = true;
	protected $_isInitializeNeeded = true;
	protected $_code = 'payeer';
	protected $_isOffline = false;
	protected $_formBlockType = 'Payeer\Payeer\Block\Form\Payeer';
	protected $_infoBlockType = 'Magento\Payment\Block\Info\Instructions';
	protected $_gateUrl = "";
	protected $orderFactory;

	public function __construct(
		\Magento\Framework\Model\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
		\Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
		\Magento\Payment\Helper\Data $paymentData,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Payment\Model\Method\Logger $logger,
		\Magento\Framework\Module\ModuleListInterface $moduleList,
		\Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
		\Magento\Sales\Model\OrderFactory $orderFactory,
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
		\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
		array $data = [])
	{
		$this->orderFactory = $orderFactory;
		parent::__construct($context,
			$registry,
			$extensionFactory,
			$customAttributeFactory,
			$paymentData,
			$scopeConfig,
			$logger,
			$resource,
			$resourceCollection,
			$data);

		$this->_gateUrl = $this->getConfigData('merchant_url');
	}

	protected function getOrder($orderId) {
		return $this->orderFactory->create()->loadByIncrementId($orderId);
	}

	public function getAmount($orderId) {
		return $this->getOrder($orderId)->getGrandTotal();
	}

	public function getCurrencyCode($orderId) {
		return $this->getOrder($orderId)->getBaseCurrencyCode();
	}

	public function initialize($paymentAction, $stateObject) {
		$stateObject->setState(Order::STATE_PENDING_PAYMENT);
		$stateObject->setStatus(Order::STATE_PENDING_PAYMENT);
		$stateObject->setIsNotified(false);
	}

	protected function isCarrierAllowed($shippingMethod) {
		return strpos($this->getConfigData('allowed_carrier'), $shippingMethod) !== false;
	}

	public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
		
		if ($quote === null) {
			return false;
		}
		
		return parent::isAvailable($quote) && $this->isCarrierAllowed($quote->getShippingAddress()->getShippingMethod());
	}

	public function getGateUrl() {
		return $this->_gateUrl;
	}

	public function getPostData($orderId){
		
		$postData = [];
		$postData['m_shop'] = $this->getConfigData('merchant_id');
		$postData['m_orderid'] = $orderId;
		$postData['m_amount'] = number_format($this->getAmount($orderId), 2, '.', '');
		$postData['m_curr'] = $this->getCurrencyCode($orderId);
		$postData['m_desc'] = base64_encode('Payment № ' . $orderId);
		
		if ($postData['m_curr'] == 'RUR') {
			$postData['m_curr'] = 'RUB';
		}
		
		$postData['m_sign'] = strtoupper(hash('sha256', implode(':', array(
			$postData['m_shop'],
			$orderId,
			$postData['m_amount'],
			$postData['m_curr'],
			$postData['m_desc'],
			$this->getConfigData('secret_key')
		))));

		return $postData;
	}

	public function statusAction($response){
		
        if (isset($response['m_operation_id']) && isset($response['m_sign'])) {
			
			$err = false;
			$message = '';
			
			// запись логов
			
			$log_text = "--------------------------------------------------------\n" .
				"operation id       " . $response['m_operation_id'] . "\n" .
				"operation ps       " . $response['m_operation_ps'] . "\n" .
				"operation date     " . $response['m_operation_date'] . "\n" .
				"operation pay date " . $response['m_operation_pay_date'] . "\n" .
				"shop               " . $response['m_shop'] . "\n" .
				"order id           " . $response['m_orderid'] . "\n" .
				"amount             " . $response['m_amount'] . "\n" .
				"currency           " . $response['m_curr'] . "\n" .
				"description        " . base64_decode($response['m_desc']) . "\n" .
				"status             " . $response['m_status'] . "\n" .
				"sign               " . $response['m_sign'] . "\n\n";
			
			$log_file = $this->getConfigData('log_file');
			
			if (!empty($log_file)) {
				file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
			}
			
			// проверка цифровой подписи и ip

			$sign_hash = strtoupper(hash('sha256', implode(":", array(
				$response['m_operation_id'],
				$response['m_operation_ps'],
				$response['m_operation_date'],
				$response['m_operation_pay_date'],
				$response['m_shop'],
				$response['m_orderid'],
				$response['m_amount'],
				$response['m_curr'],
				$response['m_desc'],
				$response['m_status'],
				$this->getConfigData('secret_key')
			))));
			
			$valid_ip = true;
			$sIP = str_replace(' ', '', $this->getConfigData('ip_filter'));
			
			if (!empty($sIP)) {
				$arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
				if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
				'(' . $arrIP[1] . '|\*{1})(\.)' .
				'(' . $arrIP[2] . '|\*{1})(\.)' .
				'(' . $arrIP[3] . '|\*{1})($|,)/', $sIP)) {
					$valid_ip = false;
				}
			}
			
			if (!$valid_ip) {
				$message .= __(" - IP address of the server is not trusted") . "\n" .
				__("   Trusted IP: ") . $sIP . "\n" .
				__("   IP of the current server: ") . $_SERVER['REMOTE_ADDR'] . "\n";
				$err = true;
			}

			if ($response['m_sign'] != $sign_hash) {
				$message .= __(" - Invalid digital signature") . "\n";
				$err = true;
			}
			
			if (!$err) {
				
				// загрузка заказа
				
				$order = $this->getOrder($response['m_orderid']);
				$order_amount = number_format($order->getGrandTotal(), 2, '.', '');
				$order_curr = $order->getGlobalCurrencyCode();
				
				if ($order_curr == 'RUR') {
					$order_curr = 'RUB';
				}

				// проверка суммы, валюты
			
				if ($response['m_amount'] != $order_amount) {
					$message .= __(" - Wrong amount") . "\n";
					$err = true;
				}

				if ($response['m_curr'] != $order_curr) {
					$message .= __(" - Wrong currency") . "\n";
					$err = true;
				}
				
				// проверка статуса
				
				if (!$err) {
					
					switch ($response['m_status']) {
						
						case 'success':

							if ($order->getState() == Order::STATE_NEW) {
								
								$payment = $order->getPayment();
								$payment->setTransactionId($response['m_orderid'])->setIsTransactionClosed(0);
								$order->setStatus(Order::STATE_PROCESSING);
								$order->save();
							}
							
							break;
							
						default:

							$message .= __(" - Payment status is not success") . "\n";
							$err = true;
							break;
					}
				}
			}
			
			if ($err) {
				
				$to = $this->getConfigData('admin_email');

				if (!empty($to)) {
					$message = __("Failed to make the payment through the system Payeer for the following reasons:") . "\n\n" . $message . "\n" . $log_text;
					$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
					"Content-type: text/plain; charset=utf-8 \r\n";
					mail($to, __("Payment error"), $message, $headers);
				}
				
				$result = $response['m_orderid'] . '|error';
			}
			else {
				$result = $response['m_orderid'] . '|success';
			}
        }
		else {
			$result = 'The operation not found';
        }
		
		return $result;
	}
}
