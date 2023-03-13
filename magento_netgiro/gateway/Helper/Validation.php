<?php

namespace netgiro\gateway\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Validation
{

    /**
     * @var string
     */
    public $exceptionMessage = "";

    /**
     * @inheritDoc
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function validateResponse($order, $netgiroSignatureFromResponse, $orderId, $transactionID, $numberFormatted, $totalAmount, $statusId)
    {
        $testMode = $this->scopeConfig->getValue('payment/netgiro/test_mode');
        if ($testMode) {
            $secretKey = 'YCFd6hiA8lUjZejVcIf/LhRXO4wTDxY0JhOXvQZwnMSiNynSxmNIMjMf1HHwdV6cMN48NX3ZipA9q9hLPb9C1ZIzMH5dvELPAHceiu7LbZzmIAGeOf/OUaDrk2Zq2dbGacIAzU6yyk4KmOXRaSLi8KW8t3krdQSX7Ecm8Qunc/A=';
        } else {
            $secretKey = $this->scopeConfig->getValue('payment/netgiro/secret_key');
        }

        $netgiroSignature = $this->calculateNetgiroSignature((string) $secretKey, (string) $orderId, (string) $transactionID, (string) $numberFormatted, (string) $totalAmount, (string) $statusId);

        if ($netgiroSignature !== $netgiroSignatureFromResponse) {
            $this->exceptionMessage = "Netgiro signature error!";
            return false;
        }

        $orderExist = !empty($order->getEntityId()) ? true : false;

        if (!$orderExist) {
            $this->exceptionMessage = "Order doesn't exist!";
            return false;
        }

        $paymentMethod = $order->getPayment()->getMethod();

        if ($paymentMethod !== 'netgiro') {
            $this->exceptionMessage = "Invalid payment method!";
            return false;
        }

        return true;
    }

    private function calculateNetgiroSignature(string $secretKey, string $orderId, string $transactionID, string $numberFormatted, string $totalAmount, string $statusId): string
    {
        $valueForHash = $secretKey . $orderId . $transactionID . $numberFormatted . $totalAmount . $statusId;
        return hash('sha256', $valueForHash);
    }
}
