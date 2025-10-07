<?php

namespace App\Http\Controllers;

use App\Models\ImageryData;

class MapController extends Controller
{
    public function index()
    {
        $imagery = ImageryData::with('user')->get();
        $copernicusAccessToken = config('services.copernicus.access_token');

        return view('pages.front.appMap', [
            'imagery' => $imagery,
            'copernicusAccessToken' => $copernicusAccessToken,
        ]);
    }
}
