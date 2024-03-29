<?php declare(strict_types = 1);

namespace netgiro\gateway\Controller\Form;

use netgiro\gateway\Helper\Validation;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

class Response extends Action
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
     * Helper for response validation
     *
     * @var Validation
     */

    private $validation;

    /**
     * Response constructor.
     *
     * @param Context                        $context
     * @param OrderManagementInterface       $orderManagement
     * @param OrderRepository                $orderRepository
     * @param TransactionRepositoryInterface $transactionRepository
     * @param OrderSender                    $orderSender
     * @param Validation                     $validation
     */
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

    /**
     * Execute response controller
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute()
    {
        $success = $this->getRequest()->getParam('success');
        $orderId = $this->getRequest()->getParam('orderid');
        $netgiroSignatureFromResponse = $this->getRequest()->getParam('netgiroSignature');
        $transactionID = $this->getRequest()->getParam('transactionid');
        $numberFormatted = $this->getRequest()->getParam('invoiceNumber');
        $totalAmount = $this->getRequest()->getParam('totalAmount');
        $statusId = $this->getRequest()->getParam('status');
        $order = $this->orderRepository->get($orderId);
        $paymentId = $order->getPayment()->getEntityId();

        $validationPass = $this->validation->validateResponse(
            $order,
            $netgiroSignatureFromResponse,
            $orderId,
            $transactionID,
            $numberFormatted,
            $totalAmount,
            $statusId
        );
        if (empty($success) || !$validationPass) {

            if (!empty($this->validation->exceptionMessage)) {
                throw new LocalizedException(__($this->validation->exceptionMessage));
            }

            $this->orderManagement->cancel($orderId);
            $this->messageManager->addErrorMessage('Payment has been cancelled.');
            $this->_redirect('checkout/cart', ['_secure' => true]);
            return;
        }

        $transaction = $this->_objectManager->create(TransactionInterface::class);
        $transaction->setOrderId($orderId)
            ->setOrderPaymentObject($order->getPayment())
            ->setPaymentId($paymentId)
            ->setTxnId($transactionID)
            ->setParentTxnId($transactionID) // TODO Hvað er rökrétt value hér
            ->setTxnType(TransactionInterface::TYPE_CAPTURE)
            ->setIsClosed(false);
        $this->transactionRepository->save($transaction);

        $this->orderSender->send($this->orderRepository->get($orderId));

        $this->_redirect('checkout/onepage/success', ['_secure' => true]);
    }
}
