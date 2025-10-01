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

            return view('pages.dashboard.admin-dashboard', $data);
        } else {
            // Regular user dashboard data

            return view('pages.dashboard.user-dashboard', $data);
        }
    }
}
