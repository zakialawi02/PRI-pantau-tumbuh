<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\FieldArea;
use App\Models\ImageryData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MapController extends Controller
{
    public function index()
    {
        $plans = Plan::all();

        $imagery = ImageryData::with('user')->get();

        return view('pages.front.appMap', compact('plans', 'imagery'));
    }
}
