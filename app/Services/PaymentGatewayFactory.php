<?php

namespace App\Services;

use InvalidArgumentException;
use App\Services\PaymentGateway\PaypalService;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentGatewayFactory
{
    public static function make(string $gateway): PaymentGatewayInterface
    {
        try {
            return match ($gateway) {
                'paypal' => new PaypalService(),
                // 'midtrans' => new MidtransService(),
                default => throw new InvalidArgumentException("Unsupported gateway: {$gateway}")
            };
        } catch (InvalidArgumentException $e) {
            Log::error("Payment gateway factory error: " . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            Log::error("Payment gateway initialization error: " . $e->getMessage());
            throw new InvalidArgumentException("Failed to initialize gateway: {$gateway}. " . $e->getMessage());
        }
    }
}
