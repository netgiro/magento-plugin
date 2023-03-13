<?php
namespace Netgiro\Gateway\Plugin;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;

class InvoicePay
{
    /**
     * @var InvoiceRepositoryInterface
     */
    protected $invoiceRepository;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @param InvoiceRepositoryInterface     $invoiceRepository
     * @param TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        InvoiceRepositoryInterface $invoiceRepository,
        TransactionRepositoryInterface $transactionRepository
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * Adds Transaction to Order after invoice has been saved
     *
     * @param  InvoiceRepositoryInterface $subject
     * @param  InvoiceInterface           $invoice
     * @return InvoiceInterface
     */
    public function afterSave($subject, $invoice)
    {
        $payment = $subject->getOrder()->getPayment();
        $paymentMethodCode = $payment->getMethodInstance()->getCode();
        if ($paymentMethodCode !=='netgiro') {
            return [$invoice];
        }

        $orderId = $invoice->getOrderId();
        $transaction = $this->transactionRepository->getByTransactionType(
            TransactionInterface::TYPE_CAPTURE,
            $orderId
        );

        if ($transaction) {
            $transactionId = $transaction->getId();
            $invoice->setTransactionId($transactionId);
            $this->invoiceRepository->save($invoice);
        }
        return [$invoice];
    }
}
