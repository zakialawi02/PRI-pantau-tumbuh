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

class PaymentController extends Controller
{
    public function mapOrder(Request $request)
    {
        $request->validate([
            'geometry' => 'required',
            'name_feature' => 'required|string|max:255',
            'area_hectares' => 'required|numeric|min:0.1',
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        $timestamp = time();
        // Hitung harga total
        $pricePerHectare = $plan->price_per_hectare;
        $totalPrice      = $request->area_hectares * $pricePerHectare;
        $data = [
            'timestamp' => $timestamp,
            'order_id' => $request->id,
            'name_feature' => $request->name_feature,
            'geometry' => $request->geometry,
            'area_hectares' => $request->area_hectares,
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
            'id'               => Str::uuid(),
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
            'id'              => Str::uuid(),
            'subscription_id' => $subscription->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'phone'           => $request->phone,
            'amount'          => $cacheData['total_price'],
            'currency'        => $plan->currency,
            'status'          => 'pending',
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
        $payments = Payment::with('subscription', 'subscription.user')->get();
        // dd($payments);
        return view('pages.dashboard.payment.index', compact('payments'));
    }

    public function showPayment($payment)
    {
        $payment = Payment::with(['subscription.plan', 'subscription.fieldArea'])
            ->findOrFail($payment);

        return view('pages.dashboard.payment.payment-order', compact('payment'));
    }
}
