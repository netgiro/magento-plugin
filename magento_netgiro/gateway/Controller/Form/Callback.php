<?php declare(strict_types = 1);

namespace netgiro\gateway\Controller\Form;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderRepository;
use netgiro\gateway\Helper\Validation;

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
	 * @var Validation
	 */
	private $validation;


	public function __construct(
		Context $context,
		OrderManagementInterface $orderManagement,
		OrderRepository $orderRepository,
		TransactionRepositoryInterface $transactionRepository,
		OrderSender $orderSender,
		Validation $validation
	) {
		parent::__construct($context);
		$this->orderManagement = $orderManagement;
		$this->orderRepository = $orderRepository;
		$this->transactionRepository = $transactionRepository;
		$this->orderSender = $orderSender;
		$this->validation = $validation;
	}

	public function execute()
	{
		$result = $this->resultFactory
			->create(ResultFactory::TYPE_JSON);
		$success = $this->getRequest()->getParam('success');
		$orderId = $this->getRequest()->getParam('orderid');
		$signatureFromResponse = $this->getRequest()->getParam('signature');
		$order = $this->orderRepository->get($orderId);

		$validationPass = $this->validation->validateResponse($order, $signatureFromResponse, $orderId);
		if (empty($success) || !$validationPass) {

			if (!empty($this->validation->exceptionMessage)) {
				throw new LocalizedException(__($this->validation->exceptionMessage));
			}

			$this->orderManagement->cancel($orderId);
			return $result->setHttpResponseCode(500);
		}

		$this->orderSender->send($this->orderRepository->get($orderId));
		return $result->setHttpResponseCode(200);
	}


}
