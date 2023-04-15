<?php

namespace App\Http\Controllers\Back\Settings\App;

use App\Http\Controllers\Controller;
use App\Models\Back\Settings\Faq;
use App\Models\Back\Settings\Settings;
use App\Helpers\Country;
use App\Models\Front\Checkout\GeoZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GeoZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $geo_zones = (new GeoZone())->list(false);

        return view('back.settings.app.geozone.index', compact('geo_zones'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $countries = Country::list();

        return view('back.settings.app.geozone.edit', compact('countries'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $this->cleanRequest($request);

        //dd($data);

        $setting = Settings::where('code', 'geo_zone')->where('key', 'list')->first();

        $values = collect();

        if ($setting) {
            $values = collect(json_decode($setting->value));
        }

        if ( ! $data['id']) {
            $data['id'] = $values->count() + 1;
            $data['status'] = isset($data['status']) ? true : false;
            $values->push($data);
        }
        else {
            $values->where('id', $data['id'])->map(function ($item) use ($data) {
                $item->title = $data['title'];
                $item->description = $data['description'];
                $item->state = isset($data['state']) ? $data['state'] : [];
                $item->status = isset($data['status']) ? true : false;

                return $item;
            });
        }

        if ( ! $setting) {
            $stored = Settings::insert('geo_zone', 'list', $values->toJson(), true);
        } else {
            $stored = Settings::edit($setting->id, 'geo_zone', 'list', $values->toJson(), true);
        }

        if ($stored) {
            Cache::forget('geo_zones');

            return redirect()->route('geozones')->with(['success' => 'Geo zone was succesfully saved!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error saving geo zone.']);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Author $author
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($geozone)
    {
        $geo_zones = Settings::get('geo_zone', 'list');

        $geo_zone = $geo_zones->where('id', $geozone)->first();

        if (isset($geo_zone->state)) {
            Cache::forget('geo_zones');

            $geo_zone->state = json_decode(json_encode($geo_zone->state), true);
        } else {
            $geo_zone->state = [];
        }

        $countries = Country::list();

        return view('back.settings.app.geozone.edit', compact('geo_zone', 'countries'));
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($geozone)
    {
        if ($geozone) {
            $setting = Settings::where('code', 'geo_zone')->where('key', 'list')->first();

            $values = collect(json_decode($setting->value));

            $new_values = $values->reject(function ($item) use ($geozone) {
                return $item->id == $geozone;
            });

            $stored = Settings::edit($setting->id, 'geo_zone', 'list', $new_values->toJson(), true);
        }

        if ($stored) {
            Cache::forget('geo_zones');

            return redirect()->route('geozones')->with(['success' => 'Geo zone was succesfully deleted!']);
        }

        return redirect()->back()->with(['error' => 'Whoops..! There was an error deleting geo zone.']);
    }


    /**
     * @param Request $request
     *
     * @return array
     */
    private function cleanRequest(Request $request): array
    {
        $data = $request->toArray();

        if (isset($data['_token'])) {
            unset($data['_token']);
        }

        if (isset($data['_method'])) {
            unset($data['_method']);
        }

        return $data;
    }
}
