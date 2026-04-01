<?php

namespace App\Models;

use App\Helpers\Metatags;
use App\Models\Front\Blog;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use App\Models\Front\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Class Sitemap
 * @package App\Models
 */
class Seo
{
    public static function brand(): string
    {
        return 'ZUZI Shop';
    }


    public static function defaultTitle(): string
    {
        return 'ZUZI Shop | Prodaja knjiga | Otkup knjiga | Webshop';
    }


    public static function defaultDescription(): string
    {
        return 'Zuzi shop - Nudimo Vam prakticnu mogucnost pretrazivanja i narucivanja zeljenih naslova putem web stranice zuzi.hr iz udobnosti naslonjaca.';
    }


    public static function defaultImage(): string
    {
        return asset('media/img/cover-zuzi.jpg');
    }


    public static function title(?string $title = null): string
    {
        $title = static::cleanText($title, 70);

        return $title ?: static::defaultTitle();
    }


    public static function appendBrand(?string $title = null): string
    {
        $title = static::cleanText($title, 70);

        if (! $title) {
            return static::defaultTitle();
        }

        if (Str::contains(Str::lower($title), Str::lower(static::brand()))) {
            return $title;
        }

        return $title . ' | ' . static::brand();
    }


    public static function description(?string $description = null, ?string $fallback = null): string
    {
        $description = static::cleanText($description, 160);

        if ($description) {
            return $description;
        }

        $fallback = static::cleanText($fallback, 160);

        return $fallback ?: static::defaultDescription();
    }


    public static function descriptionFromContent(array $candidates, ?string $fallback = null): string
    {
        foreach ($candidates as $candidate) {
            $description = static::cleanText($candidate, 160);

            if ($description) {
                return $description;
            }
        }

        return static::description(null, $fallback);
    }


