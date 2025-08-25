<?php

namespace App\Services;

use App\Models\UserAffiliate;
use App\Models\User;
use App\Models\Front\Loyalty;
use Illuminate\Support\Str;

class AffiliateService
{
    private $referal_points;

    public function __construct()
    {
        $this->referal_points = config('settings.loyalty.affiliate_points');
    }


    public function generateAffiliateCode(User $user)
    {
        return Str::slug(strtok($user->name, ' '));
    }


    public function createAffiliateRelationship(User $user, ?User $customer = null)
    {
        return UserAffiliate::create([
            'user_id' => $user->id,
            'customer_email' => $customer ? $customer->email : null,
            'affiliate_code' => $this->generateAffiliateCode($user),
            'active' => 1
        ]);
    }


    public function handleRegistration(UserAffiliate $affiliate, $order_id)
    {
        if ( ! $affiliate->registered_at) {
            $affiliate->update([
                'registered_at' => now(),
            ]);
        }
    }


    public function handleFirstPurchase(UserAffiliate $affiliate, $order_id)
    {
        if ( ! $affiliate->first_purchase_at) {
            // Award points to referrer
            Loyalty::addPoints(
                $this->referal_points,
                $order_id,
                'affiliate_referral',
                'Referral reward points',
                $affiliate->user_id
            );

            // Award points to referred customer
            Loyalty::addPoints(
                $this->referal_points,
                $order_id,
                'order',
                'Welcome referral points',
                $affiliate->customer_id
            );

            $affiliate->update([
                'first_purchase_at' => now(),
                'active' => 0
            ]);
        }
    }
}