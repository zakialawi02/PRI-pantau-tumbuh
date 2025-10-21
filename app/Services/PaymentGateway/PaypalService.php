<?php

namespace App\Services\PaymentGateway;

use App\Services\PaymentGatewayInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaypalService implements PaymentGatewayInterface
{
    private $paypal;

    public function __construct()
    {
        // Initialize PayPal client with proper configuration for version 3.x
        $config = config('paypal');
        $this->paypal = new PayPalClient($config);
        $this->paypal->setCurrency($config['currency'] ?? 'USD');
    }

    private function prepareForApiCall(): void
    {
        $this->paypal->setApiCredentials(config('paypal'));
        $this->paypal->getAccessToken();
    }

    /**
     * Charge/Create payment through PayPal
     * This method now creates the order AND captures it immediately
     */
    public function charge(array $data): array
    {
        try {
            $currencyCode = strtoupper($data['currency'] ?? config('paypal.currency', 'USD'));

            $this->paypal->setCurrency($currencyCode);
            $this->prepareForApiCall();

            // Create the order directly
            $responseOrder = $this->paypal->createOrder([
                "intent" => "CAPTURE",
                "purchase_units" => [
                    [
                        "amount" => [
                            "value" => number_format($data['amount'], 2, '.', ''),
                            "currency_code" => $currencyCode
                        ],
                    ]
                ],
                "application_context" => [
                    "cancel_url" => $data['cancel_url'] ?? url('/'),
                    "return_url" => $data['return_url'] ?? route('admin.payment.callback', ['gateway' => 'paypal']) . '?paymentId=' . ($data['payment_id'] ?? '')
                ]
            ]);

            if (isset($responseOrder['status']) && $responseOrder['status'] === 'CREATED') {
                // Get the approval URL
                $approvalUrl = null;
                $captureUrl = null;
                if (isset($responseOrder['links'])) {
                    foreach ($responseOrder['links'] as $link) {
                        if ($link['rel'] === 'approve') {
                            $approvalUrl = $link['href'];
                        }
                        if ($link['rel'] === 'capture') {
                            $captureUrl = $link['href'];
                        }
                    }
                }

                // For PayPal, we return the approval URL for the user to complete payment
                // The actual capture will happen in the callback
                return [
                    'status' => 'success',
                    'transaction_id' => $responseOrder['id'],
                    'approval_url' => $approvalUrl,
                    'capture_url' => $captureUrl,
                    'payment_data' => $responseOrder
                ];
            } else {
                throw new Exception('Failed to create PayPal order: ' . ($responseOrder['message'] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
            Log::error('PayPal charge error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Capture a previously created payment order
     */
    public function capturePayment(string $transactionId): array
    {
        try {
            $this->prepareForApiCall();

            $response = $this->paypal->capturePaymentOrder($transactionId);

            if (isset($response['status']) && $response['status'] === 'COMPLETED') {
                return [
                    'status' => 'success',
                    'transaction_id' => $response['id'],
                    'payer' => $response['payer'] ?? null,
                    'payment_data' => $response
                ];
            } else {
                // Log the full response for debugging
                Log::error('PayPal capture failed response: ' . json_encode($response));
                throw new Exception('Failed to capture PayPal payment: ' . ($response['error']['name'] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
            Log::error('PayPal capture error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Refund a payment
     */
    public function refund(string $transactionId, float $amount): array
    {
        try {
            // First, we need to get the order details to find the capture ID
            $orderDetails = $this->paypal->showOrderDetails($transactionId);

            if (isset($orderDetails['status']) && $orderDetails['status'] === 'COMPLETED') {
                // Get the capture ID from the order details
                $captureId = $orderDetails['purchase_units'][0]['payments']['captures'][0]['id'];

                // Refund the captured payment with correct method signature
                $refundResponse = $this->paypal->refundCapturedPayment(
                    $captureId,
                    '', // invoice_id (optional)
                    $amount,
                    'Refund for order' // note
                );

                if (isset($refundResponse['status']) && $refundResponse['status'] === 'COMPLETED') {
                    return [
                        'status' => 'success',
                        'refunded_amount' => $amount,
                        'refund_id' => $refundResponse['id'],
                        'refund_data' => $refundResponse
                    ];
                } else {
                    throw new Exception('Failed to process refund: ' . ($refundResponse['message'] ?? 'Unknown error'));
                }
            } else {
                throw new Exception('Failed to get order details for refund: ' . ($orderDetails['message'] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
            Log::error('PayPal refund error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get transaction status
     */
    public function getTransactionStatus(string $transactionId): array
    {
        try {
            $this->prepareForApiCall();

            $response = $this->paypal->showOrderDetails($transactionId);

            if (isset($response['status'])) {
                return [
                    'status' => $response['status'],
                    'transaction_id' => $response['id'],
                    'payment_data' => $response
                ];
            } else {
                throw new Exception('Failed to get order details: ' . ($response['message'] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
            Log::error('PayPal transaction status error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