    public static function image(?string $image = null): string
    {
        if (! $image) {
            return static::defaultImage();
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        return asset(ltrim($image, '/'));
    }


    public static function ogType(Request $request): string
    {
        if ($request->routeIs('catalog.route') && static::hasRouteParameter($request, 'prod')) {
            return 'product';
        }

        if ($request->routeIs('catalog.route.blog') && static::hasRouteParameter($request, 'blog')) {
            return 'article';
        }

        return 'website';
    }


    public static function robots(Request $request): string
    {
        if ($request->routeIs(
            'kosarica',
            'naplata',
            'pregled',
            'checkout',
            'checkout.*',
            'moj-racun',
            'moj-racun.*',
            'moje-narudzbe',
            'loyalty'
        )) {
            return 'noindex,follow';
        }

        if ($request->routeIs('pretrazi')) {
            return 'noindex,follow';
        }

        if ($request->routeIs('catalog.route.author')
            && ! static::hasRouteParameter($request, 'author')
            && $request->filled('letter')) {
            return 'noindex,follow';
        }

        if ($request->routeIs('catalog.route.publisher')
            && ! static::hasRouteParameter($request, 'publisher')
            && $request->filled('letter')) {
            return 'noindex,follow';
        }

        if ($request->routeIs('catalog.route')
            && static::hasAnyQuery($request, ['autor', 'nakladnik', 'start', 'end', 'sort'])) {
            return 'noindex,follow';
        }

        return 'index,follow';
    }


    public static function canonical(Request $request): string
    {
        if ($request->routeIs('pretrazi')) {
            return route('pretrazi');
        }

        if ($request->routeIs('catalog.route.author') && ! static::hasRouteParameter($request, 'author')) {
            if ($request->filled('letter')) {
                return route('catalog.route.author');
            }

            if (! static::hasDisallowedQuery($request, ['page'])) {
                return static::canonicalUrl($request, ['page']);
            }

            return route('catalog.route.author');
        }

        if ($request->routeIs('catalog.route.publisher') && ! static::hasRouteParameter($request, 'publisher')) {
            if ($request->filled('letter')) {
                return route('catalog.route.publisher');
            }

            if (! static::hasDisallowedQuery($request, ['page'])) {
                return static::canonicalUrl($request, ['page']);
            }

            return route('catalog.route.publisher');
        }

        if ($request->routeIs('catalog.route')) {
            if (static::hasAnyQuery($request, ['autor', 'nakladnik', 'start', 'end', 'sort'])) {
                return static::canonicalUrl($request);
            }

            if (! static::hasDisallowedQuery($request, ['page'])) {
                return static::canonicalUrl($request, ['page']);
            }

            return static::canonicalUrl($request);
        }

        return static::canonicalUrl($request);
    }


    /**
     * @return array
     */
    public static function getProductData(Product $product): array
    {
        $fallback = static::genericProductDescription($product);

        return [
            'title'       => static::appendBrand($product->meta_title ?: $product->name),
            'description' => static::descriptionFromContent([$product->description], $fallback),
        ];
    }


    /**
     * @return array
     */
    public static function getAuthorData(Author $author, ?Category $cat = null, ?Category $subcat = null): array
    {
        $title = static::appendBrand($author->title . ' knjige');
        $description = static::descriptionFromContent(
            [
                $subcat ? $subcat->description : null,
                $cat ? $cat->description : null,
                $author->description ?? null,
            ],
            'Knjige autora ' . $author->title . ' uz brzu dostavu i sigurnu kupovinu u ' . static::brand() . '.'
        );

        // Check if there is meta title or description and set vars.
        if ($cat) {
            if ($cat->meta_title) { $title = static::appendBrand($cat->meta_title); }
        }

        if ($subcat) {
            if ($subcat->meta_title) { $title = static::appendBrand($subcat->meta_title); }
        }

        return [
            'title'       => $title,
            'description' => $description
        ];
    }


    /**
     * @return array
     */
    public static function getPublisherData(Publisher $publisher, ?Category $cat = null, ?Category $subcat = null): array
    {
        $title = static::appendBrand($publisher->title . ' knjige');
        $description = static::descriptionFromContent(
            [
                $subcat ? $subcat->description : null,
                $cat ? $cat->description : null,
                $publisher->description ?? null,
            ],
            'Ponuda knjiga nakladnika ' . $publisher->title . ' uz brzu dostavu i sigurnu kupovinu u ' . static::brand() . '.'
        );

        // Check if there is meta title or description and set vars.
        if ($cat) {
            if ($cat->meta_title) { $title = static::appendBrand($cat->meta_title); }
        }

        if ($subcat) {
            if ($subcat->meta_title) { $title = static::appendBrand($subcat->meta_title); }
        }

        return [
            'title'       => $title,
            'description' => $description
        ];
    }


    public static function getMetaTags(Request $request, $target = 'product')
    {
        return static::robots($request) === 'index,follow'
            ? []
            : [Metatags::noFollow(static::robots($request))];
    }


    public static function getPageData(Page $page): array
    {
        return [
            'title'       => static::appendBrand($page->meta_title ?: $page->title),
            'description' => static::descriptionFromContent([$page->short_description ?? null, $page->description ?? null], $page->title),
        ];
    }


    public static function getBlogData(Blog $blog): array
    {
        return [
            'title'       => static::appendBrand($blog->meta_title ?: $blog->title),
            'description' => static::descriptionFromContent([$blog->short_description ?? null, $blog->description ?? null], $blog->title),
        ];
    }


    public static function getCategoryData(?string $group = null, ?Category $cat = null, ?Category $subcat = null): array
    {
        if ($subcat) {
            return [
                'title'       => static::appendBrand($subcat->meta_title ?: $subcat->title),
                'description' => static::descriptionFromContent([$subcat->description], $subcat->title),
            ];
        }

        if ($cat) {
            return [
                'title'       => static::appendBrand($cat->meta_title ?: $cat->title),
                'description' => static::descriptionFromContent([$cat->description], $cat->title),
            ];
        }

        if ($group === 'snizenja') {
            return [
                'title'       => static::appendBrand('Akcijska ponuda'),
                'description' => static::description(null, 'Aktualna akcijska ponuda i snizeni naslovi u ' . static::brand() . '.'),
            ];
        }

        $groupLabel = $group ? Str::headline(str_replace('-', ' ', $group)) : 'Zuzi Web Shop';

        return [
            'title'       => static::appendBrand($groupLabel),
            'description' => static::description(null, 'Pregledajte aktualnu ponudu knjiga i ostalih naslova u ' . static::brand() . '.'),
        ];
    }


    public static function getSearchData(?string $query = null): array
    {
        $query = static::cleanText($query, 70);

        if (! $query) {
            return [
                'title'       => static::appendBrand('Pretraga'),
                'description' => static::description(null, 'Pretrazite naslove, autore i izdavace u ' . static::brand() . '.'),
            ];
        }

        return [
            'title'       => static::appendBrand('Pretraga: ' . $query),
            'description' => static::description(null, 'Rezultati pretrage za pojam "' . $query . '" na ' . static::brand() . '.'),
        ];
    }


    private static function genericProductDescription(Product $product): string
    {
        $parts = [
            'Kupite knjigu ' . $product->name,
            $product->author ? 'autora ' . $product->author->title : null,
            $product->publisher ? 'u nakladi ' . $product->publisher->title : null,
            $product->year ? 'izdanje ' . $product->year . '.' : null,
        ];

        return static::description(null, implode(' ', array_filter($parts)) . ' Brza dostava i sigurna kupovina u ' . static::brand() . '.');
    }


    private static function cleanText(?string $value, int $limit = 160): string
    {
        if (! $value) {
            return '';
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim((string) $value);

        if (! $value) {
            return '';
        }

        return Str::limit($value, $limit);
    }


    private static function canonicalUrl(Request $request, array $allowedKeys = []): string
    {
        $query = Arr::only(static::activeQuery($request), $allowedKeys);

        if (! count($query)) {
            return $request->url();
        }

        return $request->url() . '?' . Arr::query($query);
    }


    private static function activeQuery(Request $request): array
    {
        return collect($request->query())
            ->filter(function ($value) {
                if (is_array($value)) {
                    return count(array_filter($value, fn ($item) => filled($item))) > 0;
                }

                return filled($value);
            })
            ->all();
    }


    private static function hasAnyQuery(Request $request, array $keys): bool
    {
        return count(array_intersect(array_keys(static::activeQuery($request)), $keys)) > 0;
    }


    private static function hasDisallowedQuery(Request $request, array $allowedKeys = []): bool
    {
        return count(array_diff(array_keys(static::activeQuery($request)), $allowedKeys)) > 0;
    }


    private static function hasRouteParameter(Request $request, string $key): bool
    {
        $value = $request->route($key);

        if (is_object($value) && method_exists($value, 'getKey')) {
            return filled($value->getKey());
        }

        return filled($value);
    }
}
