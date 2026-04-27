<?php


namespace App\Helpers;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Marketing\Action;
use App\Models\Back\Marketing\Review;
use App\Models\Back\Settings\Settings;
use App\Models\Back\Widget\WidgetGroup;
use App\Models\Front\Blog;
use App\Models\Front\Loyalty;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use App\Services\GiftWrapService;
use Darryldecode\Cart\CartCondition;
use Illuminate\Cache\TaggableStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\Types\False_;
use Illuminate\Support\Facades\DB;

class Helper
{
    /**
     * Prevent duplicate cache fallback warnings within the same request.
     *
     * @var array<string, bool>
     */
    private static array $reportedCacheFallbacks = [];

    public static function normalizeCoupon(?string $coupon = null): string
    {
        return Str::upper(trim((string) $coupon));
    }

    public static function couponEquals(?string $left = null, ?string $right = null): bool
    {
        $left = self::normalizeCoupon($left);
        $right = self::normalizeCoupon($right);

        return $left !== '' && $left === $right;
    }

    /**
     * @param float $price
     * @param int   $discount
     *
     * @return float|int
     */
    public static function calculateDiscountPrice(float $price, int $discount, string $type)
    {
        if ($type == 'F') {
            return $price - $discount;
        }

        return $price - ($price * ($discount / 100));
    }


    /**
     * @param $list_price
     * @param $seling_price
     *
     * @return float|int
     */
    public static function calculateDiscount($list_price, $seling_price, string $type = 'P')
    {
        if (is_string($list_price)) {
            $list_price = str_replace('.', '', $list_price);
            $list_price = str_replace(',', '.', $list_price);
        }
        if (is_string($seling_price)) {
            $seling_price = str_replace('.', '', $seling_price);
            $seling_price = str_replace(',', '.', $seling_price);
        }

        if ($type == 'F') {
            return $list_price - $seling_price;
        }

        return (($list_price - $seling_price) / $list_price) * 100;
    }


    /**
     * @return string[]
     */
    public static function abc()
    {
        return ['A', 'B', 'C', 'Ć', 'Č', 'D', 'Đ', 'Dž', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'Lj', 'M', 'N', 'Nj', 'O', 'P', 'R', 'S', 'Š', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'Ž'];
    }


