<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\Back\Orders\Order;
use App\Models\TagManager;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAnalyticsService
{
    public function dispatchPurchaseFromRequest(Order $order, Request $request): bool
    {
        if (! app()->environment('production') || ! $this->isConfigured()) {
            return false;
        }

        if ($this->wasPurchaseSent($order->id) || $this->isPurchasePending($order->id)) {
            return true;
        }

        $consent = $this->resolveConsent($request);

        if (! $consent['analytics']) {
            return false;
        }

        $clientId = $this->resolveClientId($request);

        if (! $clientId) {
            return false;
        }

        if (! Helper::addCache($this->pendingCacheKey($order->id), true, now()->addMinutes(10), true)) {
            return true;
        }

        $payload = [
            'client_id'        => $clientId,
            'timestamp_micros' => $this->resolveTimestampMicros($order),
            'consent'          => [
                'ad_user_data'      => $consent['marketing'] ? 'GRANTED' : 'DENIED',
                'ad_personalization' => $consent['marketing'] ? 'GRANTED' : 'DENIED',
            ],
            'events'           => [[
                'name'   => 'purchase',
                'params' => TagManager::getGooglePurchaseEventParams($order),
            ]],
        ];

        $endpoint = $this->endpoint();
        $sentKey = $this->sentCacheKey($order->id);
        $pendingKey = $this->pendingCacheKey($order->id);
        $orderId = $order->id;

        dispatch(function () use ($endpoint, $payload, $sentKey, $pendingKey, $orderId) {
            try {
                $response = Http::asJson()
                    ->timeout(4)
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    Helper::putCache($sentKey, true, now()->addDays(30));

                    return;
                }

                Log::warning('GA4 purchase Measurement Protocol request failed.', [
                    'order_id' => $orderId,
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                ]);
            } catch (\Throwable $exception) {
                Log::warning('GA4 purchase Measurement Protocol request threw an exception.', [
                    'order_id' => $orderId,
                    'message'  => $exception->getMessage(),
                ]);
            } finally {
                Helper::forgetCache($pendingKey);
            }
        })->afterResponse();

        return true;
    }


    public function wasPurchaseSent(int $orderId): bool
    {
        return Helper::hasCache($this->sentCacheKey($orderId));
    }


    private function isPurchasePending(int $orderId): bool
    {
        return Helper::hasCache($this->pendingCacheKey($orderId));
    }


    private function isConfigured(): bool
    {
        return (bool) config('services.google_analytics.measurement_id')
            && (bool) config('services.google_analytics.measurement_api_secret');
    }


    private function endpoint(): string
    {
        return 'https://www.google-analytics.com/mp/collect?' . http_build_query([
            'measurement_id' => config('services.google_analytics.measurement_id'),
            'api_secret'     => config('services.google_analytics.measurement_api_secret'),
        ]);
    }


    private function resolveConsent(Request $request): array
    {
        $cookie = $request->cookie('cc_cookie');

        if (! is_string($cookie) || $cookie === '') {
            return [
                'analytics' => false,
                'marketing' => false,
            ];
        }

        $decoded = json_decode(urldecode($cookie), true);
        $categories = is_array($decoded['categories'] ?? null) ? $decoded['categories'] : [];

        return [
            'analytics' => in_array('analytics', $categories, true),
            'marketing' => in_array('marketing', $categories, true),
        ];
    }


    private function resolveClientId(Request $request): ?string
    {
        $cookie = (string) $request->cookie('_ga', '');

        if ($cookie === '') {
            return null;
        }

        if (preg_match('/(\d+\.\d+)$/', $cookie, $matches)) {
            return $matches[1];
        }

        $parts = array_values(array_filter(explode('.', $cookie), static fn ($part) => $part !== ''));

        if (count($parts) < 2) {
            return null;
        }

        return $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
    }


    private function resolveTimestampMicros(Order $order): int
    {
        $timestamp = $order->updated_at ?: $order->created_at ?: now();

        if ($timestamp instanceof CarbonInterface) {
            return (int) ($timestamp->valueOf() * 1000);
        }

        return (int) (now()->valueOf() * 1000);
    }


    private function sentCacheKey(int $orderId): string
    {
        return 'ga4_purchase_server_sent_' . $orderId;
    }


    private function pendingCacheKey(int $orderId): string
    {
        return 'ga4_purchase_server_pending_' . $orderId;
    }
}
