<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function index()
    {
        $plans = Plan::all();

        return view('pages.front.appMap', compact('plans'));
    }
}
