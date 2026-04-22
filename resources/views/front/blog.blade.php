@extends('front.layouts.app')

@php
    $isBlogListing = isset($blogs);
    $blogSeo = $isBlogListing ? null : \App\Models\Seo::getBlogData($blog);
    $relatedProducts = $relatedProducts ?? collect();
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


    <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">


        @if($isBlogListing)
            <h1 class="h2 mb-3 mb-md-0 me-3">Blog</h1>
        @else
            <h1 class="h2 mb-3 mb-md-0 me-3">{{ $blog->title }}</h1>
        @endif

    </section>



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
        <div class="mt-2 mb-5 fs-md" style="max-width:1240px">
                    <!-- Post meta-->
                    <!-- Gallery-->
                    <div class="gallery row pb-2">
                        <div class="col-sm-12 mb-2"><img src="{{ $blog->image }}" alt="{{ $blog->title }}" loading="eager" fetchpriority="high" decoding="async"></div>

                    </div>
                    <!-- Post content-->

                    {!! $blog->description !!}

        </div>

        @if($relatedProducts->count())
            <section class="pb-5 mb-2 mb-xl-4">
                <div class="flex-wrap justify-content-between align-items-center text-center">
                    <h2 class="h3 mb-4 pt-1 font-title me-3 text-center">Povezani artikli</h2>
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
