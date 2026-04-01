<?php

namespace App\Helpers;

use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use App\Models\Seo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Breadcrumb
{

    /**
     * @var array
     */
    private $schema = [];

    /**
     * @var array
     */
    private $breadcrumbs = [];


    /**
     * Breadcrumb constructor.
     */
    public function __construct()
    {
        $this->setDefault();
    }


    /**
     * @param               $group
     * @param Category|null $cat
     * @param null          $subcat
     *
     * @return $this
     */
    public function category($group, ?Category $cat = null, $subcat = null)
    {
        if (isset($group) && $group) {
            $this->addGroup($group);

            if ($cat) {
                $this->pushBreadcrumb($cat->title, route('catalog.route', ['group' => $group, 'cat' => $cat]));
            }

            if ($subcat) {
                $this->pushBreadcrumb($subcat->title, route('catalog.route', ['group' => $group, 'cat' => $cat, 'subcat' => $subcat]));
            }
        }

        return $this;
    }


    /**
     * @param               $group
     * @param Category|null $cat
     * @param null          $subcat
     * @param Product|null  $prod
     *
     * @return $this
     */
    public function product($group, ?Category $cat = null, $subcat = null, ?Product $prod = null)
    {
        $this->category($group, $cat, $subcat);

        if ($prod) {
            $this->pushBreadcrumb($prod->name, url($prod->url));
        }

        return $this;
    }


    public function author(Author $author, ?Category $cat = null, $subcat = null)
    {
        $this->pushBreadcrumb('Autori', route('catalog.route.author'));
        $this->pushBreadcrumb($author->title, route('catalog.route.author', ['author' => $author]));

        if ($cat) {
            $this->pushBreadcrumb($cat->title, route('catalog.route.author', ['author' => $author, 'cat' => $cat]));
        }

        if ($subcat) {
            $this->pushBreadcrumb($subcat->title, route('catalog.route.author', ['author' => $author, 'cat' => $cat, 'subcat' => $subcat]));
        }

        return $this;
    }


    public function publisher(Publisher $publisher, ?Category $cat = null, $subcat = null)
    {
        $this->pushBreadcrumb('Nakladnici', route('catalog.route.publisher'));
        $this->pushBreadcrumb($publisher->title, route('catalog.route.publisher', ['publisher' => $publisher]));

        if ($cat) {
            $this->pushBreadcrumb($cat->title, route('catalog.route.publisher', ['publisher' => $publisher, 'cat' => $cat]));
        }

        if ($subcat) {
            $this->pushBreadcrumb($subcat->title, route('catalog.route.publisher', ['publisher' => $publisher, 'cat' => $cat, 'subcat' => $subcat]));
        }

        return $this;
    }


    /**
     * @param Product|null $prod
     *
     * @return array
     */
    public function productBookSchema(?Product $prod = null, ?Category $cat = null, ?Category $subcat = null)
    {
        if ($prod) {
            $schema = [
                '@context' => 'https://schema.org/',
                '@type' => 'Book',
                '@id' => url($prod->url) . '#book',
                'description' => Seo::descriptionFromContent(
                    [$prod->description],
                    'Knjiga ' . $prod->name . ' autora ' . (($prod->author) ? $prod->author->title : 'Nepoznati autor') . ' u ponudi ' . Seo::brand() . '.'
                ),
                'image' => [$prod->image],
                'name' => $prod->name,
                'url' => url($prod->url),
                'mainEntityOfPage' => url($prod->url),
                'sku' => $prod->sku,
                'offers' => [
                    '@type' => 'Offer',
                    'url' => url($prod->url),
                    'priceCurrency' => 'EUR',
                    'priceValidUntil' => now()->endOfYear()->format('Y-m-d'),
                    'price' => ($prod->special()) ? $prod->special() : number_format($prod->price, 2, '.', ''),
                    'sku' => $prod->sku,
                    'availability' => ($prod->quantity) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'seller' => [
                        '@type' => 'Organization',
                        'name' => Seo::brand(),
                    ],
                ],
            ];

            if ($prod->publisher) {
                $schema['publisher'] = [
                    '@type' => 'Organization',
                    'name' => $prod->publisher->title,
                ];
            }

            if ($prod->author) {
                $schema['author'] = [
                    '@type' => 'Person',
                    'name' => $prod->author->title,
                ];
            }

            if ($prod->year) {
                $schema['datePublished'] = (string) $prod->year;
            }

            if ($prod->pages) {
                $schema['numberOfPages'] = (int) $prod->pages;
            }

            if ($prod->isbn) {
                $schema['isbn'] = $prod->isbn;
            }

            if ($subcat) {
                $schema['genre'] = $subcat->title;
            } elseif ($cat) {
                $schema['genre'] = $cat->title;
            }

            if ($prod->binding) {
                $schema['bookFormat'] = $prod->binding;
            }

            $additionalProperties = [];

            if ($prod->condition) {
                $additionalProperties[] = [
                    '@type' => 'PropertyValue',
                    'name' => 'Stanje',
                    'value' => $prod->condition,
                ];

                $condition = Str::lower($prod->condition);

                if (Str::contains($condition, ['novo', 'nova'])) {
                    $schema['offers']['itemCondition'] = 'https://schema.org/NewCondition';
                } elseif (Str::contains($condition, ['rablj', 'koristen', 'korišten', 'polovno'])) {
                    $schema['offers']['itemCondition'] = 'https://schema.org/UsedCondition';
                }
            }

            if ($prod->origin) {
                $additionalProperties[] = [
                    '@type' => 'PropertyValue',
                    'name' => 'Mjesto izdavanja',
                    'value' => $prod->origin,
                ];
            }

            if ($prod->letter) {
                $additionalProperties[] = [
                    '@type' => 'PropertyValue',
                    'name' => 'Pismo',
                    'value' => $prod->letter,
                ];
            }

            if ($prod->dimensions) {
                $additionalProperties[] = [
                    '@type' => 'PropertyValue',
                    'name' => 'Dimenzije',
                    'value' => $prod->dimensions . ' cm',
                ];
            }

            if (count($additionalProperties)) {
                $schema['additionalProperty'] = $additionalProperties;
            }

            return $schema;
        }
    }


    /**
     * @return array
     */
    public function resolve()
    {
        $this->schema['itemListElement'] = $this->breadcrumbs;

        return $this->schema;
    }


    /**
     *
     */
    private function setDefault()
    {
        $this->schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'BreadcrumbList'
        ];

        array_push($this->breadcrumbs, [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Naslovnica',
            'item' => route('index')
        ]);
    }


    /**
     * @param $group
     */
    public function addGroup($group)
    {
        $this->pushBreadcrumb(Str::ucfirst($group), route('catalog.route', ['group' => $group]));
    }


    private function pushBreadcrumb(string $name, string $item): void
    {
        array_push($this->breadcrumbs, [
            '@type' => 'ListItem',
            'position' => count($this->breadcrumbs) + 1,
            'name' => $name,
            'item' => $item,
        ]);
    }
}
