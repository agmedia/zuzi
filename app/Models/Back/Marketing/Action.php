<?php

namespace App\Models\Back\Marketing;

use App\Helpers\Currency;
use App\Helpers\Helper;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductCategory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Action extends Model
{
    protected $table = 'product_actions';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $appends = ['discount_text'];

    /** @var Request */
    protected $request;

    public function getDataAttribute($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return json_decode($value, true);
    }

    public function getDiscountTextAttribute($value)
    {
        if ($this->type === 'F') {
            return Currency::main($this->discount, true);
        }
        if ($this->type === 'P') {
            return number_format($this->discount) . ' %';
        }
        return $this->discount;
    }

    public function validateRequest(Request $request)
    {
        $request->validate([
            'title'    => 'required',
            'type'     => 'required',   // 'F' = fixed amount, 'P' = percent
            'group'    => 'required',   // product|category|author|publisher|all|total
            'discount' => 'required'
        ]);

        $this->request = $request;

        if ($this->listRequired()) {
            $request->validate(['action_list' => 'required']);
        }

        return $this;
    }

    public function create()
    {
        $data = $this->getRequestData();
        $id   = $this->insertGetId($this->getModelArray());

        if ($id) {
            if ($this->request->group === 'category') {
                $this->syncCategoryProductsForAction($id, $data);
            } elseif ($this->shouldUpdateProducts($data)) {
                $this->updateProducts($this->resolveTarget($data['links']), $id, $data);
            }
            return $this->find($id);
        }

        return false;
    }

    public function edit()
    {
        $existing_action_product_ids = Product::query()
            ->where('action_id', $this->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $previous_group = $this->group;
        $data    = $this->getRequestData();
        $updated = $this->update($this->getModelArray(false));

        if ($updated) {
            if ($previous_group === 'category' || $this->request->group === 'category') {
                $this->syncCategoryProductsForAction($this->id, $data, $existing_action_product_ids);
            } elseif ($this->shouldUpdateProducts($data)) {
                $this->updateProducts($this->resolveTarget($data['links']), $this->id, $data);
            }
            if ($previous_group !== 'category' && $this->shouldRemoveActions($data)) {
                $this->deleteProductActions();
            }
            return $this;
        }

        return false;
    }

    public function isValid(string $coupon = ''): bool
    {
        $is_valid = false;

        $from = now()->subDay();
        $to   = now()->addDay();

        if ($this->date_start && $this->date_start !== '0000-00-00 00:00:00') {
            $from = Carbon::make($this->date_start);
        }
        if ($this->date_end && $this->date_end !== '0000-00-00 00:00:00') {
            $to = Carbon::make($this->date_end);
        }

        if ($from <= now() && now() <= $to) {
            $is_valid = true;
        }

        if ($is_valid) {
            $is_valid = false;

            if ($this->coupon && $coupon !== '' && Helper::couponEquals($coupon, $this->coupon)) {
                $is_valid = true;
            }

            if (!$this->coupon) {
                $is_valid = true;
            }
        }

        return $is_valid;
    }

    public function setConditionAttributes(string $coupon = ''): array
    {
        $response = ['type' => '', 'description' => ''];

        if ($coupon !== '') {
            $response = ['type' => 'coupon', 'description' => Helper::normalizeCoupon($coupon)];
        }

        return $response;
    }

    public function deleteProductActions(int $action_id = 0): int
    {
        if (!$action_id) {
            $action_id = $this->id;
        }

        return Product::query()
            ->where('action_id', $action_id)
            ->update([
                'action_id'    => 0,
                'special'      => null,
                'special_from' => null,
                'special_to'   => null,
                'special_lock' => 0,
            ]);
    }

    /*******************************************************************************
     *                                Copyright : AG media                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    private function getModelArray(bool $insert = true): array
    {
        $data = $this->getRequestData();

        $resp = [
            'title'      => $this->request->title,
            'type'       => $this->request->type,
            'discount'   => $this->request->discount,
            'group'      => $this->request->group,
            'links'      => $data['links']->flatten()->toJson(),
            'date_start' => $data['start'],
            'date_end'   => $data['end'],
            'data'       => $data['data'],
            'coupon'     => $this->request->coupon,
            'quantity'   => $data['coupon_quantity'],
            'lock'       => $data['lock'],
            'status'     => $data['status'],
            'updated_at' => Carbon::now(),
        ];

        if ($insert) {
            $resp['created_at'] = Carbon::now();
        }

        return $resp;
    }

    private function getRequestData(): array
    {
        $links = collect([$this->request->group]);
        if ($this->request->action_list) {
            $links = collect($this->request->action_list);
        }

        $data = $this->setActionData();

        return [
            'links'           => $links,
            'status'          => (isset($this->request->status) && $this->request->status === 'on') ? 1 : 0,
            'start'           => $this->request->date_start ? Carbon::make($this->request->date_start) : null,
            'end'             => $this->request->date_end ? Carbon::make($this->request->date_end) : null,
            'coupon_quantity' => (isset($this->request->coupon_quantity) && $this->request->coupon_quantity === 'on') ? 1 : 0,
            'lock'            => (isset($this->request->lock) && $this->request->lock === 'on') ? 1 : 0,
            'data'            => !empty($data) ? collect($data)->toJson() : null,
        ];
    }

    private function setActionData(): array
    {
        $resp = [];
        if ($this->request->min) { $resp['min'] = $this->request->min; }
        if ($this->request->max) { $resp['max'] = $this->request->max; }
        return $resp;
    }

    private function shouldUpdateProducts(array $data): bool
    {
        if ($this->request->group === 'total') {
            return false;
        }
        return (bool)$data['status'];
    }

    private function shouldRemoveActions(array $data): bool
    {
        return !$data['status'];
    }

    private function listRequired(): bool
    {
        return !in_array($this->request->group, ['all', 'total']);
    }

    /**
     * UVIJEK vrati listu ID-eva (jedan stupac) – nema više cardinality greške.
     */
    private function resolveTarget($links)
    {
        if (in_array($this->request->group, ['product', 'category', 'author', 'publisher', 'all'])) {
            $products = Product::query()
                ->where('special_lock', 0);

            if ($this->request->group === 'product') {
                $products->whereIn('id', $links);
            } elseif ($this->request->group === 'category') {
                $ids = ProductCategory::whereIn('category_id', $links)->pluck('product_id');
                $products->whereIn('id', $ids);
            } elseif ($this->request->group === 'author') {
                $products->whereIn('author_id', $links);
            } elseif ($this->request->group === 'publisher') {
                $products->whereIn('publisher_id', $links);
            } elseif ($this->request->group === 'all' && $links->first() !== 'all') {
                $products->whereNotIn('publisher_id', $links);
            }

            return $products->pluck('id')->unique();
        }

        return collect(); // fallback
    }

    /**
     * Primijeni pravilo:
     * - Ako nema popusta -> postavi novi
     * - Ako ima popust -> mijenjaj samo ako je NOVI popust VEĆI (nova special cijena < stara special)
     */
    private function updateProducts($target, int $id, array $data): void
    {
        $rows = [];

        $products = Product::query()
            ->where('special_lock', 0);

        if ($target->isNotEmpty()) {
            $products->whereIn('id', $target);
        }

        if ($this->request->min) {
            $products->where('price', '>', $this->request->min);
        }
        if ($this->request->max) {
            $products->where('price', '<', $this->request->max);
        }

        // trebaju nam i datumi da procijenimo aktivnost starog popusta
        $products = $products->get(['id', 'price', 'special', 'special_from', 'special_to']);

        $now = Carbon::now();

        foreach ($products as $p) {
            $price       = (float)$p->price;
            $oldSpecial  = $p->special !== null ? (float)$p->special : null;

            // izračunaj novu special cijenu po traženom popustu
            $newSpecial = (float) Helper::calculateDiscountPrice($price, $this->request->discount, $this->request->type);

            // je li stari popust AKTIVAN sada?
            $from = $p->special_from ? Carbon::make($p->special_from) : null;
            $to   = $p->special_to   ? Carbon::make($p->special_to)   : null;

            $oldActiveNow =
                ($oldSpecial !== null && $oldSpecial > 0) &&
                (is_null($from) || $from->lte($now)) &&
                (is_null($to)   || $now->lte($to));

            $shouldApply = false;

            if (is_null($oldSpecial) || $oldSpecial <= 0) {
                // nema starog popusta -> postavi novi
                $shouldApply = true;
            } else {
                if ($oldActiveNow) {
                    // stari je AKTIVAN -> mijenjaj samo ako je novi JAČI (niža special cijena)
                    $shouldApply = $newSpecial < $oldSpecial;
                } else {
                    // stari NIJE aktivan (istekao ili još nije počeo) -> tretiraj kao “nema popusta”
                    $shouldApply = true;
                }
            }

            if ($shouldApply) {
                $rows[] = [
                    'product_id' => $p->id,
                    'special'    => $newSpecial,
                ];
            }
        }

        if (empty($rows)) {
            return; // ništa za ažurirati
        }

        DB::table('temp_table')->truncate();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('temp_table')->insert($chunk);
        }

        $start = $data['start'] ? Carbon::make($data['start'])->toDateTimeString() : null;
        $end   = $data['end'] ? Carbon::make($data['end'])->toDateTimeString() : null;

        DB::update(
            'UPDATE products p
         INNER JOIN temp_table tt ON p.id = tt.product_id
         SET p.special = tt.special,
             p.action_id = ?,
             p.special_from = ?,
             p.special_to = ?,
             p.special_lock = ?',
            [$id, $start, $end, $data['lock']]
        );

        DB::table('temp_table')->truncate();
    }


    /*******************************************************************************
     *                                Copyright : AG media                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    public static function createFromProduct(int $product_id, Request $request, int $action_id = 0): int
    {
        if ($action_id) {
            $has = self::query()->where('id', $action_id)->first();
            if ($has) {
                return $has->id;
            }
        }

        $discount = $request->price;

        if ($request->special && $request->special < $discount) {
            $discount = $request->price - $request->special;
        }

        return self::query()->insertGetId([
            'title'      => 'Posebne ponuda',
            'type'       => 'F',
            'discount'   => $discount,
            'group'      => 'single',
            'links'      => '["' . $product_id . '"]',
            'date_start' => $request->special_from ? Carbon::make($request->special_from) : null,
            'date_end'   => $request->special_to ? Carbon::make($request->special_to) : null,
            'data'       => null,
            'coupon'     => null,
            'quantity'   => 0,
            'lock'       => 1,
            'status'     => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Sync the best active category action to a specific product.
     */
    public static function syncCategoryActionForProduct(int $product_id): void
    {
        $product = Product::query()->find($product_id, [
            'id',
            'price',
            'special',
            'special_from',
            'special_to',
            'special_lock',
            'action_id',
        ]);

        if (! $product || $product->special_lock) {
            return;
        }

        $currentAction = null;

        if ($product->action_id) {
            $currentAction = self::query()->find($product->action_id, ['id', 'group']);
        }

        $category_ids = ProductCategory::query()
            ->where('product_id', $product_id)
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $best_action = self::resolveBestCategoryActionForProduct($product, $category_ids);

        if ($currentAction && $currentAction->group === 'category') {
            if ($best_action) {
                self::applyResolvedActionToProduct($product_id, $best_action);
            } else {
                self::clearCategoryActionFromProduct($product_id);
            }

            return;
        }

        if (! $best_action) {
            return;
        }

        if (self::shouldApplyResolvedAction($product, (float) $best_action['special'])) {
            self::applyResolvedActionToProduct($product_id, $best_action);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resolveBestCategoryActionForProduct(Product $product, Collection $category_ids): ?array
    {
        if ($category_ids->isEmpty()) {
            return null;
        }

        $actions = self::query()
            ->where('group', 'category')
            ->where('status', 1)
            ->where(function ($query) {
                $query->whereNull('date_start')->orWhere('date_start', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('date_end')->orWhere('date_end', '>=', now());
            })
            ->get(['id', 'type', 'discount', 'links', 'date_start', 'date_end', 'data', 'lock']);

        $best_action = null;
        $best_special = null;
        $price = (float) $product->price;

        foreach ($actions as $action) {
            $links = collect(json_decode((string) $action->getRawOriginal('links'), true))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($links->isEmpty() || $links->intersect($category_ids)->isEmpty()) {
                continue;
            }

            $data = is_array($action->data) ? $action->data : [];
            $min = isset($data['min']) && $data['min'] !== '' ? (float) $data['min'] : null;
            $max = isset($data['max']) && $data['max'] !== '' ? (float) $data['max'] : null;

            if ($min !== null && $price <= $min) {
                continue;
            }

            if ($max !== null && $price >= $max) {
                continue;
            }

            $special = (float) Helper::calculateDiscountPrice($price, $action->discount, $action->type);

            if ($best_special === null || $special < $best_special) {
                $best_special = $special;
                $best_action = [
                    'action_id' => $action->id,
                    'special' => $special,
                    'special_from' => $action->date_start ? Carbon::make($action->date_start)->toDateTimeString() : null,
                    'special_to' => $action->date_end ? Carbon::make($action->date_end)->toDateTimeString() : null,
                    'special_lock' => (int) ($action->lock ?? 0),
                ];
            }
        }

        return $best_action;
    }

    private static function shouldApplyResolvedAction(Product $product, float $new_special): bool
    {
        $old_special = $product->special !== null ? (float) $product->special : null;

        if ($old_special === null || $old_special <= 0) {
            return true;
        }

        $now = Carbon::now();
        $from = $product->special_from ? Carbon::make($product->special_from) : null;
        $to = $product->special_to ? Carbon::make($product->special_to) : null;

        $old_active_now =
            $old_special > 0 &&
            (is_null($from) || $from->lte($now)) &&
            (is_null($to) || $now->lte($to));

        if (! $old_active_now) {
            return true;
        }

        return $new_special < $old_special;
    }

    /**
     * @param array<string, mixed> $resolved_action
     */
    private static function applyResolvedActionToProduct(int $product_id, array $resolved_action): void
    {
        Product::query()
            ->where('id', $product_id)
            ->update([
                'action_id' => $resolved_action['action_id'],
                'special' => $resolved_action['special'],
                'special_from' => $resolved_action['special_from'],
                'special_to' => $resolved_action['special_to'],
                'special_lock' => $resolved_action['special_lock'],
            ]);
    }

    private static function clearCategoryActionFromProduct(int $product_id): void
    {
        Product::query()
            ->where('id', $product_id)
            ->update([
                'action_id' => 0,
                'special' => null,
                'special_from' => null,
                'special_to' => null,
                'special_lock' => 0,
            ]);
    }

    private function syncCategoryProductsForAction(int $action_id, array $data, ?Collection $existing_action_product_ids = null): void
    {
        $existing_action_product_ids = ($existing_action_product_ids ?: collect())
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $target_ids = collect();

        if ((bool) $data['status'] && $this->request->group === 'category') {
            $target_ids = $this->resolveTarget($data['links'])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();
        }

        $affected_ids = $existing_action_product_ids
            ->merge($target_ids)
            ->unique()
            ->values();

        if ($affected_ids->isEmpty()) {
            return;
        }

        foreach ($affected_ids as $product_id) {
            self::syncCategoryActionForProduct((int) $product_id);
        }
    }
}
