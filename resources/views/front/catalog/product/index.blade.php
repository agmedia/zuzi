@extends('front.layouts.app')
@section ('title', $seo['title'])
@section ('description', $seo['description'])
@push('meta_tags')

    <link rel="canonical" href="{{ env('APP_URL')}}/{{ $prod->url }}" />
    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="product" />
    <meta property="og:title" content="{{ $seo['title'] }}" />
    <meta property="og:description" content="{{ $seo['description']  }}" />
    <meta property="og:url" content="{{ env('APP_URL')}}/{{ $prod->url }}"  />
    <meta property="og:site_name" content="Antikvarijat Biblos" />
    <meta property="og:updated_time" content="{{ $prod->updated_at  }}" />
    <meta property="og:image" content="{{ asset($prod->image) }}" />
    <meta property="og:image:secure_url" content="{{ asset($prod->image) }}" />
    <meta property="og:image:width" content="640" />
    <meta property="og:image:height" content="480" />
    <meta property="og:image:type" content="image/jpeg" />
    <meta property="og:image:alt" content="{{ $prod->image_alt }}" />
    <meta property="product:price:amount" content="{{ number_format($prod->price, 2) }}" />
    <meta property="product:price:currency" content="HRK" />
    <meta property="product:availability" content="instock" />
    <meta property="product:retailer_item_id" content="{{ $prod->sku }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $seo['title'] }}" />
    <meta name="twitter:description" content="{{ $seo['description'] }}" />
    <meta name="twitter:image" content="{{ asset($prod->image) }}" />

@endpush

@if (isset($gdl))
    @section('google_data_layer')
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({
                'event': 'view_item',
                'ecommerce': {
                    'items': [<?php echo json_encode($gdl); ?>]
                } });
        </script>
    @endsection
@endif

