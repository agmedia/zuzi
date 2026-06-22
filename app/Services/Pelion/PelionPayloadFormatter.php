<?php

namespace App\Services\Pelion;

use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderProduct;
use App\Models\Back\Orders\OrderTotal;
use App\Models\Back\Settings\Settings;
use Illuminate\Support\Carbon;

class PelionPayloadFormatter
{
    public function orderListItem(Order $order): array
    {
        return [
            'id' => (int) $order->id,
            'order_number' => $this->orderNumber($order),
            'created_at' => $this->dateTime($order->created_at),
            'updated_at' => $this->dateTime($order->updated_at),
            'customer_name' => trim($order->payment_fname . ' ' . $order->payment_lname),
            'payment_method_label' => $order->payment_method,
            'shipping_method_label' => $order->shipping_method,
            'shipping_price' => $this->money($this->totalByCode($order, 'shipping')),
            'total' => $this->money($order->total),
            'currency' => $this->currency(),
            'items_count' => (int) $order->products->sum('quantity'),
            'status' => $this->pelionStatus($order),
        ];
    }

    public function orderDetail(Order $order): array
    {
        return [
            'id' => (int) $order->id,
            'order_number' => $this->orderNumber($order),
            'created_at' => $this->dateTime($order->created_at),
            'updated_at' => $this->dateTime($order->updated_at),
            'status' => $this->pelionStatus($order),
            'internal_status' => [
                'id' => (int) $order->order_status_id,
                'title' => optional($order->status)->title,
            ],
            'customer' => $this->customer($order),
            'items' => $order->products->map(fn (OrderProduct $item) => $this->orderItem($item))->values(),
            'shipping' => $this->shipping($order),
            'payment' => $this->payment($order),
            'totals' => $this->totals($order),
            'currency' => $this->currency(),
        ];
    }

    public function orderStatusSummary(Order $order): array
    {
        return [
            'id' => (int) $order->id,
            'order_number' => $this->orderNumber($order),
            'status' => $this->pelionStatus($order),
            'invoice_number' => $order->pelion_invoice_number,
            'invoice_date' => optional($order->pelion_invoice_date)->toDateString(),
            'imported_at' => $this->dateTime($order->pelion_imported_at),
            'invoiced_at' => $this->dateTime($order->pelion_invoiced_at),
        ];
    }

    public function article(Product $product): array
    {
        return [
            'id' => (int) $product->id,
            'itemid' => $product->itemid ? (int) $product->itemid : null,
            'sku' => $product->sku,
            'title' => $product->name,
            'isbn' => $product->isbn ?: $product->ean,
            'publisher_id' => $product->publisher_id ? (int) $product->publisher_id : null,
            'publisher_name' => optional($product->publisher)->title,
            'price' => $this->money($product->price),
            'tax_rate' => $this->taxRateFor($product->tax_id, (float) config('services.pelion.default_product_tax_rate', 5)),
            'quantity' => (int) $product->quantity,
            'active' => (bool) $product->status,
            'updated_at' => $this->dateTime($product->updated_at),
        ];
    }

    public function publisher(Publisher $publisher): array
    {
        return [
            'id' => (int) $publisher->id,
            'name' => $publisher->title,
            'active' => (bool) $publisher->status,
            'updated_at' => $this->dateTime($publisher->updated_at),
        ];
    }

    private function customer(Order $order): array
    {
        return [
            'name' => trim($order->payment_fname . ' ' . $order->payment_lname),
            'company' => $order->company ?: null,
            'oib' => $order->oib ?: null,
            'email' => $order->payment_email,
            'phone' => $order->payment_phone,
            'address' => $order->payment_address,
            'city' => $order->payment_city,
            'postal_code' => $order->payment_zip,
            'country' => $order->payment_state ?? null,
            'shipping_name' => trim($order->shipping_fname . ' ' . $order->shipping_lname),
            'shipping_email' => $order->shipping_email,
            'shipping_phone' => $order->shipping_phone,
            'shipping_address' => $order->shipping_address,
            'shipping_city' => $order->shipping_city,
            'shipping_postal_code' => $order->shipping_zip,
            'shipping_country' => $order->shipping_state ?? null,
        ];
    }

