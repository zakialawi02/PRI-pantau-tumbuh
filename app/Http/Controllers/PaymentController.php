<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Payment;
use App\Models\FieldArea;
use Illuminate\Support\Str;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Yajra\DataTables\Facades\DataTables;
use App\Services\PaymentGatewayFactory;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function mapOrder(Request $request)
    {
        $request->validate([
            'geometry' => 'required',
            'name_feature' => 'required|string|max:255',
            'area_hectares' => 'required|numeric|min:0.01',
            'plan_id' => 'required|exists:plans,id',
        ]);
        $plan = Plan::findOrFail($request->plan_id);

        $timestamp = time();
        // Hitung harga total
        $pricePerHectare = $plan->price_per_hectare;
        $totalPrice      = number_format(($request->area_hectares / 10000) * $pricePerHectare, 2, '.', '');
        $data = [
            'timestamp' => $timestamp,
            'order_id' => $request->id,
            'name_feature' => $request->name_feature,
            'geometry' => $request->geometry,
            'area_hectares' => round(($request->area_hectares / 10000), 4),
            'plan' => $plan,
            'price_currency' => $plan->currency,
            'price_per_hectare' => $pricePerHectare,
            'total_price' => $totalPrice,
        ];

        $keyCache = 'Checkout_' . $timestamp . '_' . Str::random(10) . '';
        Cache::put($keyCache, $data, now()->addHours(2));

        return redirect()->to('/checkout?id=' . $keyCache);  // method checkout
    }

    public function checkoutOrder(Request $request)
    {
        $id = $request->id;

        if ($id) {
            $cacheData = Cache::get($id);
            if ($cacheData) {
                $data = $cacheData;
            } else {

                return redirect()->to('/app/imagery')->with('error', 'Application data not found or has expired.');
            }
        } else {
            return redirect()->to('/app/imagery')->with('error', 'Application data not found');
        }

        $data['title'] = 'Checkout';

        return view('pages.front.order.checkout', compact('data'));
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'payment_method' => 'nullable|string',
        ]);

        $cacheData = Cache::get($request->order_id);
        if (!$cacheData) {
            return redirect()->to('/app/imagery')->with('error', 'Checkout failed, data not found or expired.');
        }

        $user = Auth::user();

        try {
            $field = FieldArea::create([
                'user_id'   => $user->id,
                'name'      => $cacheData['name_feature'],
                'area_ha'   => $cacheData['area_hectares'],
                'geom'      => $cacheData['geometry'], // harus disesuaikan dengan tipe geometry (WKT/GeoJSON)
            ]);

            $plan = $cacheData['plan'];

            // Buat Subscription
            $subscription = Subscription::create([
                'user_id'          => $user->id,
                'field_area_id'    => $field->id,
                'plan_id'          => $plan->id,
                'price_per_hectare' => $plan->price_per_hectare,
                'total_price'      => $cacheData['total_price'],
                'start_date'       => Carbon::now(),
                // 'end_date'         => Carbon::now()->addMonth(),
                'status'           => 'awaiting_payment',
            ]);

            // Buat Payment
            $payment = Payment::create([
                'subscription_id' => $subscription->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'phone'           => $request->phone,
                'amount'          => $cacheData['total_price'],
                'currency'        => $plan->currency,
                'status'          => 'pending',
                'due_date'        => Carbon::now()->addDays(2), // 2 hari kedepan'
                'payment_method'  => $request->payment_method ?? 'manual',
                'bank_name'      => ($request->payment_method === 'bank_transfer') ? ($request->bank_name ?? 'bank transfer') : null,
                'account_name'    => $request->account_name,
                'account_number'  => $request->account_number,
            ]);

            Cache::forget($request->order_id);

            // Process payment if a gateway is selected
            $gatewayName = $request->input('payment_method');

            // Only process through gateway if it's not manual payment
            if ($gatewayName && $gatewayName !== 'manual' && $gatewayName !== 'bank_transfer') {
                try {
                    $gateway = PaymentGatewayFactory::make($gatewayName);

                    $paymentData = [
                        'amount' => $cacheData['total_price'],
                        'currency' => $plan->currency,
                        'description' => 'Payment for ' . $plan->name . ' plan',
                        'return_url' => route('admin.payment.callback', ['gateway' => $gatewayName]) . '?paymentId=' . $payment->id,
                        'cancel_url' => route('admin.payment.show', $payment->id),
                        'payment_id' => $payment->id // Pass payment ID for callback
                    ];

                    $result = $gateway->charge($paymentData);
                    // dd($result);
                    if ($result['status'] === 'success') {
                        // Update payment with transaction details
                        $payment->update([
                            'transaction_ref' => $result['transaction_id'],
                            'status' => 'pending' // Still pending until confirmed
                        ]);

                        // Redirect to PayPal
                        if (isset($result['approval_url'])) {
                            return redirect()->away($result['approval_url']);
                        }

                        // Fallback if no approval URL
                        return redirect()->route('admin.payment.show', $payment->id)
                            ->with('success', 'Payment processed successfully. Please complete the payment on PayPal.');
                    } else {
                        // Log error
                        Log::error("PayPal payment processing error: " . ($result['message'] ?? 'Unknown error'));

                        return redirect()->route('admin.payment.show', $payment->id)
                            ->with('error', 'Failed to process PayPal payment. Please try again.');
                    }
                } catch (Exception $e) {
                    Log::error("Payment processing error: " . $e->getMessage());
                    // Continue with manual payment process
                }
            }

            return redirect()->route('admin.payment.show', $payment->id)
                ->with('success', 'Your order has been successfully placed. Please make payment.');
        } catch (Exception $e) {
            Log::error("Checkout error: " . $e->getMessage());
            return redirect()->to('/app/imagery')->with('error', 'An error occurred during checkout. Please try again.');
        }
    }

    public function index()
    {

        if (request()->ajax()) {
            $user = Auth::user();
            $query = Payment::with(['subscription', 'subscription.user', 'subscription.plan']);

            // Role-based filtering
            if ($user->role === 'user') {
                // Users can only see their own payments
                $query->whereHas('subscription', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }
            // superadmin and admin can see all payments (no additional filtering needed)

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('action', function ($data) use ($user) {
                    $actions = '';
                    if ($user->role === 'user') {
                        $actions = '<a href="' . route('admin.payment.show', $data->id) . '" class="inline-flex items-center px-2 py-1 text-sm font-medium text-background bg-info border border-transparent rounded-md hover:bg-info/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-info" title="View Details">';
                        $actions .= '<i class="ri-eye-fill"></i>';
                        $actions .= '</a>';
                    } else if (in_array($user->role, ['superadmin', 'admin'])) {
                        $actions .= ' <button type="button" class="inline-flex items-center px-2 py-1 ml-1 text-sm font-medium text-background bg-info border border-transparent rounded-md hover:bg-info/80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-info payment-status" data-id="' . $data->id . '" data-modal-target="payment-modal" data-modal-toggle="payment-modal" title="Update Status">';
                        $actions .= '<i class="ri-eye-fill"></i>';
                        $actions .= '</button>';
                    }

                    return $actions;
                })
                ->addColumn('customer_name', function ($data) {
                    return $data->subscription->user->name ?? $data->name ?? '-';
                })
                ->addColumn('invoice_number', function ($data) {
                    return '#' . substr($data->id, 0, 8);
                })
                ->editColumn('amount', function ($data) {
                    return '<span class="font-medium text-base-content">' . number_format($data->amount, 2) . ' ' . strtoupper($data->currency) . '</span>';
                })
                ->editColumn('payment_method', function ($data) {
                    return ucwords(str_replace('_', ' ', $data->payment_method ?? 'Manual'));
                })
                ->rawColumns(['action', 'amount', 'due_date'])
                ->removeColumn(['id', 'subscription_id', 'updated_at'])
                ->make(true);
        }

        $data = [
            'title' => 'Payment Management',
        ];

        return view('pages.dashboard.payment.index', compact('data'));
    }

    public function showPayment($payment)
    {
        $payment = Payment::with(['subscription.plan', 'subscription.fieldArea', 'subscription.user'])
            ->findOrFail($payment);

        $user = Auth::user();

        // If user role is 'user', they can only view their own payments
        if ($user->role === 'user') {
            if ($payment->subscription->user_id !== $user->id) {
                abort(403, 'Unauthorized access to this payment.');
            }
        }

        $data = [
            'title' => 'Payment Order',
        ];

        return view('pages.dashboard.payment.payment-order', compact('data', 'payment'));
    }

    /**
     * Process PayPal payment for an existing payment order
     */
    public function processPayPalPayment(Request $request, $paymentId)
    {
        try {
            $payment = Payment::findOrFail($paymentId);

            // Check if payment is already paid
            if ($payment->status === 'paid') {
                return redirect()->route('admin.payment.show', $payment->id)
                    ->with('error', 'This payment has already been processed.');
            }

            // Check if payment method is PayPal
            if ($payment->payment_method !== 'paypal') {
                return redirect()->route('admin.payment.show', $payment->id)
                    ->with('error', 'This payment is not set up for PayPal processing.');
            }

            // Initialize PayPal service
            $gateway = PaymentGatewayFactory::make('paypal');

            $paymentData = [
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'description' => 'Payment for ' . $payment->subscription->plan->name . ' plan',
                'return_url' => route('admin.payment.callback', ['gateway' => 'paypal']) . '?paymentId=' . $payment->id,
                'cancel_url' => route('admin.payment.show', $payment->id),
                'payment_id' => $payment->id // Pass payment ID for callback
            ];

            $result = $gateway->charge($paymentData);

            if ($result['status'] === 'success') {
                // Update payment with transaction details
                $payment->update([
                    'transaction_ref' => $result['transaction_id'],
                    'status' => 'pending' // Still pending until confirmed
                ]);

                // Redirect to PayPal
                if (isset($result['approval_url'])) {
                    return redirect()->away($result['approval_url']);
                }

                // Fallback if no approval URL
                return redirect()->route('admin.payment.show', $payment->id)
                    ->with('success', 'Payment processed successfully. Please complete the payment on PayPal.');
            } else {
                // Log error
                Log::error("PayPal payment processing error: " . ($result['message'] ?? 'Unknown error'));

                return redirect()->route('admin.payment.show', $payment->id)
                    ->with('error', 'Failed to process PayPal payment. Please try again.');
            }
        } catch (Exception $e) {
            Log::error("PayPal payment processing error: " . $e->getMessage());

            return redirect()->route('admin.payment.show', $payment->id)
                ->with('error', 'An error occurred while processing your PayPal payment. Please try again.');
        }
    }

    public function updateStatus(Request $request, $id)
    {
        // Additional authorization check
        $user = Auth::user();
        if (!in_array($user->role, ['superadmin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,waiting_verification,paid,failed,refunded,chargeback',
        ]);

        $payment = Payment::findOrFail($id);
        $oldStatus = $payment->status;

        $payment->update([
            'status' => $request->status,
            'verified_at' => $request->status === 'paid' ? now() : null,
            'verified_by' => $request->status === 'paid' ? Auth::id() : null,
            'paid_at' => $request->status === 'paid' ? now() : $payment->paid_at,
        ]);

        // Update subscription status if payment is paid
        if ($request->status === 'paid' && $oldStatus !== 'paid') {
            $payment->subscription->update([
                // 'start_date' => now(),
                'status' => 'active',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated successfully',
            'payment' => $payment
        ]);
    }

    public function getPaymentData($id)
    {
        // Additional authorization check
        $user = Auth::user();
        if (!in_array($user->role, ['superadmin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $payment = Payment::findOrFail($id);

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'name' => $payment->name,
                'email' => $payment->email,
                'phone' => $payment->phone,
                'payment_method' => $payment->payment_method,
                'paid_at' => $payment->paid_at ? $payment->paid_at->format('Y-m-d H:i:s') : null,
                'created_at' => $payment->created_at->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Handle payment gateway callbacks
     */
    public function handleGatewayCallback(Request $request, string $gateway)
    {
        try {
            // Get payment ID from request
            $paymentId = $request->get('paymentId') ?? $request->get('id');

            if (!$paymentId) {
                return redirect()->route('admin.payment.index')->with('error', 'Invalid payment request.');
            }

            $payment = Payment::findOrFail($paymentId);
            $gatewayService = PaymentGatewayFactory::make($gateway);

            // For PayPal, we need to capture the payment first
            if ($gateway === 'paypal') {
                $paymentResult = $gatewayService->capturePayment($payment->transaction_ref);

                if ($paymentResult['status'] === 'success') {
                    // Update payment status
                    $payment->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Update subscription
                    $payment->subscription->update([
                        'status' => 'active',
                        'updated_at' => now(),
                    ]);

                    return redirect()->route('admin.payment.show', $payment->id)
                        ->with('success', 'Payment completed successfully.');
                } else {
                    $payment->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);

                    return redirect()->route('admin.payment.show', $payment->id)
                        ->with('error', 'Payment failed or cancelled.');
                }
            } else {
                // For other gateways, use the existing getTransactionStatus method
                $gatewayService = PaymentGatewayFactory::make($gateway);
                $status = $gatewayService->getTransactionStatus($payment->transaction_ref);

                if (strtolower($status['status']) === 'completed') {
                    // Update payment status
                    $payment->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Update subscription
                    $payment->subscription->update([
                        'status' => 'active',
                        'updated_at' => now(),
                    ]);

                    return redirect()->route('admin.payment.show', $payment->id)
                        ->with('success', 'Payment completed successfully.');
                } else {
                    $payment->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);

                    return redirect()->route('admin.payment.show', $payment->id)
                        ->with('error', 'Payment failed or cancelled.');
                }
            }
        } catch (Exception $e) {
            Log::error("Payment callback error: " . $e->getMessage());
            return redirect()->route('admin.payment.index')->with('error', 'Payment processing error.');
        }
    }
}
