<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WoltDrive\WoltZoneService;
use Illuminate\Http\Request;

class WoltDriveController extends Controller
{
    public function checkZone(Request $request, WoltZoneService $zone)
    {
        $request->validate([
            'address' => 'nullable|string',
            'lat'     => 'nullable|numeric',
            'lng'     => 'nullable|numeric',
        ]);

        $inZone = false;

        if ($request->filled(['lat','lng'])) {
            $inZone = $zone->containsLatLng((float)$request->lat, (float)$request->lng);
        } elseif ($request->filled('address')) {
            $inZone = $zone->containsAddress($request->address);
        }

        return response()->json([
            'success' => true,
            'in_zone' => $inZone,
        ]);
    }
}
