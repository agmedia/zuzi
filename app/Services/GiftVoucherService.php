<?php

namespace App\Services;

use App\Helpers\Currency;
use App\Mail\GiftVoucherDelivered;
use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use App\Models\GiftVoucher;
use App\Models\Front\Checkout\PaymentMethod;
use Carbon\Carbon;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class GiftVoucherService
{
    public const CART_ITEM_TYPE = 'gift_voucher';
    public const CART_ITEM_ID = 'gift-voucher';
    public const SHIPPING_CODE = 'gift_voucher_email';
    public const DEFAULT_IMAGE = 'media/img/zuzi-logo.webp';

    public static function availableAmounts(): array
    {
        return range(10, 300, 10);
    }

    public static function normalizeAmount($amount): int
    {
        $amount = (int) $amount;

        if (! in_array($amount, static::availableAmounts(), true)) {
            return 50;
        }

        return $amount;
    }

    public static function formatName(int $amount): string
    {
        return 'Poklon bon - ' . number_format($amount, 2, ',', '.') . ' €';
    }

    public static function buildCartItemRequest(array $payload): array
    {
        return [
            'item' => [
                'id' => static::CART_ITEM_ID,
                'type' => static::CART_ITEM_TYPE,
                'quantity' => 1,
                'amount' => static::normalizeAmount($payload['amount'] ?? 0),
                'recipient_name' => trim((string) ($payload['recipient_name'] ?? '')),
                'recipient_email' => trim((string) ($payload['recipient_email'] ?? '')),
                'sender_name' => trim((string) ($payload['sender_name'] ?? '')),
                'message' => trim((string) ($payload['message'] ?? '')),
            ],
        ];
    }

    public static function buildCartItem(array $payload): array
    {
        $amount = static::normalizeAmount($payload['amount'] ?? 0);
        $secondaryRate = Currency::secondary() ? Currency::secondary()->value : null;
        $secondaryPrice = $secondaryRate ? number_format($amount * $secondaryRate, 2, ',', '.') . ' kn' : null;

        $associatedModel = (object) [
            'image' => asset(static::DEFAULT_IMAGE),
            'quantity' => 1,
            'secondary_price' => $secondaryPrice,
            'main_price_text' => '€ ' . number_format($amount, 2, ',', '.'),
            'main_special_text' => '€ ' . number_format($amount, 2, ',', '.'),
            'secondary_price_text' => $secondaryPrice,
            'secondary_special_text' => $secondaryPrice,
            'dataLayer' => [
                'item_id' => 'POKLON-BON',
                'item_name' => static::formatName($amount),
                'price' => number_format($amount, 2, '.', ''),
                'currency' => 'EUR',
                'discount' => number_format(0, 2, '.', ''),
                'item_category' => 'Poklon bon',
                'item_category2' => 'Digitalni poklon',
                'quantity' => 1,
            ],
        ];

        return [
            'id' => static::CART_ITEM_ID,
            'name' => static::formatName($amount),
            'price' => $amount,
            'sec_price' => $secondaryRate ? $amount * $secondaryRate : null,
            'quantity' => 1,
            'associatedModel' => $associatedModel,
            'attributes' => [
                'path' => 'poklon-bon',
                'tax' => ['rate' => 25],
                'item_type' => static::CART_ITEM_TYPE,
                'is_editable_quantity' => false,
                'gift_voucher' => [
                    'amount' => $amount,
                    'recipient_name' => trim((string) ($payload['recipient_name'] ?? '')),
                    'recipient_email' => trim((string) ($payload['recipient_email'] ?? '')),
                    'sender_name' => trim((string) ($payload['sender_name'] ?? '')),
                    'message' => trim((string) ($payload['message'] ?? '')),
                ],
            ],
        ];
    }

    public static function isGiftVoucherItem($item): bool
    {
        return data_get(static::extractAttributes($item), 'item_type') === static::CART_ITEM_TYPE;
    }

    public static function extractVoucherData($item): array
    {
        return data_get(static::extractAttributes($item), 'gift_voucher', []);
    }

    public static function currentCartItems(): Collection
    {
        $cartId = session(config('session.cart'));

        if (! $cartId) {
            return collect();
        }

        return collect(Cart::session($cartId)->getContent());
    }

    public static function currentCartContainsGiftVoucher(): bool
    {
        return static::currentCartItems()->contains(fn ($item) => static::isGiftVoucherItem($item));
    }

    public static function currentCartContainsOnlyGiftVoucher(): bool
    {
        $items = static::currentCartItems();

        return $items->isNotEmpty() && $items->every(fn ($item) => static::isGiftVoucherItem($item));
    }

    public static function cartContainsGiftVoucher(array $cart): bool
    {
        return collect($cart['items'] ?? [])->contains(fn ($item) => static::isGiftVoucherItem($item));
    }

    public static function cartContainsOnlyGiftVoucher(array $cart): bool
    {
        $items = collect($cart['items'] ?? []);

        return $items->isNotEmpty() && $items->every(fn ($item) => static::isGiftVoucherItem($item));
    }

    public static function cartHasRegularItems(array $cart): bool
    {
        return collect($cart['items'] ?? [])->contains(fn ($item) => ! static::isGiftVoucherItem($item));
    }

    public static function shippingMethod(): object
    {
        return (object) [
            'id' => 0,
            'code' => static::SHIPPING_CODE,
            'title' => 'Dostava poklon bona e-mailom',
            'geo_zone' => 0,
            'data' => (object) [
                'price' => 0,
                'short_description' => 'Kod i poruka stižu na e-mail primatelja nakon uspješnog kartičnog plaćanja.',
                'description' => 'Poklon bon je digitalan proizvod. Primatelj dobiva e-mail s porukom i jedinstvenim kodom za popust.',
                'time' => 'Odmah nakon potvrde plaćanja',
            ],
        ];
    }

    public static function isGiftVoucherShipping(?string $code): bool
    {
        return (string) $code === static::SHIPPING_CODE;
    }

    public static function allowedPaymentCodes(): array
    {
        $disallowed = ['bank', 'cod', 'pickup'];
        $codes = (new PaymentMethod())->list()
            ->pluck('code')
            ->filter(fn ($code) => ! in_array($code, $disallowed, true))
            ->values()
            ->toArray();

        if (empty($codes)) {
            return ['corvus'];
        }

        return $codes;
    }

    public static function firstAllowedPaymentCode(): ?string
    {
        return static::allowedPaymentCodes()[0] ?? null;
    }

    public static function isAllowedPaymentCode(?string $code): bool
    {
        return in_array((string) $code, static::allowedPaymentCodes(), true);
    }

    public static function buildOrderComment(array $cart): string
    {
        $item = collect($cart['items'] ?? [])->first(fn ($cartItem) => static::isGiftVoucherItem($cartItem));

        if (! $item) {
            return '';
        }

        $data = static::extractVoucherData($item);
        $parts = [
            'Poklon bon: ' . number_format((float) ($data['amount'] ?? $item->price ?? 0), 2, ',', '.') . ' €',
        ];

        if (! empty($data['recipient_name'])) {
            $parts[] = 'Primatelj: ' . $data['recipient_name'];
        }

        if (! empty($data['recipient_email'])) {
            $parts[] = 'E-mail: ' . $data['recipient_email'];
        }

        return implode(' | ', $parts);
    }

    public static function syncOrderGiftVouchers(int $orderId, array $orderData): void
    {
        $items = collect($orderData['cart']['items'] ?? []);
        $giftItems = $items->filter(fn ($item) => static::isGiftVoucherItem($item));

        if ($giftItems->isEmpty()) {
            GiftVoucher::query()
                ->where('order_id', $orderId)
                ->whereNull('fulfilled_at')
                ->delete();

            return;
        }

        $buyerName = trim(((string) data_get($orderData, 'address.fname')) . ' ' . ((string) data_get($orderData, 'address.lname')));
        $buyerEmail = (string) data_get($orderData, 'address.email');
        $keptKeys = [];

        foreach ($giftItems as $item) {
            $data = static::extractVoucherData($item);
            $cartItemKey = (string) data_get($item, 'id', static::CART_ITEM_ID);

            $keptKeys[] = $cartItemKey;

            GiftVoucher::query()->updateOrCreate(
                [
                    'order_id' => $orderId,
                    'cart_item_key' => $cartItemKey,
                ],
                [
                    'amount' => (float) ($data['amount'] ?? data_get($item, 'price', 0)),
                    'buyer_name' => $buyerName,
                    'buyer_email' => $buyerEmail,
                    'recipient_name' => (string) ($data['recipient_name'] ?? ''),
                    'recipient_email' => (string) ($data['recipient_email'] ?? ''),
                    'sender_name' => (string) ($data['sender_name'] ?? ''),
                    'message' => (string) ($data['message'] ?? ''),
                    'status' => 'pending',
                ]
            );
        }

        GiftVoucher::query()
            ->where('order_id', $orderId)
            ->whereNull('fulfilled_at')
            ->whereNotIn('cart_item_key', $keptKeys)
            ->delete();
    }

    public static function fulfillOrder(Order $order): void
    {
        $giftVouchers = GiftVoucher::query()
            ->with('action')
            ->where('order_id', $order->id)
            ->get();

        if ($giftVouchers->isEmpty()) {
            return;
        }

        foreach ($giftVouchers as $giftVoucher) {
            if (! $giftVoucher->code || ! $giftVoucher->action_id) {
                $code = static::generateUniqueCode();
                $actionId = Action::query()->insertGetId([
                    'title' => 'Poklon bon #' . $order->id,
                    'type' => 'F',
                    'discount' => $giftVoucher->amount,
                    'group' => 'total',
                    'links' => json_encode(['total']),
                    'date_start' => Carbon::now(),
                    'date_end' => null,
                    'data' => null,
                    'coupon' => $code,
                    'quantity' => 1,
                    'lock' => 0,
                    'status' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $giftVoucher->forceFill([
                    'action_id' => $actionId,
                    'code' => $code,
                    'fulfilled_at' => $giftVoucher->fulfilled_at ?: Carbon::now(),
                    'status' => 'ready',
                ])->save();
            }

            if (! $giftVoucher->email_sent_at) {
                try {
                    Mail::to($giftVoucher->recipient_email)->send(new GiftVoucherDelivered($giftVoucher->fresh(['order', 'action'])));

                    $giftVoucher->forceFill([
                        'fulfilled_at' => $giftVoucher->fulfilled_at ?: Carbon::now(),
                        'email_sent_at' => Carbon::now(),
                        'status' => 'sent',
                    ])->save();
                } catch (\Throwable $e) {
                    Log::error('Gift voucher email delivery failed.', [
                        'gift_voucher_id' => $giftVoucher->id,
                        'order_id' => $order->id,
                        'recipient_email' => $giftVoucher->recipient_email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    public static function cancelOrder(Order $order): void
    {
        $giftVouchers = GiftVoucher::query()
            ->with('action')
            ->where('order_id', $order->id)
            ->get();

        foreach ($giftVouchers as $giftVoucher) {
            if ($giftVoucher->action && $giftVoucher->action->status) {
                $giftVoucher->action->update([
                    'status' => 0,
                    'updated_at' => Carbon::now(),
                ]);
            }

            $giftVoucher->update([
                'status' => 'cancelled',
            ]);
        }
    }

    private static function extractAttributes($item): array
    {
        $attributes = data_get($item, 'attributes', []);

        return json_decode(json_encode($attributes), true) ?: [];
    }

    private static function generateUniqueCode(): string
    {
        do {
            $code = 'ZUZI-GIFT-' . Str::upper(Str::random(8));
        } while (
            GiftVoucher::query()->where('code', $code)->exists()
            || Action::query()->where('coupon', $code)->exists()
        );

        return $code;
    }
}
