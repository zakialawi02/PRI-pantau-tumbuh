<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\FieldArea;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Common data for all users
        $data = [
            'title' => 'Dashboard'
        ];

        // Check user role and pass appropriate data
        if (in_array($user->role, ['superadmin', 'admin'])) {
            // Admin dashboard data
            $data['totalUsers'] = User::count();
            $data['totalPayments'] = Payment::count();
            $data['totalFieldAreas'] = FieldArea::count();

            $data['recentUsers'] = User::latest()->take(5)->get();
            $data['recentSubscriptions'] = Subscription::with(['user', 'plan'])->latest()->take(5)->get();
            $data['recentPayments'] = Payment::with('subscription')->latest()->take(5)->get();

            return view('pages.dashboard.admin-dashboard', $data);
        } else {
            // Regular user dashboard data
            $data['userSubscriptions'] = Subscription::where('user_id', $user->id)->count();
            $data['userPayments'] = Payment::whereHas('subscription', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->count();
            $data['userFieldAreas'] = FieldArea::where('user_id', $user->id)->count();

            $data['subscriptions'] = Subscription::with(['plan', 'fieldArea', 'payments'])
                ->where('user_id', $user->id)
                ->latest()
                ->take(5)
                ->get();

            $data['payments'] = Payment::with('subscription', 'subscription.fieldArea')
                ->whereHas('subscription', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->latest()
                ->take(5)
                ->get();

            return view('pages.dashboard.user-dashboard', $data);
        }
    }
}
