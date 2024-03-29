<?php declare(strict_types = 1);

namespace netgiro\gateway\Controller\Form;

use netgiro\gateway\Helper\Data;
use netgiro\gateway\Model\Config;
use Magento\Checkout\Model\Session;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

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

    /**
     * The Config instance.
     *
     * @var Config
     */
    private $config;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param Session $checkoutSession
     * @param Data $data
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Config $config,
        Session $checkoutSession,
        Data $data
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->data = $data;
        $this->config = $config;
    }

    /**
     * Execute the action.
     *
     * @return \Magento\Framework\Controller\Result\Json
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {

        $action = $this->config->getAction("securepay");
        $appId = $this->config->getAppId();
        $secretKey = $this->config->getSecretKey();

        $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
        $responseUrl = $baseUrl . $this->scopeConfig->getValue('payment/netgiro/response_url');
        $callbackUrl = $baseUrl . $this->scopeConfig->getValue('payment/netgiro/callback_url');

        $orderId = $this->checkoutSession->getLastRealOrder()->getId();
        $totalAmount = (int) $this->checkoutSession->getLastRealOrder()->getGrandTotal();

        $signature = $this->calculateSignature($secretKey, (string) $orderId, (string) $totalAmount, $appId);
        $post_data = [
            'action' => $action,
            'fields' =>  [
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
            ]
        ];
        $result = $this->jsonFactory->create();

        return $result->setData($post_data);
    }

    /**
     * Calculate signature for Netgiro request
     *
     * @param string $secret Secret key for the Netgiro account
     * @param string $orderId Order ID
     * @param string $totalAmount Total amount of the order
     * @param string $appId Application ID for the Netgiro account
     * @return string Signature calculated using sha256 hash algorithm
     */
    private function calculateSignature(string $secret, string $orderId, string $totalAmount, string $appId): string
    {
        $valueForHash = $secret . $orderId . $totalAmount . $appId;
        return hash('sha256', $valueForHash);
    }
}
