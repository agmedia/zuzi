<?php

namespace App\Http\Controllers\Back\Settings;

use App\Http\Controllers\Controller;
use App\Models\Back\Settings\Settings;
use Illuminate\Support\Facades\Artisan;

class SettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('back.settings.settings');
    }


    public function get()
    {
        $codes = ['currency', 'geo_zone', 'payment', 'shipping', 'tax'];
        $response = [];
        $settings =  Settings::whereIn('code', $codes)->get();

        foreach ($settings as $setting) {
            if ($setting->json) {
                $response[$setting->code . '.' . $setting->key] = json_decode($setting->value, true);
            }
        }

        return $response;
    }
    
}
