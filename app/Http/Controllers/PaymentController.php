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

        return view('pages.front.checkout', compact('data'));
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
            'end_date'         => Carbon::now()->addMonth(),
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
            'bank_name'      => $request->bank_name ?? 'bank transfer',
            'account_name'    => $request->account_name,
            'account_number'  => $request->account_number,
        ]);

        Cache::forget($request->order_id);

        return redirect()->route('admin.payment.show', $payment->id)
            ->with('success', 'Your order has been successfully placed. Please make payment.');
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
                ->editColumn('status', function ($data) {
                    $statusConfig = match ($data->status) {
                        'paid' => ['class' => 'bg-green-100 text-green-800', 'text' => 'Paid'],
                        'pending' => ['class' => 'bg-yellow-100 text-yellow-800', 'text' => 'Pending'],
                        'waiting_verification' => ['class' => 'bg-blue-100 text-blue-800', 'text' => 'Waiting Verification'],
                        'failed' => ['class' => 'bg-red-100 text-red-800', 'text' => 'Failed'],
                        'refunded' => ['class' => 'bg-gray-100 text-gray-800', 'text' => 'Refunded'],
                        'chargeback' => ['class' => 'bg-red-100 text-red-800', 'text' => 'Chargeback'],
                        default => ['class' => 'bg-gray-100 text-gray-800', 'text' => 'Unknown']
                    };
                    return '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ' . $statusConfig['class'] . '">' . $statusConfig['text'] . '</span>';
                })
                ->editColumn('payment_method', function ($data) {
                    return ucwords(str_replace('_', ' ', $data->payment_method ?? 'Manual'));
                })
                ->rawColumns(['status', 'action', 'amount', 'due_date'])
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

        return view('pages.dashboard.payment.payment-order', compact('payment'));
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
                'status' => 'active'
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
                'created_at' => $payment->created_at->format('Y-m-d H:i:s')
            ]
        ]);
    }
}