    /**
     * @param string $target
     * @param bool   $builder
     *
     * @return array|false|Collection
     */
    public static function search(string $target = '', bool $builder = false, bool $api = false)
    {
        if ($target === '') return false;

        $response = collect();
        $raw = trim($target);

        // ---------- tokenizacija ----------
        $tokens = collect(preg_split('/[\s\.,\-_\|]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn($t) => \Illuminate\Support\Str::lower($t))
            ->filter(fn($t) => \Illuminate\Support\Str::length($t) >= 2)
            ->unique()
            ->values();

        $len = max(1, mb_strlen($raw, 'UTF-8'));

        // accent-insensitive collation
        $has0900     = collect(DB::select("SHOW COLLATION LIKE 'utf8mb4_0900_ai_ci'"))->isNotEmpty();
        $aiCollation = $has0900 ? 'utf8mb4_0900_ai_ci' : 'utf8mb4_unicode_ci';

        // kratki/dugi tokeni (kratke ignoriramo za name)
        $shortTokens = $tokens->filter(fn($t) => mb_strlen($t,'UTF-8') <= 3)->values();
        $longTokens  = $tokens->filter(fn($t) => mb_strlen($t,'UTF-8') >= 4)->values();

        // ---------- AUTORI (strogi LIKE po tokenima) ----------
        $authorsQ = \App\Models\Front\Catalog\Author::active();
        if ($tokens->isNotEmpty()) {
            foreach ($tokens as $t) {
                $authorsQ->whereRaw("title COLLATE {$aiCollation} LIKE ?", ['%'.$t.'%']);
            }
        } else {
            $authorsQ->whereRaw("title COLLATE {$aiCollation} LIKE ?", ['%'.$raw.'%']);
        }
        $authorIds = $authorsQ->limit(200)->pluck('id')->all();

        // ---------- SCORE (samo name + sku) ----------
        $scoreParts = [];
        $bindings   = [];

        if (!empty($authorIds)) {
            $idsList = implode(',', array_map('intval', $authorIds));
            $scoreParts[] = "CASE WHEN products.author_id IN ($idsList) THEN 1000 ELSE 0 END";
        }

        // exact fraza u name
        $scoreParts[] = "CASE WHEN products.name COLLATE {$aiCollation} LIKE ? THEN 90 ELSE 0 END";
        $bindings[]   = '%'.$raw.'%';

        // početak riječi + contains za DUGE tokene
        foreach ($longTokens as $t) {
            $scoreParts[] = "CASE WHEN products.name COLLATE {$aiCollation} LIKE ? OR products.name COLLATE {$aiCollation} LIKE ? THEN 28 ELSE 0 END";
            $bindings[]   = $t.'%';
            $bindings[]   = '% '.$t.'%';
            $scoreParts[] = "CASE WHEN products.name COLLATE {$aiCollation} LIKE ? THEN 18 ELSE 0 END";
            $bindings[]   = '%'.$t.'%';
        }

        // sku
        $scoreParts[] = "CASE WHEN products.sku = ? THEN 60 ELSE 0 END";   $bindings[] = $raw;
        $scoreParts[] = "CASE WHEN products.sku LIKE ? THEN 40 ELSE 0 END"; $bindings[] = '%'.$raw.'%';
        foreach ($tokens as $t) {
            $scoreParts[] = "CASE WHEN products.sku LIKE ? THEN 15 ELSE 0 END";
            $bindings[]   = '%'.$t.'%';
        }

        // grupni bonus: svi dugi tokeni u name
        if ($longTokens->isNotEmpty()) {
            $allAnd = $longTokens->map(fn($t) => "products.name COLLATE {$aiCollation} LIKE ?")->implode(' AND ');
            $scoreParts[] = "CASE WHEN ($allAnd) THEN 40 ELSE 0 END";
            foreach ($longTokens as $t) { $bindings[] = '%'.$t.'%'; }
        }

        $scoreSql = '('.implode(' + ', $scoreParts).') AS score';

        // ---------- WHERE (bar jedan relevantan match) ----------
        $whereAny = function($q) use ($raw, $authorIds, $aiCollation, $longTokens) {
            if (!empty($authorIds)) {
                $q->orWhereIn('products.author_id', $authorIds);
            }
            $q->orWhereRaw("products.name COLLATE {$aiCollation} LIKE ?", ['%'.$raw.'%'])
                ->orWhere('products.sku', 'like', '%'.$raw.'%');

            if ($longTokens->isNotEmpty()) {
                foreach ($longTokens as $t) {
                    $q->orWhereRaw("products.name COLLATE {$aiCollation} LIKE ?", ['%'.$t.'%']);
                }
            }
        };

        // ---------- Glavni upit ----------
        $base = \App\Models\Front\Catalog\Product::active()
            ->select('products.id')
            ->selectRaw($scoreSql, $bindings)
            ->where(function($q) use ($whereAny) { $whereAny($q); })
            ->orderByDesc('score')
            ->orderByDesc('products.updated_at');

        $totalAll = (clone $base)->count('products.id');
        $limit    = $api ? 15 : 500;
        $ids      = $base->limit($limit)->pluck('products.id');

        // ---------- Did-You-Mean + FUZZY FALLBACK NA AUTORA ----------
        $suggestion = null;

        // helperi za normalizaciju
        $norm = function (string $s) {
            if (class_exists(\Transliterator::class)) {
                $t = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
                if ($t) { $s = $t->transliterate($s); }
            } else {
                $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
            }
            return mb_strtolower($s, 'UTF-8');
        };
        $splitTokens = function (string $s) {
            preg_match_all('/\p{L}+|\p{N}+/u', $s, $m);
            return $m[0] ?? [];
        };

        $rawN     = $norm($raw);
        $rawParts = $splitTokens($rawN);
        $lenQ     = max(1, mb_strlen($rawN,'UTF-8'));
        $sumMax   = max(2, (int)floor($lenQ/3) + (($lenQ >= 5 && $lenQ <= 7) ? 1 : 0));

        // Fuzzy kandidat autora ako nemamo autora ni (ili) nemamo rezultata
        $fuzzyAuthorId   = null;
        $fuzzyAuthorName = null;

        if (empty($authorIds) || $totalAll === 0) {
            $candAuthors = \App\Models\Front\Catalog\Author::query()
                ->select('id','title')
                ->where('status', 1)
                ->limit(3000)
                ->get();

            $bestScore = PHP_INT_MAX;
            foreach ($candAuthors as $a) {
                $candN   = $norm($a->title);
                $candTok = $splitTokens($candN);
                if (empty($candTok)) continue;

                $whole = levenshtein($rawN, $candN);

                $sum = 0;
                foreach ($rawParts as $rt) {
                    $bestLocal = PHP_INT_MAX;
                    foreach ($candTok as $ct) {
                        $d = levenshtein($rt, $ct);
                        if ($d < $bestLocal) $bestLocal = $d;
                    }
                    $sum += $bestLocal;
                }

                $score = min($whole, $sum);
                if ($score <= $sumMax && $score < $bestScore) {
                    $bestScore      = $score;
                    $fuzzyAuthorId  = $a->id;
                    $fuzzyAuthorName= $a->title;
                }
            }
        }

        // Ako nema rezultata, a imamo fuzzy autora -> vrati njegove proizvode + DYM
        if ($totalAll === 0 && $fuzzyAuthorId) {
            $ids = \App\Models\Front\Catalog\Product::active()
                ->where('author_id', $fuzzyAuthorId)
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->pluck('products.id');

            $totalAll   = \App\Models\Front\Catalog\Product::active()->where('author_id',$fuzzyAuthorId)->count();
            $suggestion = self::canonicalAuthorDisplay($fuzzyAuthorName);
        }

        // Ako ipak ima rezultata, ali nije bilo “strogo” autora, pošalji DYM kao hint
        if ($totalAll > 0 && empty($authorIds) && $fuzzyAuthorName && !$suggestion) {
            $suggestion = self::canonicalAuthorDisplay($fuzzyAuthorName);
        }

        $response->put('products', $ids);
        $response->put('total', $totalAll);
        if ($suggestion) {
            $response->put('suggestion', $suggestion);
        }

        if ($builder) return $response;

        return $response['products']->toJson();
    }


    /**
     * Kanonski prikaz imena autora za UI (npr. "Krleža Miroslav").
     */
    public static function canonicalAuthorDisplay(string $raw): string
    {
        $s = trim(self::cleanNamePunctuation($raw));

        // ne diraj očite organizacije
        if (self::looksLikeOrganization($s)) {
            return $s;
        }

        // ako je “Prezime, Ime” -> “Prezime Ime”
        if (mb_strpos($s, ',') !== false) {
            [$last, $given] = array_map('trim', explode(',', $s, 2));
            $s = trim($last . ' ' . $given);
        }

        // ako je “Ime Prezime” ostavi tako; ako je “Prezime” samo vrati
        $tokens = preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [$s];

        // Ako je više od jedne riječi, kao fallback prikaži “Prezime Ime”
        if (count($tokens) >= 2) {
            $last  = array_pop($tokens);
            $given = implode(' ', $tokens);
            return trim($last . ' ' . $given);
        }

        return $s;
    }

    /**
     * Kanonski ključ za grupiranje duplikata autora (lowercase, bez dijakritike/znakova).
     */
    public static function canonicalAuthorKey(string $raw): string
    {
        $disp  = self::canonicalAuthorDisplay($raw);
        $disp  = preg_replace('/\s+/u', ' ', trim($disp));
        $ascii = self::unaccent($disp);
        $ascii = mb_strtolower($ascii, 'UTF-8');
        $ascii = preg_replace('/[^a-z0-9\s]/', '', $ascii);
        $ascii = preg_replace('/\s+/', '_', trim($ascii));
        return $ascii ?: 'unknown';
    }

    /* ===== Interni helperi za gornje metode ===== */

    private static function looksLikeOrganization(string $s): bool
    {
        $orgHints = [
            'zavod','institut','akademija','leksikografski','društvo','drustvo','udruga',
            'univerzitet','sveučilište','sveuciliste','university','press','publisher'
        ];
        $low = mb_strtolower($s, 'UTF-8');
        foreach ($orgHints as $h) {
            if (mb_strpos($low, $h) !== false) return true;
        }
        return false;
    }

    private static function cleanNamePunctuation(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s, " \t\n\r\0\x0B,;");
    }

    private static function unaccent(string $str): string
    {
        if (class_exists(\Transliterator::class)) {
            $t = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
            if ($t) { $str = $t->transliterate($str); }
        } else {
            $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        }
        return $str ?: '';
    }


    /**
     * @param Builder $query
     * @param string  $search
     *
     * @return Builder
     */
    public static function searchByTitle(Builder $query, string $search): Builder
    {
        $preg = explode(' ', $search, 3);

        if (isset ($preg[1]) && in_array($preg[1], $preg) && ! isset($preg[2])) {
            $query->where('title', 'like', '%' . $preg[0] . '%' . $preg[1] . '%')
                ->orWhere('title', 'like', '%' . $preg[1] . '% ' . $preg[0] . '%');

        } elseif (isset ($preg[2]) && in_array($preg[2], $preg)) {
            $query->where('title', 'like', $preg[0] . '%' . $preg[1] . '%' . $preg[2] . '%')
                ->orWhere('title', 'like', $preg[2] . '%' . $preg[1] . '% ' . $preg[0] . '%')
                ->orWhere('title', 'like', $preg[0] . '%' . $preg[2] . '% ' . $preg[1] . '%')
                ->orWhere('title', 'like', $preg[1] . '%' . $preg[0] . '% ' . $preg[2] . '%')
                ->orWhere('title', 'like', $preg[1] . '%' . $preg[2] . '% ' . $preg[0] . '%');

        } else {
            $query->where('title', 'like', '%' . $preg[0] . '%');
        }

        return $query;
    }


    /**
     * @param $cat
     * @param $subcat
     *
     * @return mixed
     */
    public static function getRelated($cat = null, $subcat = null)
    {
        $related = collect();

        if ($subcat) {
            $related = $subcat->products()->inRandomOrder()->take(10)->get();

        } else {
            if ($cat) {
                $related = $cat->products()->inRandomOrder()->take(10)->get();
            }
        }

        if ($related->count() < 9) {
            $related = $related->concat(
                Product::query()->inRandomOrder()->take(10 - $related->count())->get()
            );
        }

        return $related;
    }


    /**
     * @param string $description
     *
     * @return false|string
     */
    public static function setDescription(string $description, array $context = [])
    {
        if ($description == '') {
            return '';
        }

        $ids = static::extractWidgetIds($description);

        if ($ids === []) {
            return $description;
        }

        $wgs = WidgetGroup::query()
            ->where('status', 1)
            ->where(function (Builder $query) use ($ids) {
                $query->whereIn('id', $ids)->orWhereIn('slug', $ids);
            })
            ->with('widgets')
            ->get();

        foreach ($ids as $id) {
            $description = static::resolveWidgetDescription($wgs, $description, $id, $context);
        }


        return $description;
    }


    private static function extractWidgetIds(string $description): array
    {
        $iterator = substr_count($description, '++');
        $offset   = 0;
        $ids      = [];

        for ($i = 0; $i < $iterator / 2; $i++) {
            $from = strpos($description, '++', $offset);

            if ($from === false) {
                break;
            }

            $from += 2;
            $to = strpos($description, '++', $from);

            if ($to === false) {
                break;
            }

            $ids[] = substr($description, $from, $to - $from);
            $offset = $to + 2;
        }

        return $ids;
    }


    private static function resolveWidgetDescription(Collection $wgs, string $description, string $id, array $context = []): string
    {
        $wg = $wgs->firstWhere('id', $id) ?: $wgs->firstWhere('slug', $id);

        if (! $wg) {
            return $description;
        }

        $widgetsSignature = $wg->widgets
            ->sortBy('id')
            ->map(function ($widget) {
                return implode(':', [
                    $widget->id,
                    optional($widget->updated_at)->timestamp ?? 0,
                ]);
            })
            ->implode('|');

        $renderedWidget = static::rememberCache(
            'wg.' . $wg->id . '.' . md5(serialize($context) . '|' . (optional($wg->updated_at)->timestamp ?? 0) . '|' . $widgetsSignature),
            static::resolvedWidgetCacheTtl($wg),
            function () use ($wg, $context) {
                return static::renderDescriptionWidget($wg, $context);
            }
        );

        return str_replace('++' . $id . '++', $renderedWidget, $description);
    }


    private static function resolvedWidgetCacheTtl(WidgetGroup $wg)
    {
        if (in_array($wg->template, ['product_carousel', 'page_carousel'], true)) {
            return now()->addMinutes(10);
        }

        return config('cache.life');
    }


    /**
     * @param Collection $wgs
     * @param string     $description
     * @param string     $id
     *
     * @return string
     */
    private static function resolveDescription(Collection $wgs, string $description, string $id, array $context = []): string
    {
        $wg = $wgs->where('id', $id)->first();

        if ( ! $wg) {
            $wg = $wgs->where('slug', $id)->first();
        }

        if (! $wg) {
            return $description;
        }

        return str_replace('++' . $id . '++', static::renderDescriptionWidget($wg, $context), $description);
    }


    private static function renderDescriptionWidget(WidgetGroup $wg, array $context = []): string
    {
        $widgets = [];

        if ($wg->template == 'product_carousel' || $wg->template == 'page_carousel') {
            $widget = $wg->widgets->sortBy('sort_order')->first();

            if (! $widget) {
                return '';
            }

            $data   = unserialize($widget->data);

            if (static::isDescriptionTarget($data, 'product')) {
                $items     = static::products($data)->get();
                $tablename = 'product';
            }

            if (static::isDescriptionTarget($data, 'blog')) {
                $items     = static::blogs($data)->get();
                $tablename = 'blog';
            }

            if (static::isDescriptionTarget($data, 'category')) {
                if ($wg->template == 'product_carousel') {
                    $items     = static::categoryProducts($data)->get();
                    $tablename = 'product';
                } else {
                    $items     = static::category($data)->get();
                    $tablename = 'category';
                }
            }

            if (static::isDescriptionTarget($data, 'publisher')) {
                $items     = static::publisher($data)->get();
                $tablename = 'publisher';
            }

            if (static::isDescriptionTarget($data, 'reviews')) {
                $items     = static::reviews($data)->get();
                $tablename = 'reviews';
            }

            $widgets = [
                'title'      => $widget->title,
                'subtitle'   => $widget->subtitle,
                'url'        => $widget->url,
                'tablename'  => $tablename,
                'css'        => $data['css'],
                'container'  => (isset($data['container']) && $data['container'] == 'on') ? 1 : null,
                'background' => (isset($data['background']) && $data['background'] == 'on') ? 1 : null,
                'items'      => $items
            ];

        } else {
            foreach ($wg->widgets->sortBy('sort_order') as $widget) {
                $data = unserialize($widget->data);



                $widgets[] = [
                    'title'    => $widget->title,
                    'subtitle' => $widget->subtitle,
                    'color'    => $widget->badge,
                    'url'      => $widget->url,
                    'image'    => $widget->thumb,
                    'video'    => $widget->video_url,
                    'width'    => $widget->width,
                    'right'    => (isset($data['right']) && $data['right'] == 'on') ? 1 : null,
                    'short_description' => $context['short_description'] ?? null,
                ];
            }
        }

        return view(
            'front.layouts.widget.widget_' . $wg->template,
            array_merge(['data' => $widgets], $context)
        )->render();
    }


    /**
     * @param array  $data
     * @param string $target
     *
     * @return bool
     */
    public static function isDescriptionTarget(array $data, string $target): bool
    {
        if (isset($data['target']) && $data['target'] == $target) {
            return true;
        }
        if (isset($data['group']) && $data['group'] == $target) {
            return true;
        }

        return false;
    }


    /**
     * @param string $text
     *
     * @return string
     */
    public static function resolveFirstLetter(string $text): string
    {
        $letter = substr($text, 0, 1);

        if (in_array(substr($text, 0, 2), ['Nj', 'Lj', 'Š', 'Č', 'Ć', 'Ž', 'Đ'])) {
            $letter = substr($text, 0, 2);
        }

        if (in_array(substr($text, 0, 3), ['Dž', 'Đ'])) {
            $letter = substr($text, 0, 3);
        }

        return $letter;
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function products(array $data): Builder
    {
        $prods = (new Product())->newQuery();

        $prods->active()->available()->hasImage();

        if (isset($data['popular']) && $data['popular'] == 'on') {
            $prods->popular();
        }

        $prods->distinct()->last();

        if (isset($data['list']) && $data['list']) {
            $prods->whereIn('id', $data['list']);
        }

        return $prods->with(['author', 'action'])->withReviewSummary();
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function blogs(array $data): Builder
    {
        $blogs = (new Blog())->newQuery();

        $blogs->active();

        if (isset($data['new']) && $data['new'] == 'on') {
            $blogs->last();
        }

        if (isset($data['popular']) && $data['popular'] == 'on') {
            $blogs->popular();
        }

        if (isset($data['list']) && $data['list']) {
            $blogs->whereIn('id', $data['list']);
        }

        return $blogs;
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function category(array $data): Builder
    {
        $category = (new Category())->newQuery();

        $category->active();

        if (isset($data['new']) && $data['new'] == 'on') {
            $category->latest();
        }

        if (isset($data['popular']) && $data['popular'] == 'on') {
            $category->latest();
        }

        if (isset($data['list']) && $data['list']) {
            $category->whereIn('id', $data['list']);
        }

        return $category;
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function categoryProducts(array $data): Builder
    {
        $products = static::categoryProductsBaseQuery($data);

        if (static::shouldUseCategoryDiscountPriceMix($data)) {
            return $products
                ->withListingSpecial()
                ->orderByDesc('products.price')
                ->orderByDesc('products.updated_at')
                ->limit(12)
                ->with(['author', 'action'])
                ->withReviewSummary();
        }

        return $products
            ->orderByDesc('products.price')
            ->orderByDesc('products.updated_at')
            ->limit(12)
            ->with(['author', 'action'])
            ->withReviewSummary();
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function categoryProductsBaseQuery(array $data): Builder
    {
        $products = (new Product())->newQuery();

        $products->active()->available()->hasImage();

        if (isset($data['list']) && $data['list']) {
            $products->whereHas('categories', function (Builder $query) use ($data) {
                $query->whereIn('category_id', $data['list']);
            });
        } else {
            $products->whereRaw('1 = 0');
        }

        return $products->distinct();
    }


    /**
     * @param array $data
     *
     * @return bool
     */
    private static function shouldUseCategoryDiscountPriceMix(array $data): bool
    {
        return static::isDescriptionTarget($data, 'category')
            && (($data['category_discount_price_mix'] ?? null) === 'on');
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function publisher(array $data): Builder
    {
        $publisher = (new Publisher())->newQuery();

        $publisher->active();

        if (isset($data['new']) && $data['new'] == 'on') {
            $publisher->latest();
        }

        if (isset($data['popular']) && $data['popular'] == 'on') {
            $publisher->latest();
        }

        if (isset($data['list']) && $data['list']) {
            $publisher->whereIn('id', $data['list']);
        }

        return $publisher;
    }


    /**
     * @param array $data
     *
     * @return Builder
     */
    private static function reviews(array $data): Builder
    {
        $reviews = (new Review())->newQuery()
            ->with(['product:id,name,url'])
            ->where('status', 1);

        if (isset($data['popular']) && $data['popular'] == 'on') {
            $reviews->where('featured', 1);
        }

        if (isset($data['list']) && $data['list']) {
            $reviews->whereIn('id', $data['list']);
        }

        if (isset($data['new']) && $data['new'] == 'on') {
            return $reviews->orderByDesc('created_at');
        }

        return $reviews
            ->orderByDesc('featured')
            ->orderBy('sort_order')
            ->orderByDesc('created_at');
    }


    /**
     * @param string $tag
     *
     * @return \Illuminate\Cache\TaggedCache|mixed|object
     */
    public static function resolveCache(string $tag): ?object
    {
        return self::cacheRepository($tag);
    }


    /**
     * @param string $tag
     * @param string $key
     *
     * @return object|bool|mixed|null
     */
    public static function flushCache(string $tag, string $key)
    {
        return self::cacheRepository($tag)->forget($key);
    }


    public static function rememberCache(string $key, $ttl, \Closure $callback)
    {
        return self::rememberUsingStore(Cache::getFacadeRoot(), $key, $ttl, $callback);
    }


    public static function hasCache(string $key): bool
    {
        return self::hasUsingStore(Cache::getFacadeRoot(), $key);
    }


    public static function hasUsingStore(object $cache, string $key): bool
    {
        try {
            return (bool) $cache->has($key);
        } catch (\Throwable $e) {
            self::reportCacheFallback('read', $key, $e);

            return false;
        }
    }


    public static function rememberUsingStore(object $cache, string $key, $ttl, \Closure $callback)
    {
        $sentinel = new \stdClass();

        try {
            $cached = $cache->get($key, $sentinel);

            if ($cached !== $sentinel) {
                return $cached;
            }
        } catch (\Throwable $e) {
            self::reportCacheFallback('read', $key, $e);
        }

        $value = $callback();

        try {
            $cache->put($key, $value, $ttl);
        } catch (\Throwable $e) {
            self::reportCacheFallback('write', $key, $e);
        }

        return $value;
    }


    public static function addCache(string $key, $value, $ttl, bool $fallback = false): bool
    {
        return self::addUsingStore(Cache::getFacadeRoot(), $key, $value, $ttl, $fallback);
    }


    public static function addUsingStore(object $cache, string $key, $value, $ttl, bool $fallback = false): bool
    {
        try {
            return (bool) $cache->add($key, $value, $ttl);
        } catch (\Throwable $e) {
            self::reportCacheFallback('write', $key, $e);

            return $fallback;
        }
    }


    public static function putCache(string $key, $value, $ttl): bool
    {
        return self::putUsingStore(Cache::getFacadeRoot(), $key, $value, $ttl);
    }


    public static function putUsingStore(object $cache, string $key, $value, $ttl): bool
    {
        try {
            return (bool) $cache->put($key, $value, $ttl);
        } catch (\Throwable $e) {
            self::reportCacheFallback('write', $key, $e);

            return false;
        }
    }


    public static function forgetCache(string $key): bool
    {
        return self::forgetUsingStore(Cache::getFacadeRoot(), $key);
    }


    public static function forgetUsingStore(object $cache, string $key): bool
    {
        try {
            return (bool) $cache->forget($key);
        } catch (\Throwable $e) {
            self::reportCacheFallback('forget', $key, $e);

            return false;
        }
    }


    private static function supportsCacheTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }


    private static function cacheRepository(string $tag): object
    {
        $supportsTags = self::supportsCacheTags();
        $cache = $supportsTags ? Cache::tags([$tag]) : Cache::getFacadeRoot();

        return new class($cache, $tag, ! $supportsTags) {
            public function __construct(
                private object $cache,
                private string $tag,
                private bool $prefixKeys
            ) {
            }

            public function remember(string $key, $ttl, \Closure $callback)
            {
                return Helper::rememberUsingStore($this->cache, $this->prefix($key), $ttl, $callback);
            }

            public function forget(string $key)
            {
                return Helper::forgetUsingStore($this->cache, $this->prefix($key));
            }

            public function __call(string $method, array $arguments)
            {
                if ($this->prefixKeys && isset($arguments[0]) && is_string($arguments[0])) {
                    $arguments[0] = $this->prefix($arguments[0]);
                }

                return $this->cache->{$method}(...$arguments);
            }

            private function prefix(string $key): string
            {
                return $this->prefixKeys ? $this->tag . ':' . $key : $key;
            }
        };
    }


    private static function reportCacheFallback(string $operation, string $key, \Throwable $e): void
    {
        $signature = implode('|', [$operation, get_class($e), $e->getMessage()]);

        if (isset(self::$reportedCacheFallbacks[$signature])) {
            return;
        }

        self::$reportedCacheFallbacks[$signature] = true;

        Log::warning('Cache fallback engaged after cache store failure.', [
            'operation' => $operation,
            'key' => $key,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);
    }


    /**
     * @param bool $slug
     *
     * @return string
     */
    public static function categoryGroupPath(bool $slug = false): string
    {
        if ($slug) {
            return Str::slug(config('settings.group_path'));
        }

        return config('settings.group_path');
    }

    /**
     * @param array  $data
     * @param string $tag
     * @param        $target
     *
     * @return string
     */
    public static function resolveSlug(array $data, string $tag = 'title', $target = null): string
    {
        $slug = null;

        if ($target) {
            $product = Product::where('id', $target)->first();

            if ($product) {
                $slug = $product->slug;
            }
        }

        $slug  = $slug ?: Str::slug($data[$tag]);
        $exist = Product::where('slug', $slug)->count();

        $cat_exist = Category::where('slug', $slug)->count();

        if (($cat_exist || $exist > 1) && $target) {
            return $slug . '-' . time();
        }

        if (($cat_exist || $exist) && ! $target) {
            return $slug . '-' . time();
        }

        return $slug;
    }


    /**
     * @param $cart
     *
     * @return CartCondition|false
     * @throws \Darryldecode\Cart\Exceptions\InvalidConditionException
     */
    public static function hasSpecialCartCondition($cart = null)
    {
        $condition     = false;
        $has_condition = false;
        $discountableTotal = self::discountableCartTotal($cart);

        if ($discountableTotal > 50) {
            $has_condition = 10;
        }
        if ($discountableTotal > 100) {
            $has_condition = 15;
        }
        if ($discountableTotal > 200) {
            $has_condition = 20;
        }

        if ($has_condition && self::isDateBetween()) {
            $value    = self::calculateDiscountPrice($discountableTotal, $has_condition, 'P');
            $discount = $discountableTotal - $value;

            $condition = new CartCondition(array(
                'name'       => config('settings.special_action.title'),
                'type'       => 'special',
                'target'     => 'total', // this condition will be applied to cart's subtotal when getSubTotal() is called.
                'value'      => '-' . $discount,
                'attributes' => [
                    'description' => '',
                    'geo_zone'    => ''
                ]
            ));
        }

        return $condition;
    }


    /**
     * @param        $cart
     * @param string $coupon
     *
     * @return CartCondition|false
     * @throws \Darryldecode\Cart\Exceptions\InvalidConditionException
     */
    public static function hasCouponCartConditions($cart, string $coupon = '')
    {
        $condition = false;
        $discountableTotal = self::discountableCartTotal($cart);

        if (! Schema::hasTable('product_actions')) {
            return false;
        }

        if ($discountableTotal <= 0) {
            return false;
        }

        $actions   = Action::query()->where('group', 'total')->get();

        if ($actions->count()) {
            foreach ($actions as $action) {
                if ($action->isValid($coupon)) {
                    $value    = self::calculateDiscountPrice($discountableTotal, $action->discount, $action->type);
                    $discount = $discountableTotal - $value;

                    $condition = new CartCondition(array(
                        'name'       => $action->title,
                        'type'       => 'special',
                        'target'     => 'total', // this condition will be applied to cart's subtotal when getSubTotal() is called.
                        'value'      => '-' . $discount,
                        'attributes' => $action->setConditionAttributes($coupon)
                    ));
                }
            }
        }

        return $condition;
    }


    /**
     * @param        $cart
     * @param string $coupon
     *
     * @return CartCondition|false
     * @throws \Darryldecode\Cart\Exceptions\InvalidConditionException
     */
    public static function hasLoyaltyCartConditions($cart, int $loyalty = 0)
    {
        $condition = false;
        $has_loyalty   = Loyalty::hasLoyalty();
        $discountableTotal = self::discountableCartTotal($cart);

        if ($has_loyalty) {
            $discount = Loyalty::calculateLoyalty($loyalty);

            if ($discount > 0 && $discountableTotal > $discount) {
                $condition = new CartCondition(array(
                    'name'       => 'Loyalty',
                    'type'       => 'special',
                    'target'     => 'total', // this condition will be applied to cart's subtotal when getSubTotal() is called.
                    'value'      => '-' . $discount,
                    'attributes' => [
                        'type'        => 'loyalty',
                        'description' => 'Loyalty Program'
                    ]
                ));
            }
        }

        return $condition;
    }

    private static function discountableCartTotal($cart): float
    {
        if (! $cart) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($cart->getContent() as $item) {
            if (GiftWrapService::isGiftWrapItem($item)) {
                continue;
            }

            $total += (float) $item->getPriceSumWithConditions(false);
        }

        return max(0.0, round($total, 2));
    }



    /**
     * @param $cart
     *
     * @return false|mixed
     */
    public static function isCouponUsed($cart)
    {
        $coupon = false;
        $items = $cart->getContent();

        foreach ($items as $item) {
            if ($item->getConditions()->getType() == 'coupon') {
                $coupon = $item->getConditions()->getTarget();
            }
        }

        foreach ($cart->getConditions() as $condition) {
            if (isset($condition->getAttributes()['type']) && $condition->getAttributes()['type'] == 'coupon' && floatval($condition->getValue()) < 0) {
                $coupon = $condition->getAttributes()['description'];
            }
        }



        return $coupon;
    }


    /**
     * @param $date
     *
     * @return bool
     */
    public static function isDateBetween($date = null): bool
    {
        if (config('settings.special_action.start')) {
            $now   = $date ?: Carbon::now();
            $start = Carbon::createFromFormat('d/m/Y H:i:s', config('settings.special_action.start'));
            $end   = Carbon::createFromFormat('d/m/Y H:i:s', config('settings.special_action.end'));

            if ($now->isBetween($start, $end)) {
                return true;
            }
        }

        return false;
    }

}