    private function orderItem(OrderProduct $item): array
    {
        $product = $item->product;
        $unitPrice = $this->money($item->price);
        $lineTotal = $this->money($item->total ?: ((int) $item->quantity * $unitPrice));

        return [
            'itemid' => optional($product)->itemid ? (int) $product->itemid : null,
            'article_id' => $item->product_id ? (int) $item->product_id : null,
            'sku' => optional($product)->sku,
            'title' => $item->name,
            'quantity' => (int) $item->quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => $this->taxRateFor(optional($product)->tax_id, (float) config('services.pelion.default_product_tax_rate', 5)),
            'discount' => $item->discount !== null ? (float) $item->discount : 0,
            'line_total' => $lineTotal,
        ];
    }

    private function shipping(Order $order): array
    {
        return [
            'method' => $order->shipping_code,
            'method_label' => $order->shipping_method,
            'price' => $this->money($this->totalByCode($order, 'shipping')),
            'tax_rate' => (float) config('services.pelion.shipping_tax_rate', 25),
        ];
    }

    private function payment(Order $order): array
    {
        return [
            'method' => $order->payment_code,
            'method_label' => $order->payment_method,
            'paid' => $this->isPaid($order),
            'transaction_id' => $this->transactionId($order),
        ];
    }

    private function totals(Order $order): array
    {
        return [
            'items_total' => $this->money($order->products->sum('total')),
            'shipping_total' => $this->money($this->totalByCode($order, 'shipping')),
            'discount_total' => $this->discountTotal($order),
            'tax_total' => $this->money($this->totalByCode($order, 'tax')),
            'grand_total' => $this->money($order->total ?: $this->totalByCode($order, 'total')),
        ];
    }

    private function totalByCode(Order $order, string $code): float
    {
        $total = $order->totals->first(fn (OrderTotal $total) => $total->code === $code);

        return $total ? (float) $total->value : 0.0;
    }

    private function discountTotal(Order $order): float
    {
        $discount = $order->totals
            ->whereIn('code', ['special', 'action', 'discount', 'coupon'])
            ->sum(fn (OrderTotal $total) => (float) $total->value);

        return $this->money(abs(min(0, $discount)));
    }

    private function isPaid(Order $order): bool
    {
        if (strtolower((string) $order->payment_code) === 'cod') {
            return false;
        }

        if ($order->transactions->contains(fn ($transaction) => (int) $transaction->success === 1)) {
            return true;
        }

        $paidStatuses = array_filter([
            config('settings.order.status.paid'),
            config('settings.order.status.send'),
            config('settings.order.status.completed', 9),
        ]);

        return in_array((int) $order->order_status_id, array_map('intval', $paidStatuses), true);
    }

    private function transactionId(Order $order): ?string
    {
        $transaction = $order->transactions->first(fn ($transaction) => (int) $transaction->success === 1);

        if (! $transaction) {
            return null;
        }

        return $transaction->pg_order_id ?: $transaction->approval_code ?: $transaction->signature;
    }

    private function taxRateFor($taxId, float $fallback): float
    {
        if (! $taxId) {
            return $fallback;
        }

        $tax = Settings::get('tax', 'list')->first(fn ($tax) => (int) ($tax->id ?? 0) === (int) $taxId);

        if (! $tax) {
            return $fallback;
        }

        if (isset($tax->rate) && is_numeric($tax->rate)) {
            return (float) $tax->rate;
        }

        if (preg_match('/(\d+(?:[,.]\d+)?)\s*%/', (string) ($tax->title ?? ''), $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return $fallback;
    }

    private function pelionStatus(Order $order): string
    {
        if ($order->pelion_status) {
            return $order->pelion_status;
        }

        if ($order->pelion_invoiced_at || $order->pelion_invoice_number) {
            return 'invoiced';
        }

        return 'ready_for_invoice';
    }

    private function orderNumber(Order $order): string
    {
        return (string) config('services.pelion.order_prefix', 'WEB-') . $order->id;
    }

    private function currency(): string
    {
        return (string) config('services.pelion.currency', 'EUR');
    }

    private function money($value): float
    {
        return round((float) $value, 2);
    }

    private function dateTime($value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon
            ? $value->toIso8601String()
            : Carbon::parse($value)->toIso8601String();
    }
}
