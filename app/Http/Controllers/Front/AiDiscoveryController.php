<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Support\InterliberOffer;

class AiDiscoveryController extends Controller
{
    public function llms(InterliberOffer $offer)
    {
        return response()->view('front.discovery.llms', [
            'offerActive' => $offer->isActive(),
        ], 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
