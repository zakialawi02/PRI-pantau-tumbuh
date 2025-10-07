<?php

namespace App\Http\Controllers;

use App\Models\ImageryData;
use App\Services\CopernicusTokenService;

class MapController extends Controller
{
    public function index()
    {
        $imagery = ImageryData::with('user')->get();
        $copernicusAccessToken = CopernicusTokenService::getAccessToken();
        $copernicusCredentialsConfigured = CopernicusTokenService::hasClientCredentials();

        return view('pages.front.appMap', [
            'imagery' => $imagery,
            'copernicusAccessToken' => $copernicusAccessToken,
            'copernicusCredentialsConfigured' => $copernicusCredentialsConfigured,
        ]);
    }
}
