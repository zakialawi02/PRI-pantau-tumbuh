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

    /**
     * Charge/Create payment through PayPal
     */
    public function charge(array $data): array
    {
        try {
            $this->paypal->setApiCredentials(config('paypal'));
            $this->paypal->getAccessToken();

            // Create the order directly
            $response = $this->paypal->createOrder([
                "intent" => "CAPTURE",
                "purchase_units" => [
                    [
                        "amount" => [
                            "value" => number_format($data['amount'], 2, '.', ''),
                            "currency_code" => config('paypal.currency', 'USD')
                        ],
                    ]
                ],
                "application_context" => [
                    "cancel_url" => $data['cancel_url'] ?? url('/'),
                    "return_url" => $data['return_url'] ?? url('/')
                ]
            ]);

            if (isset($response['status']) && $response['status'] === 'CREATED') {
                // Get the approval URL
                $approvalUrl = null;
                if (isset($response['links'])) {
                    foreach ($response['links'] as $link) {
                        if ($link['rel'] === 'approve') {
                            $approvalUrl = $link['href'];
                            break;
                        }
                    }
                }
                // dd($response);
                return [
                    'status' => 'success',
                    'transaction_id' => $response['id'],
                    'approval_url' => $approvalUrl,
                    'payment_data' => $response
                ];
            } else {
                throw new Exception('Failed to create PayPal order: ' . ($response['message'] ?? 'Unknown error'));
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
     * Refund a payment
     */
    public function refund(string $transactionId, float $amount): array
    {
        try {
            // For refund, we need to capture the payment first if it's not already captured
            $captureResponse = $this->paypal->capturePaymentOrder($transactionId);

            if (isset($captureResponse['status']) && $captureResponse['status'] === 'COMPLETED') {
                // Now we can refund
                $captureId = $captureResponse['purchase_units'][0]['payments']['captures'][0]['id'];

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
                throw new Exception('Failed to capture payment for refund: ' . ($captureResponse['message'] ?? 'Unknown error'));
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
            $this->paypal->setApiCredentials(config('paypal'));
            $this->paypal->getAccessToken();

            $response = $this->paypal->showOrderDetails($transactionId);
            // dd($response);
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
