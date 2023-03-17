<?php
namespace netgiro\gateway\Model\Payment;

use Magento\Sales\Model\Order\Payment;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\TransactionRepositoryInterface;
use netgiro\gateway\Model\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

class Netgiro extends AbstractMethod
{
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = 'netgiro';

    /**
     * Can refund flag
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Is gateway flag
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Can refund invoice partially flag
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     *
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    private $curl;

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var BuilderInterface
     */
    protected $transactionBuilder;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \netgiro\gateway\Model\Config
     */
    private $config;

    /**
     * @param Context                         $context
     * @param Registry                        $registry
     * @param ExtensionAttributesFactory      $extensionFactory
     * @param AttributeValueFactory           $customAttributeFactory
     * @param Data                            $paymentData
     * @param ScopeConfigInterface            $scopeConfig
     * @param Logger                          $logger
     * @param TransactionRepositoryInterface  $transactionRepository
     * @param BuilderInterface                $transactionBuilder
     * @param Config                          $config
     * @param Curl                            $curl
     * @param JsonFactory                     $jsonFactory
     * @param AbstractResource|null           $resource
     * @param AbstractDb|null                 $resourceCollection
     * @param array                           $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        TransactionRepositoryInterface $transactionRepository,
        BuilderInterface $transactionBuilder,
        Config $config,
        Curl $curl,
        JsonFactory $jsonFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->jsonFactory = $jsonFactory;
        $this->transactionBuilder = $transactionBuilder;
        $this->config = $config;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Refund capture
     *
     * @param  InfoInterface|Payment|Object $payment
     * @param  float                        $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws InvalidTransitionException
     */
    public function refund(InfoInterface $payment, $amount)
    {
        $paymentInstance = $payment->getOrder()->getPayment();
        $orderId = $payment->getOrder()->getId();

        $transaction = $this->transactionRepository->getByTransactionType(
            Transaction::TYPE_CAPTURE,
            $orderId
        );

        if (!$this->_canRefund) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }

        $gradTotal = $payment->getOrder()->getGrandTotal();
        $totalRefunded =  $payment->getOrder()->getTotalRefunded();

        if ($this->formatNumber($gradTotal) == $this->formatNumber($totalRefunded)) {
            $resp = $this->sendPaymentCancelRequest($transaction->getTxnId());
            $resp = json_decode($resp);
            if ($resp->ResultCode == 10201) {
                $newTransaction = $this->transactionBuilder->setPayment($paymentInstance)
                    ->setOrder($payment->getOrder())
                    ->setTransactionId($transaction->getTxnId() . "_" . (string) time())
                    ->setFailSafe(true)
                    ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND);
    
                $payment->setIsTransactionClosed(true);
                $payment->addTransactionCommentsToOrder($newTransaction, 'order refunded');
                return $this;
            }
            throw new \Exception('Netgíró : ' . $resp->Message);

        } else {
            $resp = $this->sendPaymentChangeRequest(
                $transaction->getTxnId(),
                $gradTotal - $totalRefunded
            );
            $resp = json_decode($resp);
            if ($resp->ResultCode == 200) {
                $newTransaction = $this->transactionBuilder->setPayment($paymentInstance)
                    ->setOrder($payment->getOrder())
                    ->setTransactionId($resp->Result->TransactionId . "_" . (string) time())
                    ->setFailSafe(true)
                    ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND);
                    
                $payment->addTransactionCommentsToOrder($newTransaction, 'order refunded');
                return $this;
            }
            throw new \Exception('Netgíró : ' . $resp->Message);
        }
    }

    /**
     * Format a number to a string with two decimal places.
     *
     * @param  float $number
     * @return string
     */
    private function formatNumber($number)
    {
        return (string)number_format($number, 2);
    }

    /**
     * Send payment cancellation request.
     *
     * @param  int $transactionId
     * @return string
     */
    private function sendPaymentCancelRequest($transactionId)
    {

        $action = $this->config->getAction("payment/cancel");
        $appId = $this->config->getAppId();
        $secretKey = $this->config->getSecretKey();

        $postBody = json_encode(
            [
            'transactionId' => $transactionId,
            'description' => 'Refund via Magento',
            'cancelationFeeAmount' => 0
            ]
        );
        
        $nonce = (string) microtime(true) * 10000000;
        $signature = hash('sha256', $secretKey . $nonce . $action . $postBody);

        $this->curl->setHeaders(
            ['Content-Type' => 'application/json',
            'NETGIRO_APPKEY'=> $appId,
            'NETGIRO_NONCE' => $nonce,
            'NETGIRO_SIGNATURE' => $signature,
            ]
        );

        $this->curl->post($action, $postBody);
        return $this->curl->getBody();
    }

    /**
     * Send payment change request.
     *
     * @param  int   $transactionId
     * @param  float $amount
     * @return string
     */
    private function sendPaymentChangeRequest($transactionId, $amount)
    {
        $action = $this->config->getAction("payment/change");
        $appId = $this->config->getAppId();
        $secretKey = $this->config->getSecretKey();

        $postBody = json_encode(
            [
                "transactionId" => $transactionId,
                "message"=> "Order Changed in Magento2 store",
                //"referenceNumber"=> "38",
                "totalAmount"=> $amount,
                //"shippingAmount"=> 0,
                //"handlingAmount"=> 0,
                //"discountAmount"=> 0,
                "items"=> [
                  [
                    //"productNo"=> "",
                    //"name"=> "",
                    //"description"=> "",
                    "amount" => $amount,
                    "quantity"=> 1000, // TODO spyrja afhvertju þetta getur ekki verið 1.
                    "unitPrice" => $amount
                  ]
                ],
                //"currentTimeUtc"=> "YYYY-mm-ddThh:mm:ss.mmmZ",
                //"validToTimeUtc"=> "YYYY-mm-ddThh:mm:ss.mmmZ",
                //"description"=> "",
                //"ipAddress"=> ""
            ]
        );
        $nonce = (string) microtime(true) * 10000000;
        $signature = hash('sha256', $secretKey . $nonce . $action . $postBody);

        $this->curl->setHeaders(
            ['Content-Type' => 'application/json',
            'NETGIRO_APPKEY'=> $appId,
            'NETGIRO_NONCE' => $nonce,
            'NETGIRO_SIGNATURE' => $signature,
            ]
        );

        $this->curl->post($action, $postBody);
        
        return $this->curl->getBody();
    }
}
