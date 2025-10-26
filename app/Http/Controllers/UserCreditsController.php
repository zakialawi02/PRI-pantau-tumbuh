<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserCredit;
use App\Models\UserCreditHistory;
use App\Services\CreditService;
use App\Services\ExchangeRateService;
use App\Services\UserLocationService;
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
    protected ExchangeRateService $exchangeRateService;
    protected UserLocationService $userLocationService;

    public function __construct(
        CreditService $creditService,
        ExchangeRateService $exchangeRateService,
        UserLocationService $userLocationService
    ) {
        $this->creditService = $creditService;
        $this->exchangeRateService = $exchangeRateService;
        $this->userLocationService = $userLocationService;
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
        $ip = $request->header('CF-Connecting-IP') ?? $request->header('X-Forwarded-For') ?? $request->ip();

        $currency = $this->userLocationService->resolveCurrency($ip);
        $plans = $this->getPlansForCurrency($currency);

        return view('pages.front.order.purchase-credits', [
            'plans' => $plans,
            'displayCurrency' => $currency,
        ]);
    }

    /**
     * Display the credit purchase page for users.
     */
    public function purchase(Request $request)
    {
        $ip = $request->header('CF-Connecting-IP') ?? $request->header('X-Forwarded-For') ?? $request->ip();

        $currency = $this->userLocationService->resolveCurrency($ip);
        $plans = $this->getPlansForCurrency($currency);

        return view('pages.dashboard.users.purchaseCredits', [
            'plans' => $plans,
            'displayCurrency' => $currency,
        ]);
    }

    public function orderCredit(Request $request)
    {
        $ip = $request->header('CF-Connecting-IP') ?? $request->header('X-Forwarded-For') ?? $request->ip();

        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        [$priceIdr, $priceUsd] = $this->getPlanAmounts($plan);
        $currency = $this->userLocationService->resolveCurrency($ip);
        $displayPrice = $currency === 'IDR' ? $priceIdr : $priceUsd;
        $allowedPaymentMethods = $this->userLocationService->resolvePaymentMethods($ip);

        $timestamp = time();
        $data = [
            'timestamp' => $timestamp,
            'plan' => $plan,
            'price_currency' => $currency,
            'price' => $displayPrice,
            'price_idr' => $priceIdr,
            'price_usd' => $priceUsd,
            'allowed_payment_methods' => $allowedPaymentMethods,
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

                if (!isset($data['allowed_payment_methods'])) {
                    $data['allowed_payment_methods'] = $this->userLocationService->resolvePaymentMethods($request->ip());
                }

                if ($data['plan'] instanceof Plan) {
                    [$priceIdr, $priceUsd] = $this->getPlanAmounts($data['plan']);
                    $data['price_idr'] = $data['price_idr'] ?? $priceIdr;
                    $data['price_usd'] = $data['price_usd'] ?? $priceUsd;
                }

                Cache::put($id, $data, now()->addHours(2));
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

    /**
     * Prepare plans for display in the requested currency.
     */
    private function getPlansForCurrency(string $currency)
    {
        return Plan::where('isShow', true)
            ->where('credit_points', '>', 0)
            ->orderBy('credit_points', 'asc')
            ->get()
            ->map(function (Plan $plan) use ($currency) {
                [$priceIdr, $priceUsd] = $this->getPlanAmounts($plan);

                $plan->setAttribute('price_idr', $priceIdr);
                $plan->setAttribute('price_usd', $priceUsd);
                $plan->setAttribute('display_currency', $currency);
                $plan->setAttribute('display_price', $currency === 'IDR' ? $priceIdr : $priceUsd);

                return $plan;
            });
    }

    /**
     * Ensure both currency values are available for a plan.
     */
    private function getPlanAmounts(Plan $plan): array
    {
        $priceIdr = $plan->price_idr;
        $priceUsd = $plan->price_usd;

        if ($priceIdr !== null && $priceUsd !== null) {
            return [round((float) $priceIdr, 2), round((float) $priceUsd, 2)];
        }

        $baseCurrency = Str::upper($plan->currency ?? 'IDR');
        $basePrice = (float) $plan->price;

        try {
            if ($priceIdr === null) {
                $priceIdr = $baseCurrency === 'IDR'
                    ? $basePrice
                    : $this->exchangeRateService->convert($basePrice, 'USD', 'IDR');
            }

            if ($priceUsd === null) {
                $priceUsd = $baseCurrency === 'USD'
                    ? $basePrice
                    : $this->exchangeRateService->convert($basePrice, 'IDR', 'USD');
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to convert plan pricing', [
                'plan_id' => $plan->id,
                'error' => $exception->getMessage(),
            ]);

            $fallbackRate = (float) config('exchange.fallback_rates.USD_IDR', 15500);

            if ($priceIdr === null) {
                $priceIdr = $baseCurrency === 'IDR'
                    ? $basePrice
                    : round($basePrice * $fallbackRate, 2);
            }

            if ($priceUsd === null) {
                $priceUsd = $baseCurrency === 'USD'
                    ? $basePrice
                    : round($basePrice / max($fallbackRate, 1), 2);
            }
        }

        return [round((float) $priceIdr, 2), round((float) $priceUsd, 2)];
    }
}
