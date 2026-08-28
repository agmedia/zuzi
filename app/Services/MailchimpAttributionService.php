<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MailchimpAttributionService
{
    public const CAMPAIGN_COOKIE = 'zuzi_mc_cid';
    public const CONSENT_COOKIE = 'zuzi_marketing_consent';

    /** @var bool|null */
    private $columnsAvailable;

    /**
     * Persist consented Mailchimp click attribution on an unfinished order.
     * Tracking metadata must never affect the checkout outcome.
     */
    public function attachToOrder(int $orderId, Request $request): bool
    {
        if ($orderId < 1 || ! $this->columnsAreAvailable()) {
            return false;
        }

        try {
            $campaignId = $this->normalizeIdentifier($request->cookie(self::CAMPAIGN_COOKIE));
            $marketingConsent = strtolower(trim((string) $request->cookie(self::CONSENT_COOKIE)));

            // A missing cookie is common after payment redirects. Only an
            // explicit withdrawal may clear previously consented attribution.
            if ($marketingConsent === 'denied') {
                DB::table('orders')
                    ->where('id', $orderId)
                    ->whereNull('checkout_processed_at')
                    ->whereNotNull('mailchimp_campaign_id')
                    ->update(['mailchimp_campaign_id' => null]);

                return false;
            }

            // Plain cookies are only transport. Persist new attribution solely
            // when the consent state written by the consent manager is present.
            if ($marketingConsent !== 'granted' || $campaignId === null) {
                return false;
            }

            return DB::table('orders')
                ->where('id', $orderId)
                ->whereNull('checkout_processed_at')
                ->update(['mailchimp_campaign_id' => $campaignId]) === 1;
        } catch (Throwable $e) {
            Log::warning('Mailchimp attribution could not be attached to order.', [
                'order_id' => $orderId,
                'exception' => get_class($e),
            ]);

            return false;
        }
    }

    private function normalizeIdentifier($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strlen($value) > 100) {
            return null;
        }

        return preg_match('/^[a-z0-9_-]+$/i', $value) === 1 ? $value : null;
    }

    private function columnsAreAvailable(): bool
    {
        if ($this->columnsAvailable !== null) {
            return $this->columnsAvailable;
        }

        try {
            $this->columnsAvailable = Schema::hasColumn('orders', 'checkout_processed_at')
                && Schema::hasColumn('orders', 'mailchimp_campaign_id')
                && Schema::hasColumn('orders', 'mailchimp_ecommerce_synced_at');
        } catch (Throwable $e) {
            $this->columnsAvailable = false;
        }

        return $this->columnsAvailable;
    }
}
