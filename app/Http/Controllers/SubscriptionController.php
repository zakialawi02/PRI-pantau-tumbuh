<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $data = [
            'title' => in_array(Auth::user()->role, ['superadmin', 'admin']) ? 'Subscription' : 'My Subscription',
        ];

        if ($request->ajax()) {
            $user = Auth::user();

            // Build the query based on user role
            if (in_array($user->role, ['superadmin', 'admin'])) {
                // Admin can see all subscriptions
                $query = Subscription::with(['user', 'plan', 'fieldArea', 'payments']);
            } else {
                // Regular users can only see their own subscriptions
                $query = Subscription::with(['user', 'plan', 'fieldArea', 'payments'])
                    ->where('user_id', $user->id);
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('user.name', function ($data) {
                    return $data->user->name ?? '-';
                })
                ->editColumn('plan.name', function ($data) {
                    return $data->plan->name ?? '-';
                })
                ->addColumn('area_ha', function ($data) {
                    return  $data->fieldArea->area_ha ?? null;
                })
                ->addColumn('action', function ($data) {
                    $user = Auth::user();
                    $actions = '<div class="flex space-x-2">';

                    // View button for all users
                    $actions .= '<button class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-secondary/80 rounded-full hover:bg-secondary/60 view-subscription" data-id="' . $data->id . '" subscription-status  data-modal-target="subscription-modal" data-modal-toggle="subscription-modal" title="View Details">';
                    $actions .= '<i class="ri-eye-line mr-1"></i> View';
                    $actions .= '</button>';

                    // Edit button only for superadmin
                    if ($user->role === 'superadmin') {
                        $actions .= '<button class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-warning/80 rounded-full hover:bg-warning/60 edit-subscription" data-id="' . $data->id . '" title="Edit Subscription">';
                        $actions .= '<i class="ri-edit-line mr-1"></i> Edit';
                        $actions .= '</button>';
                    }

                    $actions .= '</div>';
                    return $actions;
                })
                ->rawColumns(['status', 'payment_status', 'action'])
                ->removeColumn(['id', 'user_id', 'plan_id', 'field_area_id', 'updated_at'])
                ->make(true);
        }

        return view('pages.dashboard.subscription.index', compact('data'));
    }

    /**
     * Display the specified subscription.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Subscription $subscription)
    {
        try {
            $user = Auth::user();

            // Build the query based on user role
            if (in_array($user->role, ['superadmin', 'admin'])) {
                // Admin can see all subscriptions
                $subscription->load(['user', 'plan', 'fieldArea', 'payments' => function ($query) {
                    $query->latest()->limit(2);
                }]);
            } else {
                // Regular users can only see their own subscriptions
                if ($subscription->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access to this subscription.'
                    ], 403);
                }

                $subscription->load(['user', 'plan', 'fieldArea', 'payments' => function ($query) {
                    $query->latest()->limit(2);
                }]);
            }

            return response()->json([
                'success' => true,
                'data' => $subscription
            ], 200);
        } catch (ModelNotFoundException $e) {
            Log::error("Subscription Model not found. Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error("Error fetching subscription details. Error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching subscription details.'
            ], 500);
        }
    }
}
