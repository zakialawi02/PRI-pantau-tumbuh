<?php

namespace App\Http\Controllers;

use App\Models\FieldArea;
use App\Models\ImageryData;
use App\Services\CopernicusTokenService;
use Illuminate\Support\Facades\Auth;

class MapController extends Controller
{
    public function index()
    {
        $imagery = null;
        $fieldAreas = collect();

        if (Auth::check()) {
            $userId = Auth::id();

            $imagery = ImageryData::with('user')
                ->where('user_id', $userId)
                ->get();

            $fieldAreas = FieldArea::query()
                ->where('user_id', $userId)
                ->latest()
                ->get(['id', 'name', 'area_ha', 'geom']);
        }
        $copernicusAccessToken = CopernicusTokenService::getAccessToken();
        $copernicusCredentialsConfigured = CopernicusTokenService::hasClientCredentials();

        return view('pages.front.appMap', [
            'imagery' => $imagery,
            'fieldAreas' => $fieldAreas,
            'copernicusAccessToken' => $copernicusAccessToken,
            'copernicusCredentialsConfigured' => $copernicusCredentialsConfigured,
        ]);
    }
}
