<?php

namespace App\Services;

interface PaymentGatewayInterface
{
    /**
     * Process a payment charge
     *
     * @param array $data Payment data including price, currency, description, etc.
     * @return array Result with status and transaction details
     */
    public function charge(array $data): array;

    /**
     * Refund a payment
     *
     * @param string $transactionId The transaction ID to refund
     * @param float $price The price to refund
     * @return array Result with status and refund details
     */
    public function refund(string $transactionId, float $price): array;

    /**
     * Get the status of a transaction
     *
     * @param string $transactionId The transaction ID to check
     * @return array Result with status and transaction details
     */
    public function getTransactionStatus(string $transactionId): array;

    /**
     * Capture a payment paypal transaction
     *
     * @param string $transactionId The transaction ID to capture
     * @return array Result with status and capture details
     */
    public function capturePayment(string $transactionId): array;
}
