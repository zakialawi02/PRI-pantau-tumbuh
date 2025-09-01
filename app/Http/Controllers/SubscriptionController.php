<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $data = [
            'title' => 'My Subscription',
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
                    return $data->fieldArea ? number_format($data->fieldArea->area_ha, 2) . ' ha' : '-';
                })
                ->addColumn('action', function ($data) {
                    $user = Auth::user();
                    $actions = '<div class="flex space-x-2">';

                    // View button for all users
                    $actions .= '<button class="inline-flex items-center px-3 py-1 text-xs font-medium text-blue-600 bg-blue-100 rounded-full hover:bg-blue-200 view-subscription" data-id="' . $data->id . '" title="View Details">';
                    $actions .= '<i class="ri-eye-line mr-1"></i> View';
                    $actions .= '</button>';

                    $actions .= '</div>';
                    return $actions;
                })
                ->rawColumns(['status', 'payment_status', 'action'])
                ->removeColumn(['id', 'user_id', 'plan_id', 'field_area_id', 'updated_at'])
                ->make(true);
        }

        return view('pages.dashboard.subscription.index', compact('data'));
    }
}
