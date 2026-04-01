<?php

namespace App\Helpers;

use App\Models\Front\Blog;
use App\Models\Front\Catalog\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use App\Models\Seo;

class Metatags
{
    public static function noFollow(string $content = 'noindex,follow'): array
    {
        return [
            'name'    => 'robots',
            'content' => $content,
        ];
    }


    /**
     * @return array
     */
    public static function indexSchema(): array
    {
        return static::organizationSchema();
    }


    public static function organizationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'BookStore',
            '@id'      => config('app.url') . '#organization',
            'name'     => Seo::brand(),
            'image'    => Seo::defaultImage(),
            'logo'     => asset('media/img/zuzi-logo.webp'),
            'url'      => config('app.url'),
            'email'    => 'info@zuzi.hr',
            'telephone'=> '+38514831005',
            'address'  => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => 'Antuna Soljana 33',
                'addressLocality' => 'Zagreb',
                'postalCode'      => '10000',
                'addressCountry'  => 'HR',
            ],
            'geo'      => [
                '@type'     => 'GeoCoordinates',
                'latitude'  => 45.8020394107,
                'longitude' => 15.8874534126,
            ],
            'openingHoursSpecification' => [
                [
                    '@type'    => 'OpeningHoursSpecification',
                    'dayOfWeek'=> ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                    'opens'    => '09:00',
                    'closes'   => '20:00',
                ],
                [
                    '@type'    => 'OpeningHoursSpecification',
                    'dayOfWeek'=> 'Saturday',
                    'opens'    => '09:00',
                    'closes'   => '14:00',
                ],
            ],
            'priceRange' => '€€',
            'sameAs'     => [
                'https://www.facebook.com/zuziobrt/',
                'https://www.instagram.com/zuziobrt/',
            ],
        ];
    }


    public static function websiteSchema(): array
    {
        $searchKey = config('settings.search_keyword', 'pojam');

        return [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            '@id'      => config('app.url') . '#website',
            'name'     => Seo::brand(),
            'url'      => config('app.url'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => route('pretrazi') . '?' . $searchKey . '={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }


    public static function pageSchema(
        string $type,
        string $name,
        string $description,
        string $url,
        ?string $image = null
    ): array {
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => $type,
            'name'     => $name,
            'description' => $description,
            'url'      => $url,
            'isPartOf' => [
                '@type' => 'WebSite',
                '@id'   => config('app.url') . '#website',
            ],
        ];

        if ($image) {
            $schema['image'] = [$image];
        }

        return $schema;
    }


    public static function itemListSchema(iterable $items, string $url, ?string $name = null): array
    {
        $elements = [];

        foreach ($items as $index => $item) {
            $itemName = trim(strip_tags((string) data_get($item, 'name', '')));
            $itemUrl = trim((string) data_get($item, 'url', ''));

            if (! $itemName || ! $itemUrl) {
                continue;
            }

            $elements[] = [
                '@type'    => 'ListItem',
                'position' => count($elements) + 1,
                'name'     => $itemName,
                'url'      => $itemUrl,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type'    => 'ItemList',
            'name'     => $name ?: Seo::brand(),
            'url'      => $url,
            'numberOfItems' => count($elements),
            'itemListElement' => $elements,
        ];
    }


    public static function contactPageSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'ContactPage',
            'name'     => 'Kontakt | ' . Seo::brand(),
            'description' => 'Kontakt podaci i obrazac za upit za kupnju knjiga u ' . Seo::brand() . '.',
            'url'      => route('kontakt'),
            'mainEntity' => [
                '@type' => 'Organization',
                '@id'   => config('app.url') . '#organization',
                'name'  => Seo::brand(),
                'url'   => config('app.url'),
                'email' => 'info@zuzi.hr',
                'telephone' => '+38514831005',
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'contactType' => 'customer support',
                    'telephone' => '+38514831005',
                    'email' => 'info@zuzi.hr',
                    'availableLanguage' => ['hr'],
                    'areaServed' => 'HR',
                ],
            ],
        ];
    }


    /**
     * @param Product|null    $prod
     * @param Collection|null $reviews
     *
     * @return array
     */
    public static function productSchema(?Product $prod = null, ?Collection $reviews = null): array
    {
        $response = [];

        if ($prod) {
            $price = ($prod->special()) ? $prod->special() : number_format($prod->price, 2, '.', '');
            $url = url($prod->url);
            $description = Seo::descriptionFromContent(
                [$prod->description],
                'Kupite knjigu ' . $prod->name . ' uz brzu dostavu i sigurnu kupovinu u ' . Seo::brand() . '.'
            );

            $response = [
                '@context'    => 'https://schema.org',
                '@type'       => 'Product',
                'name'        => $prod->name,
                'description' => $description,
                'sku'         => $prod->sku,
                'image'       => [$prod->image],
                'url'         => $url,
                'brand'       => [
                    '@type' => 'Brand',
                    'name'  => $prod->publisher ? $prod->publisher->title : Seo::brand(),
                ],
                'offers'      => [
                    '@type'           => 'Offer',
                    'priceCurrency'   => 'EUR',
                    'price'           => (string) $price,
                    'priceValidUntil' => now()->endOfYear()->format('Y-m-d'),
                    'sku'             => $prod->sku,
                    'url'             => $url,
                    'availability'    => $prod->quantity ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'seller'          => [
                        '@type' => 'Organization',
                        'name'  => Seo::brand(),
                    ],
                ],
            ];

            if ($prod->isbn) {
                $response['isbn'] = $prod->isbn;
            }

            if ($prod->author) {
                $response['author'] = [
                    '@type' => 'Person',
                    'name'  => $prod->author->title,
                ];
            }

            if ($reviews && $reviews->count()) {
                $response['aggregateRating'] = [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => floor($reviews->avg('stars')),
                    'reviewCount' => $reviews->count(),
                ];

                foreach ($reviews as $review) {
                    $res_review = [
                        '@type'         => 'Review',
                        'author'        => [
                            '@type' => 'Person',
                            'name'  => $review->fname,
                        ],
                        'datePublished' => Carbon::make($review->created_at)->locale('hr')->format('Y-m-d'),
                        'reviewBody'    => strip_tags($review->message),
                        'name'          => $prod->name,
                        'reviewRating'  => [
                            '@type'       => 'Rating',
                            'bestRating'  => '5',
                            'ratingValue' => floor($review->stars),
                            'worstRating' => '1'
                        ]
                    ];
                }

                $response['review'] = $res_review;
            }
        }

        return $response;
    }


    public static function articleSchema(Blog $blog): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'BlogPosting',
            'headline' => $blog->title,
            'description' => Seo::descriptionFromContent(
                [$blog->short_description ?? null, $blog->description ?? null],
                $blog->title
            ),
            'image' => [$blog->image],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => route('catalog.route.blog', ['blog' => $blog]),
            ],
            'datePublished' => Carbon::make($blog->publish_date ?: $blog->created_at)->toAtomString(),
            'dateModified'  => Carbon::make($blog->updated_at ?: $blog->created_at)->toAtomString(),
            'author' => [
                '@type' => 'Organization',
                'name'  => Seo::brand(),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => Seo::brand(),
                'logo'  => [
                    '@type' => 'ImageObject',
                    'url'   => asset('media/img/zuzi-logo.webp'),
                ],
            ],
        ];
    }


    public static function faqSchema(iterable $faqItems): array
    {
        $entities = [];

        foreach ($faqItems as $item) {
            $question = trim(strip_tags((string) ($item->title ?? '')));
            $answer = trim(strip_tags((string) ($item->description ?? '')));

            if (! $question || ! $answer) {
                continue;
            }

            $entities[] = [
                '@type' => 'Question',
                'name'  => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $answer,
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type'    => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}
