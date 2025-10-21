<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserCredit;
use App\Models\UserCreditHistory;
use App\Services\CreditService;
use App\Services\CurrencyConverter;
use App\Services\UserRegionService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class UserCreditsController extends Controller
{
    protected CreditService $creditService;
    protected CurrencyConverter $currencyConverter;
    protected UserRegionService $userRegionService;

    public function __construct(
        CreditService $creditService,
        CurrencyConverter $currencyConverter,
        UserRegionService $userRegionService
    ) {
        $this->creditService = $creditService;
        $this->currencyConverter = $currencyConverter;
        $this->userRegionService = $userRegionService;
    }

    public function history(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user && $user->role === 'superadmin';

        if ($request->ajax()) {
            if ($isSuperAdmin) {
                $histories = UserCreditHistory::with(['user', 'performedBy'])
                    ->orderByDesc('created_at');

                return DataTables::of($histories)
                    ->addIndexColumn()
                    ->addColumn('user_name', function (UserCreditHistory $history) {
                        return optional($history->user)->name ?? '-';
                    })
                    ->addColumn('user_email', function (UserCreditHistory $history) {
                        return optional($history->user)->email ?? '-';
                    })
                    ->addColumn('performed_by_name', function (UserCreditHistory $history) {
                        return optional($history->performedBy)->name ?? __('System');
                    })
                    ->addColumn('type_badge', function (UserCreditHistory $history) {
                        $label = ucfirst($history->type);
                        $classes = $history->type === 'credit'
                            ? 'bg-success/10 text-success border border-success/20'
                            : 'bg-error/10 text-error border border-error/20';

                        return sprintf('<span class="%s inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold">%s</span>', $classes, e($label));
                    })
                    ->editColumn('amount', function (UserCreditHistory $history) {
                        return Number::format($history->amount, 2, locale: app()->getLocale());
                    })
                    ->editColumn('balance_before', function (UserCreditHistory $history) {
                        return Number::format($history->balance_before, 2, locale: app()->getLocale());
                    })
                    ->editColumn('balance_after', function (UserCreditHistory $history) {
                        return Number::format($history->balance_after, 2, locale: app()->getLocale());
                    })
                    ->editColumn('created_at', function (UserCreditHistory $history) {
                        return $history->created_at?->isoFormat('MMM D, YYYY HH:mm');
                    })
                    ->addColumn('reference', function (UserCreditHistory $history) {
                        if ($history->reference_type) {
                            $type = ucfirst(str_replace('_', ' ', $history->reference_type));
                            $id = $history->reference_id ?? '-';

                            return sprintf('%s #%s', e($type), e($id));
                        }

                        return '-';
                    })
                    ->editColumn('description', function (UserCreditHistory $history) {
                        return $history->description ?: '-';
                    })
                    ->rawColumns(['type_badge'])
                    ->make(true);
            }

            $histories = UserCreditHistory::where('user_id', $user->id)
                ->orderByDesc('created_at');

            return DataTables::of($histories)
                ->addIndexColumn()
                ->addColumn('type_badge', function (UserCreditHistory $history) {
                    $label = ucfirst($history->type);
                    $classes = $history->type === 'credit'
                        ? 'bg-success/10 text-success border border-success/20'
                        : 'bg-error/10 text-error border border-error/20';

                    return sprintf('<span class="%s inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold">%s</span>', $classes, e($label));
                })
                ->editColumn('amount', function (UserCreditHistory $history) {
                    return Number::format($history->amount, 2, locale: app()->getLocale());
                })
                ->editColumn('balance_before', function (UserCreditHistory $history) {
                    return Number::format($history->balance_before, 2, locale: app()->getLocale());
                })
                ->editColumn('balance_after', function (UserCreditHistory $history) {
                    return Number::format($history->balance_after, 2, locale: app()->getLocale());
                })
                ->editColumn('created_at', function (UserCreditHistory $history) {
                    return $history->created_at?->isoFormat('MMM D, YYYY HH:mm');
                })
                ->addColumn('reference', function (UserCreditHistory $history) {
                    if ($history->reference_type) {
                        $type = ucfirst(str_replace('_', ' ', $history->reference_type));
                        $id = $history->reference_id ?? '-';

                        return sprintf('%s #%s', e($type), e($id));
                    }

                    return '-';
                })
                ->editColumn('description', function (UserCreditHistory $history) {
                    return $history->description ?: '-';
                })
                ->rawColumns(['type_badge'])
                ->make(true);
        }

        return view($isSuperAdmin ? 'pages.dashboard.users.creditHistory' : 'pages.dashboard.user.credit-history');
    }

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
            'current_credits' => 'nullable|numeric|min:0',
            'credits' => 'required|numeric|min:0',
        ]);

        $performedBy = Auth::id();

        try {
            [$finalBalance, $change] = DB::transaction(function () use ($request, $user, $performedBy) {
                $userCredit = $user->credits()->lockForUpdate()->first();

                if (!$userCredit) {
                    $userCredit = $user->credits()->create([
                        'credits' => 0,
                    ]);
                }

                $initialBalance = (float) $userCredit->credits;
                $submittedBase = $request->filled('current_credits')
                    ? max(0, (float) $request->input('current_credits'))
                    : $initialBalance;
                $additional = max(0, (float) $request->input('credits'));

                $finalBalance = round($submittedBase + $additional, 2);
                $change = $finalBalance - $initialBalance;

                if ($finalBalance < 0) {
                    throw new \InvalidArgumentException(__('The resulting credit balance cannot be negative.'));
                }

                $userCredit->credits = $finalBalance;
                $userCredit->save();

                if (abs($change) > 0.00001) {
                    $type = $change >= 0 ? 'credit' : 'debit';
                    $description = $type === 'credit'
                        ? __('Manual credit adjustment with administrator')
                        : __('Manual credit deduction with administrator');

                    $this->creditService->logHistory(
                        $user,
                        $type,
                        abs($change),
                        $initialBalance,
                        $finalBalance,
                        $description,
                        [
                            'submitted_current_credits' => (float) $request->input('current_credits', $initialBalance),
                            'submitted_adjustment' => $additional,
                            'context' => 'admin_manual_adjustment',
                        ],
                        $performedBy,
                        'manual_adjustment',
                        $performedBy ? (string) $performedBy : null
                    );
                }

                return [$finalBalance, $change];
            });

            $user->refresh();

            $message = __('Credits updated successfully.');
            if ($change > 0) {
                $message = __('Credits added successfully!');
            } elseif ($change < 0) {
                $message = __('Credits deducted successfully.');
            } elseif (abs($change) <= 0.00001) {
                $message = __('No credit changes were made.');
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'user' => $user,
                    'current_credits' => $finalBalance,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to adjust user credits', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to update credits: :message', ['message' => $e->getMessage()]),
            ], 422);
        }
    }

    /**
     * Display the credit purchase page for public/guest users.
     */
    public function purchasePublic(Request $request)
    {
        $countryCode = $this->userRegionService->getCountryCode($request);
        $targetCurrency = $this->determineCurrency($countryCode);

        $plans = Plan::where('isShow', true)
            ->where('credit_points', '>', 0)
            ->orderBy('credit_points', 'asc')
            ->get()
            ->map(fn (Plan $plan) => $this->applyCurrencyPreference($plan, $targetCurrency));

        return view('pages.front.order.purchase-credits', [
            'plans' => $plans,
            'activeCurrency' => $targetCurrency,
        ]);
    }

    /**
     * Display the credit purchase page for users.
     */
    public function purchase(Request $request)
    {
        $countryCode = $this->userRegionService->getCountryCode($request);
        $targetCurrency = $this->determineCurrency($countryCode);

        $plans = Plan::where('isShow', true)
            ->where('credit_points', '>', 0)
            ->orderBy('credit_points', 'asc')
            ->get()
            ->map(fn (Plan $plan) => $this->applyCurrencyPreference($plan, $targetCurrency));

        return view('pages.dashboard.users.purchaseCredits', [
            'plans' => $plans,
            'activeCurrency' => $targetCurrency,
        ]);
    }

    public function orderCredit(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        $countryCode = $this->userRegionService->getCountryCode($request);
        $targetCurrency = $this->determineCurrency($countryCode);
        $plan = $this->applyCurrencyPreference($plan, $targetCurrency);

        // Create data array similar to the mapOrder method
        $timestamp = time();
        $data = [
            'timestamp' => $timestamp,
            'plan' => $plan,
            'price_currency' => $plan->display_currency ?? $plan->currency,
            'price' => $plan->display_price ?? $plan->price,
            'country_code' => $countryCode,
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

        $countryCode = $this->userRegionService->getCountryCode($request);
        $targetCurrency = $this->determineCurrency($countryCode);
        $isIndonesia = $this->userRegionService->isIndonesia($countryCode);

        if (isset($data['plan']) && $data['plan'] instanceof Plan) {
            $plan = $this->applyCurrencyPreference($data['plan'], $targetCurrency);
            $data['plan'] = $plan;
            $data['price'] = $plan->display_price ?? $plan->price;
            $data['price_currency'] = $plan->display_currency ?? $plan->currency;
        } else {
            [$convertedPrice, $convertedCurrency] = $this->currencyConverter->convert(
                (float) ($data['price'] ?? 0),
                $data['price_currency'] ?? $this->currencyConverter->getDefaultCurrency(),
                $targetCurrency
            );

            $data['price'] = $convertedPrice;
            $data['price_currency'] = $convertedCurrency;
        }

        $data['title'] = 'Checkout';

        $paymentPreferences = [
            'show_bank_transfer' => $isIndonesia,
            'show_paypal' => !$isIndonesia,
            'default_method' => $isIndonesia ? 'bank_transfer' : 'paypal',
        ];

        return view('pages.front.order.checkout', [
            'data' => $data,
            'paymentPreferences' => $paymentPreferences,
        ]);
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

    protected function applyCurrencyPreference(Plan $plan, string $targetCurrency): Plan
    {
        [$price, $currency] = $this->currencyConverter->convert(
            (float) $plan->price,
            $plan->currency ?? $this->currencyConverter->getDefaultCurrency(),
            $targetCurrency
        );

        $plan->display_price = $price;
        $plan->display_currency = $currency;

        return $plan;
    }

    protected function determineCurrency(?string $countryCode): string
    {
        return $this->userRegionService->isIndonesia($countryCode)
            ? $this->currencyConverter->getDefaultCurrency()
            : 'USD';
    }
}
