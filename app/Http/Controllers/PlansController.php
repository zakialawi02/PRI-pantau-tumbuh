<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class PlansController extends Controller
{
    public function __construct(private readonly ExchangeRateService $exchangeRateService)
    {
    }
    /**
     * Display a listing of the plans.
     */
    public function index(Request $request)
    {
        $data = [
            'title' => 'Plans Management',
        ];

        if ($request->ajax()) {
            $plans = Plan::query();

            return DataTables::of($plans)
                ->addIndexColumn()
                ->addColumn('action', function ($plan) {
                    return '<div class="flex gap-1">
                                <button type="button" class="edit-plan inline-flex items-center px-2 py-1 bg-secondary border border-transparent rounded-md font-semibold text-xs text-secondary-foreground uppercase tracking-widest hover:bg-secondary/80 focus:bg-secondary/80 active:bg-secondary/70 focus:outline-none focus:ring-2 focus:ring-secondary focus:ring-offset-2" data-id="' . $plan->id . '"><i class="ri-edit-line"></i></button>
                                <button type="button" class="delete-plan inline-flex items-center px-2 py-1 bg-error border border-transparent rounded-md font-semibold text-xs text-primary-foreground uppercase tracking-widest hover:bg-error/80 focus:bg-error/80 active:bg-secondary/70 focus:outline-none focus:ring-2 focus:ring-secondary focus:ring-offset-2" data-id="' . $plan->id . '"> <i class="ri-delete-bin-line"></i></button>
                            </div>';
                })
                ->editColumn('credit_points', function ($plan) {
                    return $plan->credit_points . ' credits';
                })
                ->rawColumns(['action', 'credit_points'])
                ->make(true);
        }

        return view('pages.dashboard.plan.index', compact('data'));
    }

    /**
     * Store a newly created plan in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:plans,name',
            'credit_points' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:IDR,USD',
            'isShow' => 'boolean',
            'isFeatured' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            [$priceIdr, $priceUsd] = $this->preparePlanPrices((float) $request->price, $request->currency);
        } catch (Throwable $exception) {
            Log::warning('Failed to calculate prices for plan creation', [
                'name' => $request->name,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate plan prices. Please try again later.',
            ], 422);
        }

        $plan = Plan::create([
            'name' => Str::title($request->name),
            'credit_points' => $request->credit_points,
            'price' => $request->price,
            'price_idr' => $priceIdr,
            'price_usd' => $priceUsd,
            'currency' => Str::upper($request->currency),
            'isShow' => $request->boolean('isShow'),
            'isFeatured' => $request->boolean('isFeatured'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan created successfully!',
            'data' => $plan
        ]);
    }

    /**
     * Display the specified plan.
     */
    public function show(Plan $plan): JsonResponse
    {
        return response()->json($plan);
    }

    /**
     * Update the specified plan in storage.
     */
    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:plans,name,' . $plan->id,
            'credit_points' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:IDR,USD',
            'isShow' => 'boolean',
            'isFeatured' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            [$priceIdr, $priceUsd] = $this->preparePlanPrices((float) $request->price, $request->currency);
        } catch (Throwable $exception) {
            Log::warning('Failed to calculate prices for plan update', [
                'plan_id' => $plan->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate plan prices. Please try again later.',
            ], 422);
        }

        $plan->update([
            'name' => Str::title($request->name),
            'credit_points' => $request->credit_points,
            'price' => $request->price,
            'price_idr' => $priceIdr,
            'price_usd' => $priceUsd,
            'currency' => Str::upper($request->currency),
            'isShow' => $request->boolean('isShow'),
            'isFeatured' => $request->boolean('isFeatured'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully!',
            'data' => $plan
        ]);
    }

    /**
     * Remove the specified plan from storage.
     */
    public function destroy(Plan $plan): JsonResponse
    {
        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Plan deleted successfully!'
        ]);
    }

    private function preparePlanPrices(float $price, string $currency): array
    {
        $currency = Str::upper($currency ?: 'IDR');

        if ($currency === 'IDR') {
            $priceIdr = round($price, 2);
            $priceUsd = $this->exchangeRateService->convert($price, 'IDR', 'USD');
        } else {
            $priceUsd = round($price, 2);
            $priceIdr = $this->exchangeRateService->convert($price, 'USD', 'IDR');
        }

        return [$priceIdr, $priceUsd];
    }
}
