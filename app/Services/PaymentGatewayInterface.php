<?php

namespace App\Services;

interface PaymentGatewayInterface
{
    public function charge(array $data): array; // proses pembayaran
    public function refund(string $transactionId, float $amount): array; // refund
    public function getTransactionStatus(string $transactionId): array; // cek status
}
