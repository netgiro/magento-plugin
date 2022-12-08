<?php declare(strict_types = 1);

namespace netgiro\gateway\Controller\Form;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use netgiro\gateway\Helper\Data;


class Index extends Action
{

	/**
	 * @var JsonFactory
	 */
	private $jsonFactory;

	/**
	 * @var ScopeConfigInterface
	 */
	private $scopeConfig;

	/**
	 * @var StoreManagerInterface
	 */
	private $storeManager;

	/**
	 * @var Session
	 */
	private $checkoutSession;
	/**
	 * @var Data
	 */
	private $data;


	public function __construct(
		Context $context,
		JsonFactory $jsonFactory,
		ScopeConfigInterface $scopeConfig,
		StoreManagerInterface $storeManager,
		Session $checkoutSession,
		Data $data
	) {
		parent::__construct($context);
		$this->jsonFactory = $jsonFactory;
		$this->scopeConfig = $scopeConfig;
		$this->storeManager = $storeManager;
		$this->checkoutSession = $checkoutSession;
		$this->data = $data;
	}

	public function execute()
	{
		$testMode = $this->scopeConfig->getValue('payment/netgiro/test_mode');

		if ($testMode) {
			$action = 'https://test.netgiro.is/securepay';
			$appId = '881E674F-7891-4C20-AFD8-56FE2624C4B5';
			$secretKey = 'YCFd6hiA8lUjZejVcIf/LhRXO4wTDxY0JhOXvQZwnMSiNynSxmNIMjMf1HHwdV6cMN48NX3ZipA9q9hLPb9C1ZIzMH5dvELPAHceiu7LbZzmIAGeOf/OUaDrk2Zq2dbGacIAzU6yyk4KmOXRaSLi8KW8t3krdQSX7Ecm8Qunc/A=';

		} else {
			$action = 'https://securepay.netgiro.is/v1/';
			$appId = $this->scopeConfig->getValue('payment/netgiro/app_id');
			$secretKey = $this->scopeConfig->getValue('payment/netgiro/secret_key');
		}

		$baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
		$responseUrl = $baseUrl . $this->scopeConfig->getValue('payment/netgiro/response_url');
		$callbackUrl = $baseUrl . $this->scopeConfig->getValue('payment/netgiro/callback_url');

		$orderId = $this->checkoutSession->getLastRealOrder()->getId();
		$totalAmount = (int) $this->checkoutSession->getLastRealOrder()->getGrandTotal();

		$signature = $this->calculateSignature($secretKey, (string) $orderId, (string) $totalAmount, $appId);
		$post_data = array(
			'action' => $action,
			'fields' => array (
				'ApplicationID' => $appId,
				'Iframe' => "false",
				'Signature' => $signature,
				'TotalAmount' =>  $totalAmount,
				'OrderId' => $orderId,
				'PaymentSuccessfulURL' => $responseUrl,
				'PaymentCancelledURL' => $responseUrl,
				'PaymentConfirmedURL' => $callbackUrl,
				'ConfirmationType' => "1",
				'ClientInfo' => 'Magento ' . $this->data->getVersion(),
			)
		);
		$result = $this->jsonFactory->create();

		return $result->setData($post_data);
	}


	private function calculateSignature(string $secret, string $orderId, string $totalAmount, string $appId): string
	{
		$valueForHash = $secret . $orderId . $totalAmount . $appId;
		return hash('sha256', $valueForHash);
	}

}