@section('content')

    <!-- Page Title-->
    <div class="page-title-overlap bg-dark pt-4" style="background-image: url({{ config('settings.images_domain') . 'media/img/indexslika.jpg' }});-webkit-background-size: cover;-moz-background-size: cover;-o-background-size: cover;background-size: cover;">
        <div class="container d-lg-block justify-content-end py-2 py-lg-3">
            <div class="order-lg-2 mb-3 mb-lg-0 pb-lg-2">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-light flex-lg-nowrap justify-content-center justify-content-lg-start">
                        <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>

                        @if ($group)
                            @if ($group && ! $cat && ! $subcat)
                                <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ \Illuminate\Support\Str::ucfirst($group) }}</li>
                            @elseif ($group && $cat)
                                <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group]) }}">{{ \Illuminate\Support\Str::ucfirst($group) }}</a></li>
                            @endif

                            @if ($cat && ! $subcat)
                                @if ($prod)
                                    <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                                @else
                                    <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $cat->title }}</li>
                                @endif
                            @elseif ($cat && $subcat)
                                <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                                @if ($prod)
                                    @if ($cat && ! $subcat)
                                        <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ \Illuminate\Support\Str::limit($prod->name, 50) }}</a></li>
                                    @else
                                        <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat, 'subcat' => $subcat]) }}">{{ $subcat->title }}</a></li>
                                    @endif
                                @endif
                            @endif
                        @endif

                        <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ \Illuminate\Support\Str::limit($prod->name, 50) }}</li>
                    </ol>
                </nav>
            </div>
            <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
                <h1 class="h3 text-light mb-0"><span style="font-weight: lighter;">{{ $prod->author ? $prod->author->title : '' }}:</span> {{ $prod->name }}</h1>
            </div>
        </div>
    </div>
    <div class="container">
        <!-- Gallery + details-->
        <div class="bg-light shadow-lg rounded-3 px-4 py-3 mb-5">
            <div class="px-lg-3">
                <div class="row">
                    <!-- Product gallery-->
                    <div class="col-lg-7 pe-lg-0 pt-lg-4">
                        <div class="product-gallery">
                            <div class="product-gallery-preview order-sm-2">
                                @if ( ! empty($prod->image))
                                    <div class="product-gallery-preview-item active" id="first"><img  src="{{ asset($prod->image) }}"  alt="{{ $prod->name }}"></div>
                                @endif

                                @if ($prod->images->count())
                                    @foreach ($prod->images as $key => $image)
                                        <div class="product-gallery-preview-item" id="key{{ $key + 1 }}"><img  src="{{ asset($image->image) }}" alt="{{ $image->alt }}"></div>
                                    @endforeach
                                @endif
                            </div>

                            <div class="product-gallery-thumblist order-sm-1" style="z-index: 10;position: relative;">
                                @if ($prod->images->count())
                                @if ( ! empty($prod->thumb))
                                    <a class="product-gallery-thumblist-item active" href="#first"><img src="{{ asset($prod->thumb) }}" alt="{{ $prod->name }}"></a>
                                @endif


                                    @foreach ($prod->images as $key => $image)
                                        <a class="product-gallery-thumblist-item" href="#key{{ $key + 1 }}"><img src="{{ url('cache/thumb?size=100x100&src=' . $image->thumb) }}" width="100" height="100" alt="{{ $image->alt }}"></a>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                    <!-- Product details-->
                    <div class="col-lg-5 pt-4 pt-lg-0">
                        <div class="product-details ms-auto pb-3">

                            <div class="mb-0 mt-4">
                                @if ($prod->main_price > $prod->main_special)
                                    <span class="h3 fw-normal text-accent me-1">{{ $prod->main_special_text }}</span>
                                    <del class="text-muted fs-lg me-3">{{ $prod->main_price_text }}</del>
                                    <span class="badge bg-danger align-middle mt-n2">Akcija</span>
                                @else
                                    <span class="h3 fw-normal text-accent me-1">{{ $prod->main_price_text }}</span>
                                @endif
                                @if ($prod->quantity)
                                    <span class="badge bg-success align-middle mt-n2">Dostupno</span>
                                @else
                                    <span class="badge bg-fourth align-middle mt-n2">Nedostupno</span>
                                @endif
                            </div>

                            @if($prod->secondary_price_text)
                                <div class="mb-3 mt-1">
                                    @if ($prod->main_price > $prod->main_special)
                                        <span class="h3 fw-normal text-accent me-1">{{ $prod->secondary_special_text }}</span>
                                        <del class="text-muted fs-lg me-3">{{ $prod->secondary_price_text }}</del>
                                    @else
                                        <span class="h3 fw-normal text-accent me-1">{{ $prod->secondary_price_text }}</span>
                                    @endif
                                </div>
                            @endif

                            <add-to-cart-btn id="{{ $prod->id }}"></add-to-cart-btn>

                            <!-- Product panels-->
                            <ul class="list-unstyled fs-sm spec">
                                @if ($prod->author)
                                    <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Autor</span><span class="text-muted"><a class="product-meta text-primary" href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">{{ $prod->author->title }}</a></span></li>
                                @endif
                                @if ($prod->publisher)
                                    <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Izdavač</span><a class="product-meta text-primary" href="{{ route('catalog.route.publisher', ['publisher' => $prod->publisher]) }}">{{ $prod->publisher->title }}</a></li>
                                @endif
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Šifra</span><span class="text-muted">{{ $prod->sku }}</span></li>
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Broj stranica</span><span class="text-muted">{{ $prod->pages ?: '...' }}</span></li>
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Godina izdanja</span><span class="text-muted">{{ $prod->year ?: '...' }}</span></li>
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Dimenzije</span><span class="text-muted">{{ $prod->dimensions.' cm' ?: '...' }}</span></li>
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Mjesto izdavanja</span><span class="text-muted">{{ $prod->origin ?: '...' }}</span></li>
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Pismo</span><span class="text-muted">{{ $prod->letter ?: '...' }}</span></li>
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Stanje</span><span class="text-muted">{{ $prod->condition ?: '...' }}</span></li>
                                <li class="d-flex justify-content-between mb-2 pb-2 border-bottom"><span class="text-dark fw-medium">Uvez</span><span class="text-muted">{{ $prod->binding ?: '...' }}</span></li>
                            </ul>

                            <div class=" pt-2 pb-4 mb-1">
                                <div class="mt-3"><span class="d-inline-block align-middle text-muted fs-sm me-3 mt-1 mb-2">Podijeli:</span>
                                    <!-- ShareThis BEGIN --><div class="sharethis-inline-share-buttons"></div><!-- ShareThis END -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row align-items-center py-md-3">
            <div class="col-lg-8 col-md-12 offset-lg-2 py-4 text-center">
                <h2 class="h3 mb-2 pb-0">{{ $prod->name }}</h2>
                @if ($prod->author)
                    <h3 class="h6 mb-4">{{ $prod->author->title }}</h3>
                @endif
                <p class="fs-md pb-2">{!! $prod->description !!}</p>
                @if ($prod->author)
                    <div class="mt-3 me-3"><a class="btn-tag me-2 mb-2" href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">#{{ $prod->author->title }}</a></div>
                @endif
            </div>
        </div>
    </div>

    <!-- Product carousel (You may also like)-->
    <div class="container py-5 my-md-3">
        <h2 class="h3 text-center pb-4">Preporučamo</h2>
        <div class="tns-carousel tns-controls-static tns-controls-outside">
            <div class="tns-carousel-inner" data-carousel-options='{"items": 2, "controls": true, "nav": true, "autoHeight": true, "responsive": {"0":{"items":2, "gutter": 10},"500":{"items":2, "gutter": 18},"768":{"items":3, "gutter": 20}, "1100":{"items":4, "gutter": 30}}}'>
                @foreach ($cat->products()->get()->take(5) as $cat_product)
                    @if ($cat_product->id  != $prod->id)
                        <div>
                            @include('front.catalog.category.product', ['product' => $cat_product])
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

@endsection

@push('js_after')
    <script type="application/ld+json">
        {!! collect($crumbs)->toJson() !!}
    </script>
    <script type="application/ld+json">
        {!! collect($bookscheme)->toJson() !!}
    </script>
    <script type='text/javascript' src='https://platform-api.sharethis.com/js/sharethis.js#property=6134a372eae16400120a5035&product=sop' async='async'></script>
@endpush
