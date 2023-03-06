<?php
namespace netgiro\gateway\Model\Payment;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\Order\Payment;


class Netgiro extends AbstractMethod
{

	protected $_code = 'netgiro';

	protected $_canRefund = true;
	protected $_isGateway = true;
	protected $_canRefundInvoicePartial = true;

	/**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;

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
	 * @param \Magento\Framework\Model\Context $context
	 * @param \Magento\Framework\Registry $registry
	 * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
	 * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
	 * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
	 * @param \Magento\Framework\HTTP\Client\Curl $curl
	 * @param \Magento\Payment\Helper\Data $paymentData
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	 * @param \Magento\Payment\Model\Method\Logger $logger
	 * @param \Magento\Framework\Module\ModuleListInterface $moduleList
	 * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
	 * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
	 * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
	 * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
	 * @param array $data
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
	 */
	public function __construct(
		\Magento\Framework\Model\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
		\Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
		\Magento\Payment\Helper\Data $paymentData,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Payment\Model\Method\Logger $logger,
		\Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
		BuilderInterface $transactionBuilder,
		\Magento\Framework\HTTP\Client\Curl $curl,
		\Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
		\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
		array $data = [],
	) {
        $this->transactionRepository = $transactionRepository;
		$this->curl = $curl;
		$this->scopeConfig = $scopeConfig;
		$this->jsonFactory = $jsonFactory;
		$this->transactionBuilder = $transactionBuilder;

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
     * @param InfoInterface|Payment|Object $payment
     * @param float $amount
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

		if( $this->formatNumber( $payment->getOrder()->getGrandTotal()) == $this->formatNumber($payment->getOrder()->getTotalRefunded()) ){
			$resp = $this->sendPaymentCancelRequest($transaction->getTxnId());
			$resp = json_decode($resp);
			if($resp->ResultCode == 10201){
				$newTransaction = $this->transactionBuilder->setPayment($paymentInstance)
					->setOrder($payment->getOrder())
					->setTransactionId($transaction->getTxnId() . "_" . (string) time())
					->setFailSafe(true)
					->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND);
	
				$payment->setIsTransactionClosed(true);
				$payment->addTransactionCommentsToOrder($newTransaction, 'order refunded');
				return $this;
			}
			throw new \Exception( 'Netgíró : ' . $resp->Message);

		} else {	
			$resp = $this->sendPaymentChangeRequest($transaction->getTxnId(), $payment->getOrder()->getGrandTotal() - $payment->getOrder()->getTotalRefunded());
			$resp = json_decode($resp);
			if($resp->ResultCode == 200){
				$newTransaction = $this->transactionBuilder->setPayment($paymentInstance)
					->setOrder($payment->getOrder())
					->setTransactionId( $resp->Result->TransactionId . "_" . (string) time())
					->setFailSafe(true)
					->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND);
					
				$payment->addTransactionCommentsToOrder($newTransaction, 'order refunded');
				return $this;
			}
			throw new \Exception( 'Netgíró : ' . $resp->Message);
		}
		
    }

	private function formatNumber($number){
		return (string)number_format($number, 2);
	}

	private function sendPaymentCancelRequest( $transactionId ) {
		$testMode = $this->scopeConfig->getValue('payment/netgiro/test_mode');
		if ($testMode) {
			$action = 'https://test.netgiro.is/api/payment/cancel';
			$appId = '881E674F-7891-4C20-AFD8-56FE2624C4B5';
			$secretKey = 'YCFd6hiA8lUjZejVcIf/LhRXO4wTDxY0JhOXvQZwnMSiNynSxmNIMjMf1HHwdV6cMN48NX3ZipA9q9hLPb9C1ZIzMH5dvELPAHceiu7LbZzmIAGeOf/OUaDrk2Zq2dbGacIAzU6yyk4KmOXRaSLi8KW8t3krdQSX7Ecm8Qunc/A=';
		} else {
			$action = 'https://securepay.netgiro.is/v1/api/payment/cancel';
			$appId = $this->scopeConfig->getValue('payment/netgiro/app_id');
			$secretKey = $this->scopeConfig->getValue('payment/netgiro/secret_key');
		}

		$postBody = json_encode([
			'transactionId' => $transactionId,
    		'description' => 'Refund via Magento',
    		'cancelationFeeAmount' => 0
		]);
		
		$nonce = (string) microtime(true) * 10000000;
		$signature = hash( 'sha256',$secretKey . $nonce . $action . $postBody);

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
	private function sendPaymentChangeRequest( $transactionId , $amount) {

		$testMode = $this->scopeConfig->getValue('payment/netgiro/test_mode');
		if ($testMode) {
			$action = 'https://test.netgiro.is/api/payment/change';
			$appId = '881E674F-7891-4C20-AFD8-56FE2624C4B5';
			$secretKey = 'YCFd6hiA8lUjZejVcIf/LhRXO4wTDxY0JhOXvQZwnMSiNynSxmNIMjMf1HHwdV6cMN48NX3ZipA9q9hLPb9C1ZIzMH5dvELPAHceiu7LbZzmIAGeOf/OUaDrk2Zq2dbGacIAzU6yyk4KmOXRaSLi8KW8t3krdQSX7Ecm8Qunc/A=';
		} else {
			$action = 'https://securepay.netgiro.is/v1/api/payment/change';
			$appId = $this->scopeConfig->getValue('payment/netgiro/app_id');
			$secretKey = $this->scopeConfig->getValue('payment/netgiro/secret_key');
		}

		$postBody = json_encode([
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
		$signature = hash( 'sha256', $secretKey . $nonce . $action . $postBody);

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