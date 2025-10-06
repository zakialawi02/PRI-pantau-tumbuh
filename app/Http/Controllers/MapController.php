<?php

namespace App\Http\Controllers;

use App\Models\ImageryData;

class MapController extends Controller
{
    public function index()
    {
        $imagery = ImageryData::with('user')->get();

        return view('pages.front.appMap', compact('imagery'));
    }
}
