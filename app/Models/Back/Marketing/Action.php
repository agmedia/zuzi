<?php

namespace App\Models\Back\Marketing;

use App\Helpers\Currency;
use App\Helpers\Helper;
use App\Models\Back\Catalog\Category;
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
    protected $appends = ['discount_text', 'selection_text'];

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
        if (self::isCombinedCategoryGroup((string) $this->group)) {
            return 'Kombinirano';
        }

        if ($this->type === 'F') {
            return Currency::main($this->discount, true);
        }
        if ($this->type === 'P') {
            return number_format($this->discount) . ' %';
        }
        return $this->discount;
    }

    public function getSelectionTextAttribute(): string
    {
        if (self::isCombinedCategoryGroup((string) $this->group)) {
            $rules = self::normalizeCombinedCategoryRules(is_array($this->data) ? $this->data : []);

            if (empty($rules)) {
                return '';
            }

            $titles = Category::query()
                ->whereIn('id', collect($rules)->pluck('category_id')->all())
                ->pluck('title', 'id');

            return collect($rules)
                ->map(function (array $rule) use ($titles) {
                    $title = $titles->get($rule['category_id'], '#' . $rule['category_id']);
                    $scope = $rule['apply_to'] === 'used' ? 'rabljene' : 'sve';

                    return $title . ' ' . rtrim(rtrim(number_format((float) $rule['discount'], 2, '.', ''), '0'), '.') . '% (' . $scope . ')';
                })
                ->implode(', ');
        }

        if ((string) $this->group === 'category') {
            $category_ids = collect(json_decode((string) $this->getRawOriginal('links'), true))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            if ($category_ids->isEmpty()) {
                return '';
            }

            return Category::query()
                ->whereIn('id', $category_ids)
                ->pluck('title')
                ->implode(', ');
        }

        return '';
    }

    public function validateRequest(Request $request)
    {
        $resolved_group = (string) $request->input('group', $request->input('action_group'));

        if ($resolved_group !== '') {
            $request->merge(['group' => $resolved_group]);
        }

        $is_combined_category = self::isCombinedCategoryGroup($resolved_group);

        $request->validate([
            'title'    => 'required',
            'type'     => $is_combined_category ? 'nullable' : 'required',   // 'F' = fixed amount, 'P' = percent
            'group'    => 'required',   // product|category|author|publisher|all|total
            'discount' => $is_combined_category ? 'nullable' : 'required'
        ]);

        $this->request = $request;

        if ($is_combined_category) {
            $request->validate([
                'combined_categories' => 'required|array|min:1',
                'combined_categories.*.category_id' => 'required|integer|exists:categories,id',
                'combined_categories.*.discount' => 'required|numeric|min:0.01',
                'combined_categories.*.apply_to' => 'required|in:all,used',
            ]);

            $request->merge([
                'type' => 'P',
                'discount' => $this->resolveCombinedCategoryDiscount(),
            ]);
        } elseif ($this->listRequired()) {
            $request->validate(['action_list' => 'required']);
        }

        return $this;
    }

    public function create()
    {
        $data = $this->getRequestData();
        $id   = $this->insertGetId($this->getModelArray());

        if ($id) {
            if (self::isCategoryLikeGroup((string) $this->request->group)) {
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
            if (self::isCategoryLikeGroup($previous_group) || self::isCategoryLikeGroup((string) $this->request->group)) {
                $this->syncCategoryProductsForAction($this->id, $data, $existing_action_product_ids);
            } elseif ($this->shouldUpdateProducts($data)) {
                $this->updateProducts($this->resolveTarget($data['links']), $this->id, $data);
            }
            if (! self::isCategoryLikeGroup($previous_group) && $this->shouldRemoveActions($data)) {
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

    public function remove(): bool
    {
        $action_id = (int) $this->id;
        $group = (string) $this->group;
        $affected_product_ids = Product::query()
            ->where('action_id', $action_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        return (bool) DB::transaction(function () use ($action_id, $group, $affected_product_ids) {
            $deleted = $this->delete();

            if (! $deleted) {
                return false;
            }

            if ($affected_product_ids->isEmpty()) {
                return true;
            }

            if (self::isCategoryLikeGroup($group)) {
                self::syncCategoryActionsForProducts($affected_product_ids);
                self::cleanupDetachedProductSpecials();
                return true;
            }

            Product::query()
                ->whereIn('id', $affected_product_ids)
                ->update([
                    'action_id' => 0,
                    'special' => null,
                    'special_from' => null,
                    'special_to' => null,
                    'special_lock' => 0,
                ]);

            self::cleanupDetachedProductSpecials();

            return true;
        });
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
            'type'       => $this->request->type ?: 'P',
            'discount'   => $this->request->discount ?: 0,
            'group'      => $this->request->group,
            'links'      => $this->normalizeLinks($data['links'])->toJson(),
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
        $data = $this->setActionData();
        $links = collect([$this->request->group]);

        if (self::isCombinedCategoryGroup((string) $this->request->group)) {
            $links = collect($data['combined_categories'] ?? [])
                ->pluck('category_id');
        } elseif ($this->request->action_list) {
            $links = collect($this->request->action_list);
        }

        return [
            'links'           => $this->normalizeLinks($links),
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

        if ($this->request->min !== null && $this->request->min !== '') {
            $resp['min'] = $this->request->min;
        }
        if ($this->request->max !== null && $this->request->max !== '') {
            $resp['max'] = $this->request->max;
        }
        if (self::isCombinedCategoryGroup((string) $this->request->group)) {
            $resp['combined_categories'] = $this->resolveCombinedCategoryRules();
        }

        return $resp;
    }

    private function normalizeLinks(Collection $links): Collection
    {
        if ((string) $this->request->group === 'all' && (string) $links->first() === 'all') {
            return collect(['all']);
        }

        return $links
            ->flatten()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
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
        return !in_array($this->request->group, ['all', 'total', 'combined_category']);
    }

    /**
     * UVIJEK vrati listu ID-eva (jedan stupac) – nema više cardinality greške.
     */
    private function resolveTarget($links)
    {
        $links = collect($links)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : $id)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->values();

        if (in_array($this->request->group, ['product', 'category', 'combined_category', 'author', 'publisher', 'all'])) {
            $products = Product::query()
                ->where('special_lock', 0);

            if ($this->request->group === 'product') {
                $products->whereIn('id', $links);
            } elseif (in_array($this->request->group, ['category', 'combined_category'], true)) {
                if ($links->isEmpty()) {
                    return collect();
                }

                $products->whereExists(function ($query) use ($links) {
                    $query->select(DB::raw(1))
                        ->from('product_category as pc')
                        ->whereColumn('pc.product_id', 'products.id')
                        ->whereIn('pc.category_id', $links->all());
                });
            } elseif ($this->request->group === 'author') {
                $products->whereIn('author_id', $links);
            } elseif ($this->request->group === 'publisher') {
                $products->whereIn('publisher_id', $links);
            } elseif ($this->request->group === 'all' && $links->first() !== 'all') {
                $products->whereNotIn('publisher_id', $links);
            }

            return $products->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
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
        $start = $data['start'] ? Carbon::make($data['start'])->toDateTimeString() : null;
        $end   = $data['end'] ? Carbon::make($data['end'])->toDateTimeString() : null;

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
                    'id' => $p->id,
                    'special' => $newSpecial,
                    'action_id' => $id,
                    'special_from' => $start,
                    'special_to' => $end,
                    'special_lock' => (int) $data['lock'],
                ];
            }
        }

        if (empty($rows)) {
            return; // ništa za ažurirati
        }

        self::bulkUpdateResolvedProductRows($rows);
    }

    private function resolveCombinedCategoryDiscount(): float
    {
        return (float) collect($this->resolveCombinedCategoryRules())
            ->pluck('discount')
            ->map(fn ($discount) => (float) $discount)
            ->max();
    }

    /**
     * @return array<int, array{category_id:int, discount:float, apply_to:string}>
     */
    private function resolveCombinedCategoryRules(): array
    {
        return self::normalizeCombinedCategoryRules([
            'combined_categories' => $this->request->input('combined_categories', []),
        ]);
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array<int, array{category_id:int, discount:float, apply_to:string}>
     */
    private static function normalizeCombinedCategoryRules(?array $data): array
    {
        $rules = collect($data['combined_categories'] ?? []);

        return $rules
            ->map(function ($rule) {
                if (is_object($rule)) {
                    $rule = (array) $rule;
                }

                $category_id = (int) ($rule['category_id'] ?? 0);
                $discount = isset($rule['discount']) ? (float) $rule['discount'] : 0.0;
                $apply_to = ($rule['apply_to'] ?? 'all') === 'used' ? 'used' : 'all';

                return [
                    'category_id' => $category_id,
                    'discount' => $discount,
                    'apply_to' => $apply_to,
                ];
            })
            ->filter(fn (array $rule) => $rule['category_id'] > 0 && $rule['discount'] > 0)
            ->values()
            ->all();
    }

    private static function isCategoryLikeGroup(?string $group): bool
    {
        return in_array((string) $group, ['category', 'combined_category'], true);
    }

    private static function isCombinedCategoryGroup(?string $group): bool
    {
        return (string) $group === 'combined_category';
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
        self::syncCategoryActionsForProducts(collect([$product_id]));
    }

    /**
     * Refresh category-based actions so future/expired windows are applied without manual edits.
     */
    public static function syncScheduledCategoryActions(): int
    {
        $actions = self::query()
            ->whereIn('group', ['category', 'combined_category'])
            ->get(['id', 'group', 'type', 'discount', 'links', 'date_start', 'date_end', 'data', 'lock']);

        $action_ids = $actions
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $category_ids = $actions
            ->flatMap(function (self $action) {
                return self::resolveCategoryRulesForAction($action)
                    ->pluck('category_id');
            })
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $product_ids = collect();

        if ($action_ids->isNotEmpty()) {
            $product_ids = $product_ids->merge(
                Product::query()
                    ->whereIn('action_id', $action_ids)
                    ->pluck('id')
            );
        }

        if ($category_ids->isNotEmpty()) {
            $product_ids = $product_ids->merge(
                ProductCategory::query()
                    ->whereIn('category_id', $category_ids)
                    ->pluck('product_id')
            );
        }

        $product_ids = $product_ids
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($product_ids->isNotEmpty()) {
            self::syncCategoryActionsForProducts($product_ids);
        }

        self::cleanupDetachedProductSpecials();

        return $product_ids->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resolveBestCategoryActionForProduct(Product $product, Collection $category_ids, ?Collection $rules_by_category = null): ?array
    {
        if ($category_ids->isEmpty()) {
            return null;
        }

        $rules_by_category = $rules_by_category ?: self::resolveActiveCategoryRulesByCategory();
        $best_action = null;
        $best_special = null;
        $price = (float) $product->price;
        $is_used_book = self::isUsedBook($product->condition ?? null);

        foreach ($category_ids as $category_id) {
            /** @var Collection<int, array<string, mixed>> $rules */
            $rules = $rules_by_category->get((int) $category_id, collect());

            foreach ($rules as $rule) {
                $min = $rule['min'];
                $max = $rule['max'];

                if ($rule['apply_to'] === 'used' && ! $is_used_book) {
                    continue;
                }

                if ($min !== null && $price <= $min) {
                    continue;
                }

                if ($max !== null && $price >= $max) {
                    continue;
                }

                $special = (float) Helper::calculateDiscountPrice($price, $rule['discount'], $rule['type']);

                if ($best_special === null || $special < $best_special) {
                    $best_special = $special;
                    $best_action = [
                        'action_id' => $rule['action_id'],
                        'special' => $special,
                        'special_from' => $rule['special_from'],
                        'special_to' => $rule['special_to'],
                        'special_lock' => $rule['special_lock'],
                    ];
                }
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
        self::bulkApplyResolvedActions([
            array_merge(['id' => $product_id], $resolved_action),
        ]);
    }

    private static function clearCategoryActionFromProduct(int $product_id): void
    {
        self::bulkClearResolvedActions([$product_id]);
    }

    private function syncCategoryProductsForAction(int $action_id, array $data, ?Collection $existing_action_product_ids = null): void
    {
        $existing_action_product_ids = ($existing_action_product_ids ?: collect())
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $target_ids = collect();

        if (! self::isCategoryLikeGroup((string) $this->request->group) && $existing_action_product_ids->isNotEmpty()) {
            Product::query()
                ->where('action_id', $action_id)
                ->whereIn('id', $existing_action_product_ids)
                ->update([
                    'action_id' => 0,
                    'special' => null,
                    'special_from' => null,
                    'special_to' => null,
                    'special_lock' => 0,
                ]);
        }

        if ((bool) $data['status'] && self::isCategoryLikeGroup((string) $this->request->group)) {
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

        self::syncCategoryActionsForProducts($affected_ids);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function resolveCategoryRulesForAction(self $action): Collection
    {
        $data = is_array($action->data) ? $action->data : [];
        $min = isset($data['min']) && $data['min'] !== '' ? (float) $data['min'] : null;
        $max = isset($data['max']) && $data['max'] !== '' ? (float) $data['max'] : null;
        $base_rule = [
            'action_id' => (int) $action->id,
            'special_from' => $action->date_start ? Carbon::make($action->date_start)->toDateTimeString() : null,
            'special_to' => $action->date_end ? Carbon::make($action->date_end)->toDateTimeString() : null,
            'special_lock' => (int) ($action->lock ?? 0),
            'min' => $min,
            'max' => $max,
        ];

        if (self::isCombinedCategoryGroup((string) $action->group)) {
            return collect(self::normalizeCombinedCategoryRules($data))
                ->map(function (array $rule) use ($base_rule) {
                    return array_merge($base_rule, [
                        'category_id' => (int) $rule['category_id'],
                        'discount' => (float) $rule['discount'],
                        'type' => 'P',
                        'apply_to' => $rule['apply_to'],
                    ]);
                })
                ->values();
        }

        $category_ids = collect(json_decode((string) $action->getRawOriginal('links'), true))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        return $category_ids
            ->map(function (int $category_id) use ($action, $base_rule) {
                return array_merge($base_rule, [
                    'category_id' => $category_id,
                    'discount' => (float) $action->discount,
                    'type' => (string) $action->type,
                    'apply_to' => 'all',
                ]);
            })
            ->values();
    }

    private static function syncCategoryActionsForProducts(Collection $product_ids): void
    {
        $product_ids = $product_ids
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($product_ids->isEmpty()) {
            return;
        }

        $rules_by_category = self::resolveActiveCategoryRulesByCategory();

        foreach ($product_ids->chunk(500) as $chunk) {
            $products = Product::query()
                ->whereIn('id', $chunk)
                ->get([
                    'id',
                    'price',
                    'special',
                    'special_from',
                    'special_to',
                    'special_lock',
                    'action_id',
                    'condition',
                ]);

            if ($products->isEmpty()) {
                continue;
            }

            $category_map = ProductCategory::query()
                ->whereIn('product_id', $products->pluck('id'))
                ->get(['product_id', 'category_id'])
                ->groupBy('product_id')
                ->map(function (Collection $items) {
                    return $items
                        ->pluck('category_id')
                        ->map(fn ($id) => (int) $id)
                        ->filter()
                        ->unique()
                        ->values();
                });

            $action_groups = self::query()
                ->whereIn('id', $products->pluck('action_id')->filter()->unique())
                ->get(['id', 'group'])
                ->pluck('group', 'id');

            $rows_to_apply = [];
            $product_ids_to_clear = [];

            foreach ($products as $product) {
                if ((int) $product->special_lock === 1) {
                    continue;
                }

                $current_action_id = (int) $product->action_id;
                $current_action_group = $current_action_id > 0 ? $action_groups->get($current_action_id) : null;
                $has_stale_action_reference = $current_action_id > 0 && $current_action_group === null;
                $best_action = self::resolveBestCategoryActionForProduct(
                    $product,
                    $category_map->get($product->id, collect()),
                    $rules_by_category
                );

                if (self::isCategoryLikeGroup($current_action_group)) {
                    if ($best_action) {
                        $rows_to_apply[] = array_merge(['id' => (int) $product->id], $best_action);
                    } else {
                        $product_ids_to_clear[] = (int) $product->id;
                    }

                    continue;
                }

                if (! $best_action) {
                    if ($has_stale_action_reference) {
                        $product_ids_to_clear[] = (int) $product->id;
                    }

                    continue;
                }

                if (self::shouldApplyResolvedAction($product, (float) $best_action['special'])) {
                    $rows_to_apply[] = array_merge(['id' => (int) $product->id], $best_action);
                }
            }

            self::bulkApplyResolvedActions($rows_to_apply);
            self::bulkClearResolvedActions($product_ids_to_clear);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, array<string, mixed>>>
     */
    private static function resolveActiveCategoryRulesByCategory(): Collection
    {
        $actions = self::query()
            ->whereIn('group', ['category', 'combined_category'])
            ->where('status', 1)
            ->where(function ($query) {
                $query->whereNull('date_start')->orWhere('date_start', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('date_end')->orWhere('date_end', '>=', now());
            })
            ->get(['id', 'group', 'type', 'discount', 'links', 'date_start', 'date_end', 'data', 'lock']);

        $rules = collect();

        foreach ($actions as $action) {
            foreach (self::resolveCategoryRulesForAction($action) as $rule) {
                $category_id = (int) ($rule['category_id'] ?? 0);

                if ($category_id <= 0) {
                    continue;
                }

                $bucket = $rules->get($category_id, collect());
                $bucket->push($rule);
                $rules->put($category_id, $bucket);
            }
        }

        return $rules;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function bulkApplyResolvedActions(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        self::bulkUpdateResolvedProductRows($rows);
    }

    /**
     * @param array<int, int> $product_ids
     */
    private static function bulkClearResolvedActions(array $product_ids): void
    {
        $product_ids = collect($product_ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($product_ids)) {
            return;
        }

        foreach (array_chunk($product_ids, 500) as $chunk) {
            Product::query()
                ->whereIn('id', $chunk)
                ->update([
                    'action_id' => 0,
                    'special' => null,
                    'special_from' => null,
                    'special_to' => null,
                    'special_lock' => 0,
                ]);
        }
    }

    private static function cleanupDetachedProductSpecials(): void
    {
        $existing_action_ids = self::query()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $clear_payload = [
            'action_id' => 0,
            'special' => null,
            'special_from' => null,
            'special_to' => null,
            'special_lock' => 0,
        ];

        if ($existing_action_ids->isEmpty()) {
            Product::query()
                ->where('special_lock', 0)
                ->where(function ($query) {
                    $query->whereNotNull('special')
                        ->orWhere('action_id', '>', 0);
                })
                ->update($clear_payload);

            return;
        }

        Product::query()
            ->where('special_lock', 0)
            ->where('action_id', '>', 0)
            ->whereNotIn('action_id', $existing_action_ids)
            ->update($clear_payload);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function bulkUpdateResolvedProductRows(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            $ids = [];
            $special_cases = [];
            $special_bindings = [];
            $action_id_cases = [];
            $action_id_bindings = [];
            $special_from_cases = [];
            $special_from_bindings = [];
            $special_to_cases = [];
            $special_to_bindings = [];
            $special_lock_cases = [];
            $special_lock_bindings = [];

            foreach ($chunk as $row) {
                $id = (int) ($row['id'] ?? 0);

                if ($id <= 0) {
                    continue;
                }

                $ids[] = $id;
                $special_cases[] = 'WHEN ' . $id . ' THEN ?';
                $special_bindings[] = $row['special'];
                $action_id_cases[] = 'WHEN ' . $id . ' THEN ?';
                $action_id_bindings[] = (int) ($row['action_id'] ?? 0);
                $special_from_cases[] = 'WHEN ' . $id . ' THEN ?';
                $special_from_bindings[] = $row['special_from'] ?? null;
                $special_to_cases[] = 'WHEN ' . $id . ' THEN ?';
                $special_to_bindings[] = $row['special_to'] ?? null;
                $special_lock_cases[] = 'WHEN ' . $id . ' THEN ?';
                $special_lock_bindings[] = (int) ($row['special_lock'] ?? 0);
            }

            if (empty($ids)) {
                continue;
            }

            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $sql = 'UPDATE products SET '
                . 'special = CASE id ' . implode(' ', $special_cases) . ' ELSE special END, '
                . 'action_id = CASE id ' . implode(' ', $action_id_cases) . ' ELSE action_id END, '
                . 'special_from = CASE id ' . implode(' ', $special_from_cases) . ' ELSE special_from END, '
                . 'special_to = CASE id ' . implode(' ', $special_to_cases) . ' ELSE special_to END, '
                . 'special_lock = CASE id ' . implode(' ', $special_lock_cases) . ' ELSE special_lock END '
                . 'WHERE id IN (' . $placeholders . ')';

            DB::update($sql, array_merge(
                $special_bindings,
                $action_id_bindings,
                $special_from_bindings,
                $special_to_bindings,
                $special_lock_bindings,
                $ids
            ));
        }
    }

    private static function isUsedBook(?string $condition): bool
    {
        $normalized_condition = mb_strtolower(trim((string) $condition), 'UTF-8');

        return ! in_array($normalized_condition, ['novo', 'nova knjiga'], true);
    }
}
