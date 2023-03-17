<?php

namespace netgiro\gateway\Helper;

use netgiro\gateway\Model\Config;

class Validation
{

    /**
     * @var string
     */
    public $exceptionMessage = "";

    /**
     * The Config instance.
     *
     * @var Config
     */
    private $config;

    /**
     * Class constructor.
     *
     * @param \Magento\Framework\App\Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Validate response.
     *
     * @param object $order
     * @param string $netgiroSignatureFromResponse
     * @param string $orderId
     * @param string $transactionID
     * @param string $numberFormatted
     * @param string $totalAmount
     * @param string $statusId
     * @return bool
     */
    public function validateResponse(
        $order,
        $netgiroSignatureFromResponse,
        $orderId,
        $transactionID,
        $numberFormatted,
        $totalAmount,
        $statusId
    ) {
        $secretKey = $this->config->getSecretKey();

        $netgiroSignature = $this->calculateNetgiroSignature(
            (string) $secretKey,
            (string) $orderId,
            (string) $transactionID,
            (string) $numberFormatted,
            (string) $totalAmount,
            (string) $statusId
        );

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

    /**
     * Calculate Netgiro signature.
     *
     * @param string $secretKey
     * @param string $orderId
     * @param string $transactionID
     * @param string $numberFormatted
     * @param string $totalAmount
     * @param string $statusId
     * @return string
     */
    private function calculateNetgiroSignature(
        string $secretKey,
        string $orderId,
        string $transactionID,
        string $numberFormatted,
        string $totalAmount,
        string $statusId
    ): string {
        $valueForHash = $secretKey . $orderId . $transactionID . $numberFormatted . $totalAmount . $statusId;
        return hash('sha256', $valueForHash);
    }
}
