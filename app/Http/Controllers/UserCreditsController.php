<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserCredit;
use App\Models\UserCreditHistory;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
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

        $previousCredits = (float) ($userCredit->credits ?? 0);
        $targetCredits = (float) $request->input('current_credits', $previousCredits);
        $newBalance = $targetCredits + (float) $request->credits;

        $userCredit->credits = $newBalance;
        $userCredit->save();

        $difference = $newBalance - $previousCredits;

        if (abs($difference) > 0) {
            $type = $difference > 0 ? 'increase' : 'decrease';
            UserCreditHistory::record(
                (string) $user->id,
                $type,
                abs($difference),
                $previousCredits,
                (float) $userCredit->credits,
                'Manual adjustment by ' . (Auth::user()->name ?? 'system'),
                [
                    'performed_by' => Auth::id(),
                    'adjustment_amount' => (float) $request->credits,
                    'submitted_current_value' => $targetCredits,
                    'difference' => $difference,
                ]
            );
        }

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
     * Display the credit purchase page for public/guest users.
     */
    public function purchasePublic()
    {
        // Get all plans that are shown to users and have credit points
        $plans = Plan::where('isShow', true)
            ->where('credit_points', '>', 0)
            ->orderBy('credit_points', 'asc')
            ->get();

        return view('pages.front.order.purchase-credits', compact('plans'));
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


    /**
     * Check user's current credits
     */
    public function checkUserCredits()
    {
        try {
            $user = Auth::user();

            $currentCredits = (float) ($user->current_credits ?? 0);

            return response()->json([
                'success' => true,
                'credits' => $currentCredits,
                'message' => 'Credit balance retrieved successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve credit balance.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function history(Request $request)
    {
        $authUser = Auth::user();
        $query = UserCreditHistory::with('user')->orderByDesc('created_at');

        $isSuperAdmin = $authUser && $authUser->role === 'superadmin';

        if (!$isSuperAdmin) {
            $query->where('user_id', $authUser->id);
        }

        if ($request->ajax()) {
            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('user_name', fn($history) => optional($history->user)->name)
                ->addColumn('user_email', fn($history) => optional($history->user)->email)
                ->addColumn('change', function ($history) {
                    $sign = $history->type === 'decrease' ? '-' : '+';
                    return $sign . number_format((float) $history->amount, 2);
                })
                ->addColumn('type_label', fn($history) => ucfirst($history->type))
                ->editColumn('balance_before', fn($history) => number_format((float) $history->balance_before, 2))
                ->editColumn('balance_after', fn($history) => number_format((float) $history->balance_after, 2))
                ->editColumn('description', fn($history) => $history->description ?? '-')
                ->editColumn('created_at', fn($history) => $history->created_at->format('d M Y H:i'))
                ->make(true);
        }

        return view('pages.dashboard.users.creditHistory', [
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }
}
