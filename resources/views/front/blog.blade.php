@extends('front.layouts.app')

@php
    $isBlogListing = isset($blogs);
    $blogSeo = $isBlogListing ? null : \App\Models\Seo::getBlogData($blog);
    $relatedProducts = $relatedProducts ?? collect();
    $ctaBlocks = $ctaBlocks ?? collect();
    $singleRelatedProduct = $relatedProducts->count() === 1 ? $relatedProducts->first() : null;
    $relatedProductsHeading = $singleRelatedProduct ? 'Naruči knjigu iz recenzije' : 'Preporučeni naslovi';
    $ctaButtonClasses = [
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'outline' => 'btn-outline-primary',
    ];
    $productShelfCarouselOptions = [
        'items' => 2,
        'gutter' => 16,
        'controls' => true,
        'nav' => true,
        'autoHeight' => false,
        'mouseDrag' => true,
        'touch' => true,
        'swipeAngle' => 30,
        'preventActionWhenRunning' => true,
        'preventScrollOnTouch' => 'auto',
        'responsive' => [
            0 => ['items' => 2, 'controls' => true, 'nav' => true],
            480 => ['items' => 2, 'controls' => true, 'nav' => true],
            720 => ['items' => 3],
            991 => ['items' => 2],
            1140 => ['items' => 3],
            1300 => ['items' => 4],
            1500 => ['items' => 5],
            1600 => ['items' => 6],
        ],
    ];
@endphp

@if($isBlogListing)
    @section('title', \App\Models\Seo::appendBrand('Blog'))
    @section('description', \App\Models\Seo::description(null, 'Medijske objave, clanci i obavijesti iz ' . \App\Models\Seo::brand() . '.'))
@else
    @section('title', $blogSeo['title'])
    @section('description', $blogSeo['description'])
    @section('seo_image', $blog->image)
    @section('seo_image_alt', $blog->title)
    @section('og_type', 'article')
    @section('seo_published_time', optional($blog->publish_date ?: $blog->created_at)->toAtomString())
    @section('seo_updated_time', optional($blog->updated_at ?: $blog->created_at)->toAtomString())
@endif

