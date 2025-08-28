<?php

namespace App\Http\Middleware;

use App\Models\UserDetail;
use Closure;
use Illuminate\Http\Request;
use Bouncer;

class TrackAffiliateLoyaltyUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $tag = config('settings.loyalty.link_tag');

        if ($request->has($tag)) {
            $tag_exist = UserDetail::query()->where('affiliate_name', $request->get($tag))->first();

            if ($tag_exist) {
                $response->withCookie(
                    cookie('affiliate',
                        $request->get($tag),
                        config('settings.loyalty.affiliate_minutes_approval')
                    )
                );
            }
        }

        return $response;
    }
}
