<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Payment;
use App\Models\FieldArea;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Yajra\DataTables\Facades\DataTables;
use App\Mail\OrderConfirmation;
use App\Mail\PaymentConfirmation;
use App\Services\PaymentGatewayFactory;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{

    public function index()
    {
        if (request()->ajax()) {
            $user = Auth::user();
            $query = Payment::with(['user']);

            // Role-based filtering
            if ($user->role === 'user') {
                // Users can only see their own payments
                $query->where('user_id', $user->id);
            }
            // superadmin and admin can see all payments (no additional filtering needed)

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('action', function ($data) use ($user) {
                    $actions = '';
                    if ($user->role === 'user') {
                        $actions = '<a href="' . route('admin.payment.show', $data->id) . '" class="inline-flex items-center px-2 py-1 text-xs text-white bg-secondary/80 rounded-full hover:bg-secondary/60 border border-transparent focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary" title="View Details">';
                        $actions .= '<i class="ri-eye-line mr-1"></i> View';
                        $actions .= '</a>';
                    } else if (in_array($user->role, ['superadmin'])) {
                        $actions .= '<button type="button" class="inline-flex items-center px-2 py-1 text-xs text-white bg-secondary/80 rounded-full hover:bg-secondary/60 border border-transparent focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary btn-payment-status" data-id="' . $data->id . '" title="View Details">';
                        $actions .= '<i class="ri-eye-line mr-1"></i> View';
                        $actions .= '</button>';

                        // Add Update Status button
                        $actions .= ' <button type="button" class="inline-flex items-center px-2 py-1 text-xs text-white bg-warning/80 rounded-full hover:bg-warning/60 border border-transparent focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-warning btn-update-status" data-id="' . $data->id . '" title="Update Status">';
                        $actions .= '<i class="ri-edit-line mr-1"></i> Update';
                        $actions .= '</button>';
                    }

                    return $actions;
                })
                ->addColumn('customer_name', function ($data) {
                    return $data->user->name ?? $data->name ?? '-';
                })
                ->addColumn('invoice_number', function ($data) {
                    return $data->invoice_number ? '#' . $data->invoice_number : '#' . substr($data->id, 0, 16);
                })
                ->editColumn('status', function ($data) {
                    return $data->checkAndMarkAsExpired;
                })
                ->editColumn('payment_method', function ($data) {
                    return ucwords(str_replace('_', ' ', $data->payment_method ?? 'Manual'));
                })
                ->rawColumns(['action', 'due_date'])
                ->removeColumn(['id', 'updated_at'])
                ->make(true);
        }

        $data = [
            'title' => 'Payment Management',
        ];

        return view('pages.dashboard.payment.index', compact('data'));
    }

    public function showPayment($payment)
    {
        $payment = Payment::with(['user'])->findOrFail($payment);

        $user = Auth::user();

        // If user role is 'user', they can only view their own payments
        if ($user->role === 'user') {
            if ($payment->user_id !== $user->id) {
                abort(403, 'Unauthorized access to this payment.');
            }
        }

        $data = [
            'title' => 'Payment Order',
        ];

        return view('pages.dashboard.payment.payment-order', compact('data', 'payment'));
    }

    public function getPaymentData($id)
    {
        try {
            // Additional authorization check
            $user = Auth::user();
            if (!in_array($user->role, ['superadmin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $payment = Payment::with(['user', 'verifier'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'payment' => [
                    'id' => $payment->id,
                    'invoice_number' => $payment->invoice_number,
                    'status' => $payment->checkAndMarkAsExpired,
                    'payment_proof' => $payment->proof_image,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'name' => $payment->name,
                    'email' => $payment->email,
                    'phone' => $payment->phone,
                    'payment_method' => $payment->payment_method,
                    'due_date' => $payment->due_date ? $payment->due_date->format('Y-m-d H:i:s') : null,
                    'paid_at' => $payment->paid_at ? $payment->paid_at->format('Y-m-d H:i:s') : null,
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                    'user' => $payment->user->only('id', 'name', 'email', 'phone'),
                    'verifier' => $payment->verifier ? $payment->verifier->only('id', 'name', 'email', 'phone', 'created_at', 'updated_at') : null,
                ]
            ], 200);
        } catch (ModelNotFoundException $e) {
            Log::error("Payment Model not found. Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error("An error occurred while retrieving payment data. Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving payment data',
                'error' => $e->getMessage()
            ], 500);
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

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated successfully',
            'payment' => $payment
        ]);
    }

    // Method to handle payment proof upload
    public function uploadProof(Request $request, $paymentId)
    {
        $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|numeric',
            'account_name' => 'required|string|max:100',
            'proof_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:724',
        ]);

        $payment = Payment::findOrFail($paymentId);

        // Check if payment is still pending and not expired
        if ($payment->status !== 'pending' || ($payment->due_date && now()->isAfter($payment->due_date))) {
            return redirect()->route('admin.payment.show', $payment->id)
                ->with('error', 'This payment cannot be updated.');
        }

        // Check if payment method is bank transfer or manual
        if (!in_array($payment->payment_method, ['bank_transfer', 'manual'])) {
            return redirect()->route('admin.payment.show', $payment->id)
                ->with('error', 'Payment proof upload is not available for this payment method.');
        }

        try {
            // Handle file upload
            if ($request->hasFile('proof_image')) {
                $file = $request->file('proof_image');
                $timestamp = now()->timestamp;
                $randomString = uniqid();
                $extension = $file->getClientOriginalExtension();
                $newFileName = $timestamp . '_' . $randomString . '.' . $extension;

                // Store file in storage
                $file->storeAs('payment_proofs', $newFileName, 'public');
                $path = 'storage/payment_proofs/' . $newFileName;
            }

            // Update payment with proof details
            $payment->update([
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'account_name' => $request->account_name,
                'proof_image' => $path ?? null,
                'status' => 'waiting_verification',
            ]);

            return redirect()->route('admin.payment.show', $payment->id)
                ->with('success', 'Payment proof uploaded successfully. Your payment is now waiting for verification.');
        } catch (Exception $e) {
            Log::error("Payment proof upload error: " . $e->getMessage());
            return redirect()->route('admin.payment.show', $payment->id)
                ->with('error', 'Failed to upload payment proof. Please try again.');
        }
    }

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



            // Buat Payment
            $payment = Payment::create([
                'user_id'         => $user->id,
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

            // Send order confirmation email
            try {
                Mail::to($user->email)->send(new OrderConfirmation($payment));
            } catch (Exception $e) {
                Log::error("Failed to send order confirmation email: " . $e->getMessage());
                // Continue with the process even if email fails
            }

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
                'description' => 'Payment for service',
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

                    // Send payment confirmation email for successful payments
                    try {
                        Mail::to($payment->email)->send(new PaymentConfirmation($payment));
                    } catch (Exception $e) {
                        Log::error("Failed to send payment confirmation email: " . $e->getMessage());
                        // Continue with the process even if email fails
                    }

                    return redirect()->route('admin.payment.show', $payment->id)
                        ->with('success', 'Payment completed successfully.');
                } else {
                    $payment->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);

                    return redirect()->route('admin.payment.show', $payment->id)
                        ->with('error', 'Payment failed or cancelled. ' . $paymentResult['status'] . ':  ' . $paymentResult['message']);
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

                    // Send payment confirmation email for successful payments
                    try {
                        Mail::to($payment->email)->send(new PaymentConfirmation($payment));
                    } catch (Exception $e) {
                        Log::error("Failed to send payment confirmation email: " . $e->getMessage());
                        // Continue with the process even if email fails
                    }

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
