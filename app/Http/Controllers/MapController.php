<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\FieldArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MapController extends Controller
{
    public function index()
    {
        $plans = Plan::all();

        // Get active field areas for authenticated users
        $activeFieldAreas = [];
        if (Auth::check()) {
            $activeFieldAreas = FieldArea::where('user_id', Auth::id())
                ->with('subscriptions')
                ->get();
        }

        return view('pages.front.appMap', compact('plans', 'activeFieldAreas'));
    }
}
