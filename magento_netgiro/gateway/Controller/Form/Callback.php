<?php declare(strict_types = 1);

namespace netgiro\gateway\Controller\Form;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderRepository;

class Callback extends Action
{

	/**
	 * @var OrderManagementInterface
	 */
	private $orderManagement;

	/**
	 * @var OrderRepository
	 */
	private $orderRepository;

	/**
	 * @var TransactionRepositoryInterface
	 */
	private $transactionRepository;
	/**
	 * @var OrderSender
	 */
	private $orderSender;
	/**
	 * @var ScopeConfigInterface
	 */
	private $scopeConfig;

	public function __construct(
		Context $context,
		OrderManagementInterface $orderManagement,
		OrderRepository $orderRepository,
		TransactionRepositoryInterface $transactionRepository,
		OrderSender $orderSender,
		ScopeConfigInterface $scopeConfig
	) {
		parent::__construct($context);
		$this->orderManagement = $orderManagement;
		$this->orderRepository = $orderRepository;
		$this->transactionRepository = $transactionRepository;
		$this->orderSender = $orderSender;
		$this->scopeConfig = $scopeConfig;
	}

	public function execute()
	{
		$result = $this->resultFactory
			->create(ResultFactory::TYPE_JSON);

		$success = $this->getRequest()->getParam('success');
		$orderId = $this->getRequest()->getParam('orderid');

		$this->validateResponse($orderId);
		if (empty($success)) {
			$this->orderManagement->cancel($orderId);
			return $result->setHttpResponseCode(500);
		}

		$this->orderSender->send($this->orderRepository->get($orderId));
		return $result->setHttpResponseCode(200);
	}


	private function validateResponse($orderId)
	{
		$signatureFromResponse = $this->getRequest()->getParam('signature');
		$order = $this->orderRepository->get($orderId);

		$testMode = $this->scopeConfig->getValue('payment/netgiro/test_mode');
		if ($testMode) {
			$secretKey = 'YCFd6hiA8lUjZejVcIf/LhRXO4wTDxY0JhOXvQZwnMSiNynSxmNIMjMf1HHwdV6cMN48NX3ZipA9q9hLPb9C1ZIzMH5dvELPAHceiu7LbZzmIAGeOf/OUaDrk2Zq2dbGacIAzU6yyk4KmOXRaSLi8KW8t3krdQSX7Ecm8Qunc/A=';
		} else {
			$secretKey = $this->scopeConfig->getValue('payment/netgiro/secret_key');
		}

		$signature = $this->calculateSignature((string) $orderId, (string) $secretKey);

		if ($signature !== $signatureFromResponse) {
			throw new LocalizedException(__("Signature error!"));
		}


		$orderExist = !empty($order->getEntityId()) ? TRUE : FALSE;

		if (!$orderExist) {
			throw new LocalizedException(__("Order doesn't exist!"));
		}

		$paymentMethod = $order->getPayment()->getMethod();

		if ($paymentMethod !== 'netgiro') {
			throw new LocalizedException(__("Invalid payment method!"));
		}

	}

	private function calculateSignature(string $orderId, string $secret): string
	{
		$valueForHash = $secret . $orderId;
		return hash('sha256', $valueForHash);
	}

}
