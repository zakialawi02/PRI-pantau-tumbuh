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
            $totalUsers = User::count();
            $totalFieldAreas = FieldArea::count();
            $totalPayments = Payment::where('status', 'paid')->count();
            $recentPayments = Payment::where('status', 'paid')->with('user')->latest()->take(5)->get();

            $data['totalUsers'] = $totalUsers;
            $data['totalFieldAreas'] = $totalFieldAreas;
            $data['totalPayments'] = $totalPayments;
            $data['recentPayments'] = $recentPayments;

            return view('pages.dashboard.admin-dashboard', compact('data'));
        } else {
            // Regular user dashboard data
            $recentPayments = $user->payments()->where('status', 'paid')->latest()->take(5)->get();
            $totalPayments = $user->payments()->where('status', 'paid')->count();
            $recentFieldAreas = $user->fieldAreas()->latest()->take(3)->get();

            $data['recentPayments'] = $recentPayments;
            $data['recentFieldAreas'] = $recentFieldAreas;

            return view('pages.dashboard.user-dashboard', compact('data'));
        }
    }
}
