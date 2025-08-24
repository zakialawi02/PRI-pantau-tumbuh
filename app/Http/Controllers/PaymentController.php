<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    public function beforeCheckout(Request $request)
    {
        $timestamp = time();
        $data = [
            'timestamp' => $timestamp,
            'geometry' => $request->geometry,
            'name' => $request->nama_feature,
            'area_hectares' => $request->area_hectares,
        ];

        $keyCache = 'Checkout_' . $timestamp . '_' . Str::random(10) . '';
        Cache::put($keyCache, $data, now()->addHours(2));

        return redirect()->to('/checkout?id=' . $keyCache);  // method checkout
    }

    public function checkout(Request $request)
    {
        $id = $request->id;

        if ($id) {
            $cacheData = Cache::get($id);
            if ($cacheData) {
                $data = $cacheData;
            } else {

                return redirect()->to('/buy-citra')->with('error', 'Data permohonan tidak ditemukan atau sudah kadaluarsa.');
            }
        } else {
            return redirect()->to('/buy-citra')->with('error', 'Data tidak ditemukan');
        }

        return view('pages.front.checkout', compact('data'));
    }
}
