<?php

namespace App\Http\Controllers;

use App\Models\ImageryData;
use App\Services\CopernicusTokenService;
use App\Services\GeoServerService;

class MapController extends Controller
{
    public function index()
    {
        $imagery = ImageryData::with('user')->get();
        $copernicusAccessToken = CopernicusTokenService::getAccessToken();
        $copernicusCredentialsConfigured = CopernicusTokenService::hasClientCredentials();

        $geoServer = app(GeoServerService::class);

        return view('pages.front.appMap', [
            'imagery' => $imagery,
            'copernicusAccessToken' => $copernicusAccessToken,
            'copernicusCredentialsConfigured' => $copernicusCredentialsConfigured,
            'geoserverConfig' => [
                'workspace' => $geoServer->getWorkspace(),
                'wmsUrl' => $geoServer->getWmsUrl(),
                'wmtsUrl' => $geoServer->getWmtsUrl(),
            ],
        ]);
    }
}
