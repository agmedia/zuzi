<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Support\InterliberOffer;

class InterliberPromotionController extends Controller
{
    public function index(InterliberOffer $offer)
    {
        return response()->view('front.interliber-popusti', [
            'offerActive' => $offer->isActive(),
        ], 200, [
            'Cache-Control' => 'no-cache',
        ]);
    }
}
