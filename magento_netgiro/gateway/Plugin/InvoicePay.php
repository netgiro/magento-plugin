<?php
namespace Netgiro\Gateway\Plugin;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;


class InvoicePay
{
    /**
     * Invoice repository
     *
     * @var InvoiceRepositoryInterface
     */
    protected $invoiceRepository;

    /**
     * Invoice repository
     *
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;


    /**
     * Constructor
     *
     * @param InvoiceRepositoryInterface $invoiceRepository
     */
    public function __construct( InvoiceRepositoryInterface $invoiceRepository, TransactionRepositoryInterface $transactionRepository ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * @param InvoiceRepositoryInterface $subject
     * @param InvoiceInterface $invoice
     * @return InvoiceInterface
     */
    public function afterSave($subject,  $invoice)
    {

        $payment = $subject->getOrder()->getPayment();
        $paymentMethodCode = $payment->getMethodInstance()->getCode();
        if( $paymentMethodCode !=='netgiro' ){
            return [$invoice];
        }

        $orderId = $invoice->getOrderId();
        $transaction = $this->transactionRepository->getByTransactionType(
            TransactionInterface::TYPE_CAPTURE,
            $orderId
        );

        if($transaction) {
            $transactionId = $transaction->getId();
            $invoice->setTransactionId($transactionId);
            $this->invoiceRepository->save($invoice);
        }
        return [$invoice];
    }

}
