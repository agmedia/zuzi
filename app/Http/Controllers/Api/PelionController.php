<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use App\Services\Pelion\PelionPayloadFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PelionController extends Controller
{
    public function orders(Request $request, PelionPayloadFormatter $formatter)
    {
        $data = $request->validate([
            'status' => 'nullable|string|in:ready_for_invoice,imported_to_pelion,invoiced,error,all',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'query' => 'nullable|string|max:120',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'updated_from' => 'nullable|date',
        ]);

        $limit = (int) ($data['limit'] ?? 50);
        $status = $data['status'] ?? 'ready_for_invoice';

        $query = Order::query()
            ->with(['products.product', 'totals', 'transactions']);

        $this->applyOrderStatusFilter($query, $status);
        $this->applyDateFilters($query, $data);
        $this->applyOrderSearch($query, $data['query'] ?? null);

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($limit);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Order $order) => $formatter->orderListItem($order))
                ->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function order(Order $order, PelionPayloadFormatter $formatter)
    {
        $order->load(['products.product', 'totals', 'transactions']);

        return response()->json([
            'data' => $formatter->orderDetail($order),
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order, PelionPayloadFormatter $formatter)
    {
        $data = $request->validate([
            'status' => 'required|string|in:imported_to_pelion,invoiced,error',
            'invoice_number' => 'nullable|string|max:191',
            'invoice_date' => 'nullable|date',
            'message' => 'nullable|string|max:1000',
        ]);

        $now = now();
        $updates = [
            'pelion_status' => $data['status'],
            'pelion_error' => null,
        ];

        if ($data['status'] === 'imported_to_pelion') {
            $updates['pelion_imported_at'] = $order->pelion_imported_at ?: $now;
        }

        if ($data['status'] === 'invoiced') {
            $updates['pelion_imported_at'] = $order->pelion_imported_at ?: $now;
            $updates['pelion_invoiced_at'] = $now;
            $updates['pelion_invoice_number'] = $data['invoice_number'] ?? $order->pelion_invoice_number;
            $updates['pelion_invoice_date'] = $data['invoice_date'] ?? $now->toDateString();

            if (! empty($data['invoice_number'])) {
                $updates['invoice'] = $data['invoice_number'];
            }
        }

        if ($data['status'] === 'error') {
            $updates['pelion_error'] = $data['message'] ?? null;
        }

        $order->forceFill($updates)->save();
        $order->refresh()->load(['products.product', 'totals', 'transactions']);

        OrderHistory::query()->insert([
            'order_id' => $order->id,
            'user_id' => 0,
            'status' => $order->order_status_id,
            'comment' => $this->historyComment($data),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'message' => 'Pelion status je zaprimljen.',
            'data' => $formatter->orderStatusSummary($order),
        ]);
    }

    public function articles(Request $request, PelionPayloadFormatter $formatter)
    {
        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1',
            'query' => 'nullable|string|max:120',
            'active' => 'nullable|boolean',
            'updated_from' => 'nullable|date',
        ]);

        $query = Product::query()
            ->with('publisher')
            ->whereNotNull('itemid')
            ->where('itemid', '>', 0);

        if ($request->has('active')) {
            $query->where('status', $request->boolean('active'));
        } else {
            $query->where('status', true);
        }

        if (! empty($data['updated_from'])) {
            $query->where('updated_at', '>=', $data['updated_from']);
        }

        if (! empty($data['query'])) {
            $search = $data['query'];
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhere('ean', 'like', '%' . $search . '%')
                    ->orWhere('isbn', 'like', '%' . $search . '%')
                    ->orWhere('itemid', 'like', '%' . $search . '%');
            });
        }

        $paginator = $query
            ->orderBy('name')
            ->paginate((int) ($data['limit'] ?? 100));

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Product $product) => $formatter->article($product))
                ->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function publishers(Request $request, PelionPayloadFormatter $formatter)
    {
        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1',
            'query' => 'nullable|string|max:120',
            'active' => 'nullable|boolean',
            'updated_from' => 'nullable|date',
        ]);

        $query = Publisher::query();

        if ($request->has('active')) {
            $query->where('status', $request->boolean('active'));
        } else {
            $query->where('status', true);
        }

        if (! empty($data['updated_from'])) {
            $query->where('updated_at', '>=', $data['updated_from']);
        }

        if (! empty($data['query'])) {
            $query->where('title', 'like', '%' . $data['query'] . '%');
        }

        $paginator = $query
            ->orderBy('title')
            ->paginate((int) ($data['limit'] ?? 100));

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Publisher $publisher) => $formatter->publisher($publisher))
                ->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    private function applyOrderStatusFilter(Builder $query, string $status): void
    {
        if ($status === 'all') {
            return;
        }

        if ($status !== 'ready_for_invoice') {
            $query->where('pelion_status', $status);

            return;
        }

        $query
            ->where(function (Builder $query) {
                $query->whereNull('pelion_status')
                    ->orWhere('pelion_status', 'ready_for_invoice');
            })
            ->whereNull('pelion_invoiced_at')
            ->where(function (Builder $query) {
                $query->whereNull('invoice')
                    ->orWhere('invoice', '');
            })
            ->whereNotIn('order_status_id', $this->excludedOrderStatusIds())
            ->whereDoesntHave('products', function (Builder $query) {
                $query->where('product_id', '>', 0)
                    ->where(function (Builder $query) {
                        $query->whereDoesntHave('product')
                            ->orWhereHas('product', function (Builder $query) {
                                $query->whereNull('itemid')
                                    ->orWhere('itemid', '<=', 0);
                            });
                    });
            });
    }

    private function applyDateFilters(Builder $query, array $data): void
    {
        $dateFrom = $data['date_from'] ?? config('services.pelion.orders_from');

        if ($dateFrom) {
            $query->where('created_at', '>=', $this->dateBoundary($dateFrom, false));
        }

        if (! empty($data['date_to'])) {
            $query->where('created_at', '<=', $this->dateBoundary($data['date_to'], true));
        }

        if (! empty($data['updated_from'])) {
            $query->where('updated_at', '>=', $data['updated_from']);
        }
    }

    private function applyOrderSearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $orderPrefix = (string) config('services.pelion.order_prefix', 'WEB-');
        $searchId = preg_replace('/^' . preg_quote($orderPrefix, '/') . '/i', '', trim($search));

        $query->where(function (Builder $query) use ($search, $searchId) {
            if (is_numeric($searchId)) {
                $query->orWhere('id', (int) $searchId);
            }

            $query->orWhere('payment_fname', 'like', '%' . $search . '%')
                ->orWhere('payment_lname', 'like', '%' . $search . '%')
                ->orWhere('payment_email', 'like', '%' . $search . '%')
                ->orWhere('shipping_fname', 'like', '%' . $search . '%')
                ->orWhere('shipping_lname', 'like', '%' . $search . '%');
        });
    }

    private function excludedOrderStatusIds(): array
    {
        return array_values(array_unique(array_map('intval', array_filter([
            config('settings.order.status.canceled'),
            config('settings.order.status.declined'),
            config('settings.order.status.unfinished'),
        ], fn ($status) => $status !== null))));
    }

    private function dateBoundary(string $value, bool $endOfDay): Carbon
    {
        $date = Carbon::parse($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        }

        return $date;
    }

    private function historyComment(array $data): string
    {
        if ($data['status'] === 'invoiced') {
            return 'Pelion je izradio racun' . (! empty($data['invoice_number']) ? ': ' . $data['invoice_number'] : '') . '.';
        }

        if ($data['status'] === 'imported_to_pelion') {
            return 'Pelion je uvezao narudzbu.';
        }

        return 'Pelion je vratio gresku' . (! empty($data['message']) ? ': ' . $data['message'] : '') . '.';
    }
}
