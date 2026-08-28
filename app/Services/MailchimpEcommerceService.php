<?php

namespace App\Services;

use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderProduct;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class MailchimpEcommerceService
{
    /** @var bool */
    private $storeEnsured = false;

    public function isConfigured(): bool
    {
        return $this->apiKey() !== ''
            && $this->serverPrefix() !== ''
            && $this->audienceId() !== ''
            && $this->storeId() !== '';
    }

    /**
     * Add or update a finalized local order in Mailchimp's e-commerce store.
     * This method never changes business fields on the local order.
     *
     * @return array{ok:bool,error:?string,stop?:bool,financial_status?:string}
     */
    public function syncOrder(Order $order): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'error' => 'Mailchimp e-commerce nije konfiguriran.',
                'stop' => true,
            ];
        }

        $financialStatus = $this->financialStatusForStatusId((int) $order->order_status_id);
        if ($financialStatus === null) {
            return [
                'ok' => false,
                'error' => 'Status narudžbe nije predviđen za Mailchimp e-commerce sync.',
            ];
        }

        $email = strtolower(trim((string) $order->payment_email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'error' => 'Narudžba nema valjanu e-mail adresu kupca.'];
        }

        try {
            $order->loadMissing(['products.product', 'totals']);

            $storeResult = $this->ensureStore();
            if (! $storeResult['ok']) {
                return $storeResult;
            }

            $customerId = md5($email);
            $customerResult = $this->upsertCustomer($customerId, $email, $order);
            if (! $customerResult['ok']) {
                return $customerResult;
            }

            $lines = [];

            foreach ($order->products as $item) {
                $line = $this->mapOrderLine($item);
                if ($line === null) {
                    continue;
                }

                $productResult = $this->upsertProduct($item, $line);
                if (! $productResult['ok']) {
                    return $productResult;
                }

                $lines[] = $line;
            }

            if ($lines === []) {
                return ['ok' => false, 'error' => 'Narudžba nema valjane stavke za Mailchimp sync.'];
            }

            $taxTotal = (float) optional($order->totals->firstWhere('code', 'tax'))->value;
            $shippingTotal = (float) optional($order->totals->firstWhere('code', 'shipping'))->value;
            $processedAt = $order->checkout_processed_at ?: $order->created_at ?: now();

            $payload = [
                'id' => (string) $order->id,
                'customer' => [
                    'id' => $customerId,
                ],
                'currency_code' => $this->currencyCode(),
                'order_total' => max((float) $order->total, 0),
                'tax_total' => max($taxTotal, 0),
                'shipping_total' => max($shippingTotal, 0),
                'financial_status' => $financialStatus,
                'processed_at_foreign' => $processedAt->toIso8601String(),
                'lines' => $lines,
            ];

            $campaignId = $this->normalizeIdentifier($order->mailchimp_campaign_id);
            if ($campaignId !== null) {
                $payload['campaign_id'] = $campaignId;
            }

            if ((int) $order->order_status_id === (int) config('settings.order.status.send', 4)) {
                $payload['fulfillment_status'] = 'shipped';
            }

            $response = $this->request()->put(
                $this->baseUrl()
                    . '/ecommerce/stores/' . rawurlencode($this->storeId())
                    . '/orders/' . rawurlencode((string) $order->id),
                $payload
            );

            if (! $response->successful()) {
                return $this->failureFromResponse($response, 'Mailchimp order sync nije uspio');
            }

            return [
                'ok' => true,
                'error' => null,
                'financial_status' => $financialStatus,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => 'Mailchimp e-commerce trenutno nije dostupan.',
                'stop' => true,
            ];
        }
    }

    public function financialStatusForStatusId(int $statusId): ?string
    {
        if (in_array($statusId, [
            (int) config('settings.order.status.new', 1),
            (int) config('settings.order.status.awaiting_payment', 2),
        ], true)) {
            return 'pending';
        }

        if (in_array($statusId, [
            (int) config('settings.order.status.paid', 3),
            (int) config('settings.order.status.send', 4),
            (int) config('settings.order.status.completed', 9),
            (int) config('settings.order.status.ready', 10),
            (int) config('settings.order.status.processing', 11),
        ], true)) {
            return 'paid';
        }

        if ($statusId === (int) config('settings.order.status.refunded', 6)) {
            return 'refunded';
        }

        if (in_array($statusId, [
            (int) config('settings.order.status.canceled', 5),
            (int) config('settings.order.status.declined', 7),
        ], true)) {
            return 'cancelled';
        }

        return null;
    }

    /** @return array{ok:bool,error:?string,stop?:bool} */
    public function ensureStore(): array
    {
        if ($this->storeEnsured) {
            return ['ok' => true, 'error' => null];
        }

        $url = $this->baseUrl() . '/ecommerce/stores/' . rawurlencode($this->storeId());
        $existing = $this->request()->get($url);
        $storePayload = [
            'id' => $this->storeId(),
            'list_id' => $this->audienceId(),
            'name' => $this->storeName(),
            'currency_code' => $this->currencyCode(),
            // Keep Mailchimp order notifications and store automations paused
            // until they are explicitly enabled in production configuration.
            'is_syncing' => ! $this->automationsEnabled(),
        ];

        if ($existing->status() === 404) {
            $create = $this->request()->post(
                $this->baseUrl() . '/ecommerce/stores',
                $storePayload
            );

            if (! $create->successful()) {
                return $this->failureFromResponse($create, 'Mailchimp store nije moguće kreirati');
            }

            $this->storeEnsured = true;

            return ['ok' => true, 'error' => null];
        }

        if (! $existing->successful()) {
            return $this->failureFromResponse($existing, 'Mailchimp store nije dostupan');
        }

        $remoteAudienceId = trim((string) $existing->json('list_id'));
        if ($remoteAudienceId !== '' && $remoteAudienceId !== $this->audienceId()) {
            return [
                'ok' => false,
                'error' => 'Mailchimp store je povezan s drugim Audience ID-em.',
                'stop' => true,
            ];
        }

        $updatePayload = [
            'name' => $this->storeName(),
            'currency_code' => $this->currencyCode(),
            'is_syncing' => ! $this->automationsEnabled(),
        ];

        $storeMatches = true;
        foreach ($updatePayload as $key => $value) {
            if ($existing->json($key) !== $value) {
                $storeMatches = false;
                break;
            }
        }

        if ($storeMatches) {
            $this->storeEnsured = true;

            return ['ok' => true, 'error' => null];
        }

        $update = $this->request()->patch($url, $updatePayload);

        if (! $update->successful()) {
            return $this->failureFromResponse($update, 'Mailchimp store nije moguće ažurirati');
        }

        $this->storeEnsured = true;

        return ['ok' => true, 'error' => null];
    }

    /** @return array{ok:bool,error:?string,stop?:bool} */
    private function upsertCustomer(string $customerId, string $email, Order $order): array
    {
        $payload = [
            'id' => $customerId,
            'email_address' => $email,
            'opt_in_status' => false,
            'first_name' => trim((string) $order->payment_fname),
            'last_name' => trim((string) $order->payment_lname),
        ];

        $response = $this->request()->put(
            $this->baseUrl()
                . '/ecommerce/stores/' . rawurlencode($this->storeId())
                . '/customers/' . rawurlencode($customerId),
            $payload
        );

        return $response->successful()
            ? ['ok' => true, 'error' => null]
            : $this->failureFromResponse($response, 'Mailchimp customer sync nije uspio');
    }

    /**
     * @param array{id:string,product_id:string,product_variant_id:string,quantity:int,price:float} $line
     * @return array{ok:bool,error:?string,stop?:bool}
     */
    private function upsertProduct(OrderProduct $item, array $line): array
    {
        $catalogProduct = $item->product;
        $title = trim((string) $item->name) ?: ('Proizvod ' . $line['product_id']);
        $inventory = $catalogProduct ? max((int) $catalogProduct->quantity, 0) : 0;

        $payload = [
            'id' => $line['product_id'],
            'title' => $title,
            'variants' => [[
                'id' => $line['product_variant_id'],
                'title' => $title,
                'price' => $line['price'],
                'inventory_quantity' => $inventory,
            ]],
        ];

        if ($catalogProduct) {
            $description = trim(strip_tags((string) $catalogProduct->description));
            if ($description !== '') {
                $payload['description'] = Str::limit($description, 5000, '…');
            }

            $imageUrl = $this->absoluteUrl((string) $catalogProduct->image);
            if ($imageUrl !== '') {
                $payload['image_url'] = $imageUrl;
            }
        }

        $response = $this->request()->put(
            $this->baseUrl()
                . '/ecommerce/stores/' . rawurlencode($this->storeId())
                . '/products/' . rawurlencode($line['product_id']),
            $payload
        );

        return $response->successful()
            ? ['ok' => true, 'error' => null]
            : $this->failureFromResponse($response, 'Mailchimp product sync nije uspio');
    }

    /** @return array{id:string,product_id:string,product_variant_id:string,quantity:int,price:float}|null */
    private function mapOrderLine(OrderProduct $item): ?array
    {
        $productId = trim((string) $item->product_id);
        $quantity = (int) $item->quantity;

        if ($quantity < 1) {
            return null;
        }

        if ($productId === '' || $productId === '0') {
            $productId = $this->syntheticProductId($item);
        }

        return [
            'id' => (string) $item->id,
            'product_id' => $productId,
            'product_variant_id' => $productId,
            'quantity' => $quantity,
            'price' => max((float) $item->price, 0),
        ];
    }

    /**
     * Gift vouchers and gift wrapping are valid Zuzi sales but intentionally
     * use product_id=0 locally. Give them deterministic Mailchimp catalog IDs
     * so voucher-only orders are still reported as conversions.
     */
    private function syntheticProductId(OrderProduct $item): string
    {
        $name = trim((string) $item->name);
        $normalized = Str::lower(Str::ascii($name));

        if (Str::startsWith($normalized, 'poklon bon')) {
            return 'zuzi-gift-voucher-' . (int) round(max((float) $item->price, 0) * 100);
        }

        if (Str::startsWith($normalized, 'zamatanje')) {
            return 'zuzi-gift-wrap';
        }

        return 'zuzi-custom-' . substr(sha1($normalized !== '' ? $normalized : (string) $item->id), 0, 20);
    }

    /** @return array{ok:false,error:string,stop:bool} */
    private function failureFromResponse(Response $response, string $context): array
    {
        return [
            'ok' => false,
            'error' => $context . ': ' . $this->responseError($response),
            'stop' => in_array($response->status(), [401, 403, 404, 429, 500, 502, 503, 504], true),
        ];
    }

    private function responseError(Response $response): string
    {
        if ($response->status() === 401) {
            return 'API ključ nije prihvaćen (HTTP 401).';
        }

        if ($response->status() === 429) {
            return 'Mailchimp je ograničio broj zahtjeva (HTTP 429).';
        }

        $title = trim(strip_tags((string) $response->json('title')));
        $detail = trim(strip_tags((string) $response->json('detail')));
        $message = trim(implode(': ', array_filter([$title, $detail])));

        if ($message === '') {
            $message = 'Neočekivana Mailchimp greška.';
        }

        return 'HTTP ' . $response->status() . ' — ' . Str::limit($message, 500, '…');
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth('anystring', $this->apiKey())
            ->acceptJson()
            ->withOptions(['connect_timeout' => 5])
            ->timeout(15);
    }

    private function baseUrl(): string
    {
        return 'https://' . $this->serverPrefix() . '.api.mailchimp.com/3.0';
    }

    private function apiKey(): string
    {
        return trim((string) config('services.mailchimp.api_key'));
    }

    private function audienceId(): string
    {
        return trim((string) config('services.mailchimp.audience_id'));
    }

    private function storeId(): string
    {
        return trim((string) config('services.mailchimp.ecommerce_store_id', 'zuzi-shop'));
    }

    private function storeName(): string
    {
        return trim((string) config('services.mailchimp.ecommerce_store_name', 'Zuzi obrt'))
            ?: 'Zuzi obrt';
    }

    private function currencyCode(): string
    {
        $currency = strtoupper(trim((string) config('services.mailchimp.ecommerce_currency_code', 'EUR')));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'EUR';
    }

    private function automationsEnabled(): bool
    {
        return filter_var(
            config('services.mailchimp.ecommerce_automations_enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function serverPrefix(): string
    {
        $prefix = trim((string) config('services.mailchimp.server_prefix'));

        if ($prefix === '' && strpos($this->apiKey(), '-') !== false) {
            $parts = explode('-', $this->apiKey());
            $prefix = (string) end($parts);
        }

        return preg_match('/^[a-z0-9-]+$/i', $prefix) === 1 ? strtolower($prefix) : '';
    }

    private function normalizeIdentifier($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strlen($value) > 100) {
            return null;
        }

        return preg_match('/^[a-z0-9_-]+$/i', $value) === 1 ? $value : null;
    }

    private function absoluteUrl(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $storefrontUrl = rtrim((string) config('services.mailchimp.storefront_url', config('app.url')), '/');

        return $storefrontUrl !== '' ? $storefrontUrl . '/' . ltrim($path, '/') : '';
    }
}
