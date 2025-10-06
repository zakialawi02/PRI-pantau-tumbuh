<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserCredit;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Yajra\DataTables\Facades\DataTables;

class UserCreditsController extends Controller
{
    /**
     * Display a listing of user credits.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $userCredits = UserCredit::with('user')->whereHas('user')->get();

            return DataTables::of($userCredits)
                ->addIndexColumn()
                ->addColumn('action', function ($credit) {
                    return '<div class="flex gap-1">
                                 <button type="button" class="add-credits inline-flex items-center px-2 py-1 bg-secondary border border-transparent rounded-md font-semibold text-xs text-secondary-foreground uppercase tracking-widest hover:bg-secondary/80 focus:bg-secondary/80 active:bg-secondary/70 focus:outline-none focus:ring-2 focus:ring-secondary focus:ring-offset-2" data-id="' . $credit->user->id . '"><i class="ri-add-line"></i> Add</button>
                            </div>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('pages.dashboard.users.userCredit');
    }

    /**
     * Show the form.
     */
    public function showAddCreditsForm(User $user)
    {
        $user->load('credits');

        return response()->json([
            'user' => $user,
            'current_credits' => $user->credits->credits ?? 0
        ]);
    }

    /**
     * Add credits user.
     */
    public function addCredits(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'credits' => 'required|integer|min:0',
        ]);

        $userCredit = UserCredit::updateOrCreate(['user_id' => $user->id]);
        $userCredit->credits = $request->current_credits;
        $userCredit->credits += $request->credits;
        $userCredit->save();

        return response()->json([
            'success' => true,
            'message' => 'Credits added successfully!',
            'data' => [
                'user' => $user,
                'current_credits' => $user->fresh()->current_credits
            ]
        ]);
    }

    /**
     * Display the credit purchase page for users.
     */
    public function purchase()
    {
        // Get all plans that are shown to users and have credit points
        $plans = Plan::where('isShow', true)
            ->where('credit_points', '>', 0)
            ->orderBy('credit_points', 'asc')
            ->get();

        return view('pages.dashboard.users.purchaseCredits', compact('plans'));
    }

    public function orderCredit(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        // Create data array similar to the mapOrder method
        $timestamp = time();
        $data = [
            'timestamp' => $timestamp,
            'plan' => $plan,
            'price_currency' => $plan->currency,
            'price' => $plan->price,
        ];

        $keyCache = 'Checkout_' . $timestamp . '_' . Str::random(10) . '';
        Cache::put($keyCache, $data, now()->addHours(2));

        // Redirect to checkout with the cache key
        return redirect()->to('/checkout?id=' . $keyCache);
    }

    public function checkoutOrder(Request $request)
    {
        $id = $request->id;

        if ($id) {
            $cacheData = Cache::get($id);
            if ($cacheData) {
                $data = $cacheData;
            } else {

                return redirect()->route('admin.purchase-credits')->with('error', 'Application data not found or has expired.');
            }
        } else {
            return redirect()->route('admin.purchase-credits')->with('error', 'Application data not found');
        }

        $data['title'] = 'Checkout';

        return view('pages.front.order.checkout', compact('data'));
    }
}