@if(! $isBlogListing)
    @push('css_after')
        <style>
            .product-page-carousel .tns-ovh,
            .product-page-carousel .tns-item,
            .product-page-carousel .tns-carousel-inner {
                touch-action: pan-y pinch-zoom;
            }

            .blog-cta-block {
                background: #fff;
                border: 0;
                border-radius: .5rem;
                box-shadow: 0 0.25rem 0.5625rem -0.0625rem rgba(0, 0, 0, 0.03),
                    0 0.275rem 1.25rem -0.0625rem rgba(0, 0, 0, 0.05);
            }

            .blog-post-shell {
                max-width: 1120px;
                margin: 0 auto 3rem;
            }

            .blog-post-card {
                overflow: hidden;
                border: 1px solid rgba(43, 52, 69, .08);
                border-radius: .5rem;
                background: #fff;
                box-shadow: 0 .75rem 2.25rem rgba(31, 45, 61, .08);
            }

            .blog-post-header {
                padding: 2rem 2rem 1.35rem;
                border-bottom: 1px solid #edf1f6;
            }

            .blog-post-meta {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: .55rem .85rem;
                color: #7d879c;
                font-size: .92rem;
                font-weight: 600;
            }

            .blog-post-meta i {
                color: #e50077;
            }

            .blog-post-date-icon {
                position: relative;
                display: inline-block;
                width: .9rem;
                height: .9rem;
                margin-right: .35rem;
                border: 1.5px solid #e50077;
                border-radius: .18rem;
                vertical-align: -.12rem;
            }

            .blog-post-date-icon::before {
                position: absolute;
                top: .18rem;
                left: 0;
                width: 100%;
                height: 1.5px;
                background: #e50077;
                content: "";
            }

            .blog-post-date-icon::after {
                position: absolute;
                top: -.18rem;
                left: .18rem;
                width: .1rem;
                height: .35rem;
                border-radius: 999px;
                background: #e50077;
                box-shadow: .38rem 0 0 #e50077;
                content: "";
            }

            .blog-post-hero {
                background: #f6f9fc;
            }

            .blog-post-hero img {
                display: block;
                width: 100%;
                height: auto;
            }

            .blog-post-body {
                max-width: 820px;
                margin: 0 auto;
                padding: 2.15rem 2rem 2.35rem;
                color: #4b566b;
                font-size: 1.05rem;
                line-height: 1.78;
            }

            .blog-post-body > :first-child {
                margin-top: 0;
            }

            .blog-post-body > :last-child {
                margin-bottom: 0;
            }

            .blog-post-body p {
                margin-bottom: 1.1rem;
            }

            .blog-post-body h2,
            .blog-post-body h3,
            .blog-post-body h4 {
                margin-top: 2rem;
                margin-bottom: .85rem;
                color: #373f50;
                line-height: 1.28;
            }

            .blog-post-body h2 {
                font-size: 1.55rem;
            }

            .blog-post-body h3 {
                font-size: 1.3rem;
            }

            .blog-post-body a {
                color: #e50077;
                font-weight: 700;
                text-decoration: none;
            }

            .blog-post-body a:hover {
                color: #373f50;
            }

            .blog-post-body img {
                max-width: 100%;
                height: auto;
                border-radius: .5rem;
            }

            .blog-post-body ul,
            .blog-post-body ol {
                padding-left: 1.35rem;
                margin-bottom: 1.25rem;
            }

            .blog-post-body li + li {
                margin-top: .35rem;
            }

            .blog-post-body blockquote {
                margin: 1.75rem 0;
                padding: 1.15rem 1.25rem;
                border-left: .22rem solid #e50077;
                border-radius: .5rem;
                background: #fff6fb;
                color: #373f50;
                font-weight: 600;
            }

            .blog-post-body table {
                width: 100%;
                margin: 1.5rem 0;
                border-collapse: collapse;
                font-size: .95rem;
            }

            .blog-post-body th,
            .blog-post-body td {
                padding: .75rem;
                border: 1px solid #e3e9ef;
                vertical-align: top;
            }

            .blog-post-body th {
                background: #f6f9fc;
                color: #373f50;
                font-weight: 700;
            }

            .blog-post-followup {
                max-width: 1120px;
                margin-right: auto;
                margin-left: auto;
            }

            .blog-cta-buttons {
                display: grid;
                gap: 0.75rem;
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }

            .blog-cta-button {
                align-items: center;
                display: inline-flex;
                font-weight: 600;
                gap: 0.5rem;
                justify-content: center;
                min-height: 3.25rem;
                width: 100%;
            }

            .blog-cta-button__icon {
                font-size: 1.1rem;
                line-height: 1;
            }

            @media (min-width: 768px) {
                .blog-cta-buttons {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1200px) {
                .blog-cta-buttons {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            @media (max-width: 767.98px) {
                .blog-post-header {
                    padding: 1.35rem 1.25rem 1rem;
                }

                .blog-post-body {
                    padding: 1.45rem 1.25rem 1.65rem;
                    font-size: 1rem;
                    line-height: 1.68;
                }

                .blog-post-body h2 {
                    font-size: 1.35rem;
                }

                .blog-post-body h3 {
                    font-size: 1.18rem;
                }
            }
        </style>
    @endpush
@endif

@section('content')

    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('catalog.route.blog') }}"><i class="ci-home"></i>Blog</a></li>
        </ol>
    </nav>


    @if($isBlogListing)
        <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">
            <h1 class="h2 mb-3 mb-md-0 me-3">Blog</h1>
        </section>
    @endif



    @if($isBlogListing)

    <div class=" pb-5 mb-2 mb-md-4">


            <!-- Entries grid-->
            <div class="masonry-grid" data-columns="3">
                @foreach ($blogs as $blog)

                <article class="masonry-grid-item">
                    <div class="card">
                        <a class="blog-entry-thumb" href="{{ route('catalog.route.blog', ['blog' => $blog]) }}"><img class="card-img-top" src="{{ $blog->image }}" alt="{{ $blog->title }}" loading="lazy" decoding="async"></a>
                        <div class="card-body">
                            <h2 class="h6 blog-entry-title"><a href="{{ route('catalog.route.blog', ['blog' => $blog]) }}">{{ $blog->title }}</a></h2>
                            <p class="fs-sm">{{ $blog->short_description }}</p>
                        </div>
                        <div class="card-footer d-flex align-items-left fs-xs">
                            <div class="me-auto text-nowrap"><a class="blog-entry-meta-link text-nowrap" href="{{ route('catalog.route.blog', ['blog' => $blog]) }}">{{ \Carbon\Carbon::make($blog->created_at)->locale('hr')->format('d.m.Y.') }}</a></div>
                        </div>
                    </div>
                </article>

                @endforeach

            </div>

            {{ $blogs->onEachSide(1)->links() }}


    </div>
    @else
        <article class="blog-post-shell">
            <div class="blog-post-card">
                <header class="blog-post-header">
                    <h1 class="h2 mb-3">{{ $blog->title }}</h1>
                    <div class="blog-post-meta">
                        <span><span class="blog-post-date-icon" aria-hidden="true"></span>{{ \Carbon\Carbon::make($blog->publish_date ?: $blog->created_at)->locale('hr')->format('d.m.Y.') }}</span>
                        <span>Zuzi blog</span>
                    </div>
                </header>

                <div class="blog-post-hero">
                    <img src="{{ $blog->image }}" alt="{{ $blog->title }}" loading="eager" fetchpriority="high" decoding="async">
                </div>

                <div class="blog-post-body">
                    {!! $blog->description !!}
                </div>
            </div>
        </article>

        @if($relatedProducts->count())
            <section class="blog-post-followup pb-5 mb-2 mb-xl-4">
                <div class="flex-wrap justify-content-between align-items-center text-start">
                    <h2 class="h3 mb-4 pt-1 font-title me-3">{{ $relatedProductsHeading }}</h2>
                </div>
                <div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled pt-2 product-page-carousel">
                    <div class="tns-carousel-inner" data-carousel-options='@json($productShelfCarouselOptions)'>
                        @foreach ($relatedProducts as $relatedProduct)
                            <div>
                                @include('front.catalog.category.product', ['product' => $relatedProduct])
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if($ctaBlocks->count())
            <section class="blog-post-followup pb-4 mb-4">
                @foreach($ctaBlocks as $ctaBlock)
                    <div class="blog-cta-block p-4 p-md-5 mb-4">
                        <h2 class="h3 mb-3">{{ $ctaBlock->title }}</h2>

                        @if(filled($ctaBlock->description))
                            <p class="fs-md text-muted mb-4">{!! nl2br(e($ctaBlock->description)) !!}</p>
                        @endif

                        <div class="blog-cta-buttons">
                            @foreach($ctaBlock->buttons as $ctaButton)
                                <a href="{{ $ctaButton->url }}" class="btn {{ $ctaButtonClasses[$ctaButton->style] ?? 'btn-outline-primary' }} blog-cta-button">
                                    @if(filled($ctaButton->icon))
                                        <span class="blog-cta-button__icon">{{ $ctaButton->icon }}</span>
                                    @endif
                                    <span>{{ $ctaButton->label }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>
        @endif


    @endif

@endsection

@if(! $isBlogListing)
    @push('js_after')
        <script type="application/ld+json">
            {!! collect(\App\Helpers\Metatags::articleSchema($blog))->toJson() !!}
        </script>
    @endpush
@else
    @push('js_after')
        @php
            $blogSchemas = [
                \App\Helpers\Metatags::pageSchema(
                    'CollectionPage',
                    'Blog o knjigama',
                    \App\Models\Seo::description(null, 'Medijske objave, clanci i preporuke o knjigama iz ' . \App\Models\Seo::brand() . '.'),
                    \App\Models\Seo::canonical(request())
                ),
                \App\Helpers\Metatags::itemListSchema(
                    $blogs->getCollection()->map(function ($item) {
                        return [
                            'name' => $item->title,
                            'url' => route('catalog.route.blog', ['blog' => $item]),
                        ];
                    }),
                    \App\Models\Seo::canonical(request()),
                    'Objave o knjigama'
                ),
            ];
        @endphp
        <script type="application/ld+json">
            {!! collect($blogSchemas)->toJson() !!}
        </script>
    @endpush
@endif
