<?php

namespace App\Http\Controllers;

use App\Models\ImageryData;
use App\Services\CopernicusTokenService;
use Illuminate\Support\Facades\Auth;

class MapController extends Controller
{
    public function index()
    {
        if (Auth::check()) {
            $imagery = ImageryData::with('user')->where('user_id', Auth::id())->get();
        } else {
            $imagery = null;
        }
        $copernicusAccessToken = CopernicusTokenService::getAccessToken();
        $copernicusCredentialsConfigured = CopernicusTokenService::hasClientCredentials();

        return view('pages.front.appMap', [
            'imagery' => $imagery,
            'copernicusAccessToken' => $copernicusAccessToken,
            'copernicusCredentialsConfigured' => $copernicusCredentialsConfigured,
        ]);
    }
}
