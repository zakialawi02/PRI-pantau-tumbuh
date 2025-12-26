<?php

namespace App\Services\PaymentGateway;

use App\Services\PaymentGatewayInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MidtransService implements PaymentGatewayInterface
{
    protected string $serverKey;
    protected ?string $clientKey;
    protected bool $isProduction;
    protected string $snapUrl;
    protected string $apiUrl;

    public function __construct()
    {
        $this->serverKey = (string) config('services.midtrans.server_key');
        $this->clientKey = config('services.midtrans.client_key');
        $this->isProduction = (bool) config('services.midtrans.is_production', false);

        if (empty($this->serverKey)) {
            throw new InvalidArgumentException('Midtrans server key is not configured.');
        }

        $this->snapUrl = (string) (config('services.midtrans.snap_url')
            ?? ($this->isProduction
                ? 'https://app.midtrans.com/snap/v1/transactions'
                : 'https://app.sandbox.midtrans.com/snap/v1/transactions'));

        $this->apiUrl = (string) (config('services.midtrans.api_url')
            ?? ($this->isProduction
                ? 'https://api.midtrans.com/v2'
                : 'https://api.sandbox.midtrans.com/v2'));
    }

    public function charge(array $data): array
    {
        try {
            $orderId = $data['payment_id'] ?? ('order-' . uniqid());
            $grossAmount = (int) round($data['price'] ?? 0);

            if ($grossAmount <= 0) {
                throw new InvalidArgumentException('Invalid payment amount for Midtrans charge.');
            }

            $payload = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $grossAmount,
                ],
                'item_details' => [
                    [
                        'id' => $orderId,
                        'price' => $grossAmount,
                        'quantity' => 1,
                        'name' => $data['description'] ?? 'Payment Order',
                    ],
                ],
                'customer_details' => [
                    'first_name' => $data['customer']['first_name'] ?? null,
                    'last_name' => $data['customer']['last_name'] ?? null,
                    'email' => $data['customer']['email'] ?? null,
                    'phone' => $data['customer']['phone'] ?? null,
                ],
                'callbacks' => [
                    'finish' => $data['return_url'] ?? url('/'),
                    'error' => $data['cancel_url'] ?? url('/'),
                    'pending' => $data['return_url'] ?? url('/'),
                ],
            ];

            $response = Http::withBasicAuth($this->serverKey, '')
                ->acceptJson()
                ->post($this->snapUrl, $payload);

            if ($response->failed()) {
                $body = $response->json();
                $message = $body['status_message'] ?? $response->body();
                throw new Exception('Midtrans charge failed: ' . $message, $response->status());
            }

            $result = $response->json();

            return [
                'status' => 'success',
                'transaction_id' => $orderId,
                'approval_url' => $result['redirect_url'] ?? null,
                'payment_token' => $result['token'] ?? null,
                'payment_data' => $result,
            ];
        } catch (Exception $exception) {
            Log::error('Midtrans charge error: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function refund(string $transactionId, float $price): array
    {
        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->acceptJson()
                ->post($this->apiUrl . '/' . $transactionId . '/refund', [
                    'amount' => (int) round($price),
                    'reason' => 'Refund request',
                ]);

            if ($response->failed()) {
                $body = $response->json();
                $message = $body['status_message'] ?? $response->body();
                throw new Exception('Midtrans refund failed: ' . $message, $response->status());
            }

            $body = $response->json();

            return [
                'status' => 'success',
                'transaction_status' => $body['transaction_status'] ?? null,
                'refund_key' => $body['refund_key'] ?? null,
                'refund_data' => $body,
            ];
        } catch (Exception $exception) {
            Log::error('Midtrans refund error: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function getTransactionStatus(string $transactionId): array
    {
        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->acceptJson()
                ->get($this->apiUrl . '/' . $transactionId . '/status');

            if ($response->failed()) {
                $body = $response->json();
                $message = $body['status_message'] ?? $response->body();
                throw new Exception('Midtrans status fetch failed: ' . $message, $response->status());
            }

            $body = $response->json();
            $transactionStatus = strtolower((string) ($body['transaction_status'] ?? ''));

            return [
                'status' => $transactionStatus,
                'is_success' => in_array($transactionStatus, ['capture', 'settlement'], true),
                'is_pending' => $transactionStatus === 'pending',
                'fraud_status' => $body['fraud_status'] ?? null,
                'payment_type' => $body['payment_type'] ?? null,
                'raw' => $body,
            ];
        } catch (Exception $exception) {
            Log::error('Midtrans transaction status error: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function capturePayment(string $transactionId): array
    {
        return [
            'status' => 'error',
            'message' => 'Capture is not supported for Midtrans Snap transactions.',
        ];
    }
}
