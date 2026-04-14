@extends('front.layouts.app')
@section ('title', $seo['title'])
@section ('description', $seo['description'])
@section('seo_image', $prod->image)
@section('seo_image_alt', $prod->image_alt ?: 'Naslovnica knjige ' . $prod->name)
@section('og_type', 'product')
@section('seo_updated_time', optional($prod->updated_at)->toAtomString())
@php
    $productCoverAlt = $prod->image_alt ?: 'Naslovnica knjige ' . $prod->name;
    $relatedHeading = $subcat ? 'Slične knjige iz kategorije ' . $subcat->title : ($cat ? 'Slične knjige iz kategorije ' . $cat->title : 'Možda vas zanima');
    $hasKnownPublisher = $prod->publisher
        && \Illuminate\Support\Str::lower(trim((string) $prod->publisher->title)) !== 'nepoznati izdavač';
    $reviews = $reviews ?? collect();
    $reviewsCount = $reviews->count();
    $reviewsAverage = $reviewsCount ? round((float) $reviews->avg('stars'), 1) : 0;
    $hasReviewErrors = $errors->has('name') || $errors->has('email') || $errors->has('stars') || $errors->has('message');
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

@push('meta_tags')
    <meta property="product:price:amount" content="{{ number_format($prod->special(), 2, '.', '') }}" />
    <meta property="product:price:currency" content="EUR" />
    <meta property="product:availability" content="{{ $prod->quantity ? 'instock' : 'out of stock' }}" />
    <meta property="product:retailer_item_id" content="{{ $prod->sku }}" />
@endpush

@push('css_after')
    <link rel="stylesheet" media="screen" href="{{ asset('vendor/lightgallery/css/lightgallery-bundle.min.css')}}"/>
    <style>
        .product-page-carousel .tns-ovh,
        .product-page-carousel .tns-item,
        .product-page-carousel .tns-carousel-inner {
            touch-action: pan-y pinch-zoom;
        }
    </style>
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



    <!-- Page title + breadcrumb-->
    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            @if ($group)
                @if ($group && ! $cat && ! $subcat)
                  <!--  <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ \Illuminate\Support\Str::ucfirst($group) }}</li> -->
                @elseif ($group && $cat)
              <!--      <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group]) }}">{{ \Illuminate\Support\Str::ucfirst($group) }}</a></li> -->
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

        </ol>
    </nav>
    <!-- Content-->
    <section class="row g-0 mx-n2 ">
        @include('back.layouts.partials.session')
        <!-- Product Gallery + description-->
        <div class="col-xl-6 px-2 mb-3">
            <div class="h-100 bg-light shadow rounded-3 p-4">
                <div class="product-gallery">
                    <div class="product-gallery-preview  gallery order-sm-2">
                            @if ( ! empty($prod->image))
                                <div class="product-gallery-preview-item active" id="first">
                                    <a class="gallery-item position-relative" data-sub-html='{{ $prod->name }}' href="{{ asset($prod->image) }}">
                                        @if (!empty($prod->delivery_24h))
                                            <span class="badge badgerounded-pill badge-shadow d-inline-flex align-items-center" style="position: absolute; top: 10px; right: 10px; z-index: 5; background: rgb(229, 0, 119); color: rgb(255, 255, 255); padding: 0.55rem 0.8rem;font-size: 16px;font-weight: 600;">
                                                <i class="ci-delivery me-1"></i>24 sata
                                            </span>
                                        @endif
                                        <img src="{{ $prod->image }}" alt="{{ $productCoverAlt }}" height="800" loading="eager" fetchpriority="high" decoding="async">
                                    </a>
                                </div>
                            @endif
                            @if ($prod->images->count())
                                @foreach ($prod->images as $key => $image)
                                        <div class="product-gallery-preview-item" id="key{{ $key + 1 }}">
                                            <a class="gallery-item rounded-3 position-relative" href="{{ asset($image->image) }}">
                                                @if (!empty($prod->delivery_24h))
                                                    <span class="badge rounded-pill badge-shadow d-inline-flex align-items-center" style="position:absolute; top:10px; right:10px; z-index:5; background:#e50077; color:#fff; padding:.35rem .6rem;">
                                                        <i class="ci-delivery me-1"></i>24 sata
                                                    </span>
                                                @endif
                                                <img src="{{ asset($image->image) }}" alt="{{ $image->alt ?: 'Detalj knjige ' . $prod->name }}" height="800" loading="lazy" decoding="async">
                                            </a>
                                        </div>
                                @endforeach
                            @endif
                    </div>
                    <div class="product-gallery-thumblist order-sm-1">
                        @if ($prod->images->count())
                            @if ( ! empty($prod->thumb))
                                <a class="product-gallery-thumblist-item active" href="#first"><img src="{{ $prod->thumb }}" alt="{{ $productCoverAlt }}" loading="lazy" decoding="async"></a>
                            @endif
                            @foreach ($prod->images as $key => $image)
                                <a class="product-gallery-thumblist-item" href="#key{{ $key + 1 }}"><img src="{{ url('cache/thumb?size=100x100&src=' . $image->thumb) }}" width="100" height="100" alt="{{ $image->alt ?: 'Detalj knjige ' . $prod->name }}"></a>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 px-2 mb-3">
            <div class="h-100 bg-light shadow  rounded-3 py-5 px-4 px-sm-5">

        @if ( $prod->quantity < 1)
                    <span class="badge bg-warning ">Rasprodano</span>
       @endif

   @if ($prod->main_price > $prod->main_special)
       <span class="badge bg-primary ">-{{ number_format(floatval(\App\Helpers\Helper::calculateDiscount($prod->price, $prod->special())), 0) }}%</span>
   @endif



   <h1 class="h3">{{ $prod->name }}</h1>

        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <a id="openReview" href="#reviews" class="d-inline-flex align-items-center text-decoration-none">
                <div class="star-rating me-2">
                    @for ($i = 0; $i < 5; $i++)
                        @if (floor($reviewsAverage) - $i >= 1)
                            <i class="star-rating-icon ci-star-filled active"></i>
                        @else
                            <i class="star-rating-icon ci-star"></i>
                        @endif
                    @endfor
                </div>

                @if ($reviewsCount)
                    <span class="fs-sm text-body">{{ number_format($reviewsAverage, 1) }}/5 · {{ $reviewsCount }} {{ $reviewsCount === 1 ? 'komentar' : 'komentara' }}</span>
                @else
                    <span class="fs-sm text-body">Još nema komentara, budite prvi.</span>
                @endif
            </a>
        </div>

       <div class="mb-1">
           @if ($prod->main_price > $prod->main_special)
               <span class="h3 fw-normal text-accent me-1">{{ $prod->main_special_text }}</span>
               <span class="text-muted fs-lg me-3"><s>*{{ $prod->main_price_text }}</s></span>

           @else
               <span class="h3 fw-normal text-accent me-1">{{ $prod->main_price_text }}</span>
           @endif

       </div>

   @if($prod->secondary_price_text)
       <div class="mb-1 mt-1 text-start">
           @if ($prod->main_price > $prod->main_special)
               <span class=" fs-sm text-muted me-1"> {{ $prod->secondary_special_text }}</span>
               <span class="text-muted fs-sm me-3"><s>*{{ $prod->secondary_price_text }}</s></span>
           @else
               <span class="fs-sm text-muted  me-1">{{ $prod->secondary_price_text }}</span>
           @endif
       </div>
   @endif
   @if ($prod->main_price > $prod->main_special)

       <div class="mb-3 mt-1 text-start">
           <span class=" fs-sm text-muted me-1"> *Najniža cijena u zadnjih 30 dana.</span>
       </div>





   @endif



            @if($prod->kat)
                <div class="d-flex row justify-content-between mt-2"><div class="col-md-12"><div role="alert" class="alert alert-info d-flex  mb-1 "><div class="alert-icon"><i class="ci-truck"></i></div> <small>Dostava za Regionalne naslove je 20 dana.  </small></div></div></div>
            @endif
            @if ( $prod->quantity > 0)
   <add-to-cart-btn id="{{ $prod->id }}" available="{{ $prod->quantity }}"></add-to-cart-btn>
                @else
                <div class="cart mb-3 mt-3 d-flex align-items-center" >
                    <a class="btn btn-primary btn-shadow d-block w-100" href="#wishlist-modal" data-bs-toggle="modal"><i class="ci-bell"></i> Obavijesti me o dostupnosti</a>
                </div>
            @endif
   <!-- Product panels-->
   <div class="accordion mb-4" id="productPanels">
       <div class="accordion-item">
           <h3 class="accordion-header"><a class="accordion-button" href="#productInfo" role="button" data-bs-toggle="collapse" aria-expanded="true" aria-controls="productInfo"><i class="ci-announcement text-muted fs-lg align-middle mt-n1 me-2"></i>Osnovne informacije</a></h3>
           <div class="accordion-collapse collapse show" id="productInfo" data-bs-parent="#productPanels">
               <div class="accordion-body">

                   <ul class="fs-sm ps-4 mb-0">
                       @if ($prod->author)
                           <li><strong>Autor:</strong> <a href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">{{ $prod->author->title }} </a></li>
                       @endif
                       @if ($hasKnownPublisher)
                           <li><strong>Nakladnik:</strong> <a href="{{ route('catalog.route.publisher', ['publisher' => $prod->publisher]) }}">{{ $prod->publisher->title }}</a> </li>
                       @endif
                       @if ($prod->isbn)
                       <li><strong>EAN:</strong> {{ $prod->isbn }} </li>
                       @endif
                           @if ($prod->quantity)
                               @if ($prod->decrease)
                                   <li><strong>Dostupnost:</strong> {{ $prod->quantity }} </li>
                               @else
                                   <li><strong>Dostupnost:</strong> Dostupno</li>
                               @endif
                           @else
                               <li><strong>Dostupnost:</strong> Rasprodano</li>
                           @endif

                           <li><strong>Stanje:</strong> {{ $prod->condition}}</li>
                   </ul>

               </div>
           </div>
       </div>
       <div class="accordion-item">
           <h3 class="accordion-header"><a class="accordion-button collapsed" href="#shippingOptions" role="button" data-bs-toggle="collapse" aria-expanded="true" aria-controls="shippingOptions"><i class="ci-delivery text-muted lead align-middle mt-n1 me-2"></i>Opcije dostave</a></h3>
           <div class="accordion-collapse collapse" id="shippingOptions" data-bs-parent="#productPanels">
               <div class="accordion-body fs-sm">

                   @foreach($shipping_methods as $shipping_method)
                       <div class="d-flex justify-content-between border-bottom py-2">
                           <div>
                               <div class="fw-semibold text-dark">{{ $shipping_method->title }}</div>
                              {{--  <div class="fs-sm text-muted"> Besplatna dostava za narudžbe iznad {{ config('settings.free_shipping') }}€</div>--}}
                               @if ($prod->shipping_time)

                                   <span class=" fs-sm text-muted me-1"> Rok dostave: {{ $prod->shipping_time }}</span>

                               @endif
                           </div>
                           <div>{{ $shipping_method->data->price }}€ </div>
                       </div>
                   @endforeach
                       <div class="d-flex row justify-content-between mt-2"><div class="col-md-12"><div role="alert" class="alert alert-info d-flex  mb-1 "><div class="alert-icon"><i class="ci-truck"></i></div> Besplatna dostava za narudžbe iznad {{ config('settings.free_shipping') }}€</div></div></div>
               </div>
               <small class="mt-2"></small>

           </div>
       </div>
       <div class="accordion-item">
           <h3 class="accordion-header"><a class="accordion-button collapsed" href="#localStore" role="button" data-bs-toggle="collapse" aria-expanded="true" aria-controls="localStore"><i class="ci-card text-muted fs-lg align-middle mt-n1 me-2"></i>Načini plaćanja</a></h3>
           <div class="accordion-collapse collapse" id="localStore" data-bs-parent="#productPanels">
               <div class="accordion-body fs-sm">


                   @foreach($payment_methods as $payment_method)
                       @if($prod->origin == 'Engleski' and $payment_method->code == 'cod' )

                       @else
                           <div class="d-flex justify-content-between border-bottom py-2">
                               <div>
                                   <div class="fw-semibold text-dark">{{ $payment_method->title }}</div>
                                   @if (isset($payment_method->data->description))
                                       <div class="fs-sm text-muted">{{ $payment_method->data->description }}</div>
                                   @endif
                               </div>
                           </div>
                       @endif
                   @endforeach

               </div>


           </div>
       </div>
   </div>

   @if ($prod->author || $hasKnownPublisher || $cat || $subcat)
       <div class="border-top pt-3 mt-2 mb-3">
           <h2 class="h6 mb-3">Istražite još</h2>
           <div class="d-flex flex-wrap gap-2">
               @if ($prod->author)
                   <a class="btn btn-outline-primary btn-sm" href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">Još knjiga autora {{ \Illuminate\Support\Str::limit($prod->author->title, 28) }}</a>
               @endif
               @if ($hasKnownPublisher)
                   <a class="btn btn-outline-primary btn-sm" href="{{ route('catalog.route.publisher', ['publisher' => $prod->publisher]) }}">Više od nakladnika {{ \Illuminate\Support\Str::limit($prod->publisher->title, 24) }}</a>
               @endif
               @if ($cat)
                   <a class="btn btn-outline-secondary btn-sm" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">Kategorija {{ \Illuminate\Support\Str::limit($cat->title, 28) }}</a>
               @endif
               @if ($cat && $subcat)
                   <a class="btn btn-outline-secondary btn-sm" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat, 'subcat' => $subcat]) }}">Potkategorija {{ \Illuminate\Support\Str::limit($subcat->title, 24) }}</a>
               @endif
           </div>
       </div>
   @endif

   <!-- Sharing-->
   <!-- ShareThis BEGIN --><div class="sharethis-inline-share-buttons"></div><!-- ShareThis END -->




            </div>
</div>
</section>
<!-- Related products-->

<section class="mx-n2 pb-2 px-2 mb-xl-3" id="tabs_widget">
<div class="bg-light px-2 mb-3 shadow rounded-3">
<!-- Tabs-->
<ul class="nav nav-tabs" role="tablist">
   <li class="nav-item"><a class="nav-link py-4 px-sm-4 active" href="#specs" data-bs-toggle="tab" role="tab"><span>Opis i komentari</span> </a></li>

</ul>
<div class="px-4 pt-lg-3 pb-3 mb-5">
   <div class="tab-content px-lg-3">
       <!-- Tech specs tab-->
       <div class="tab-pane fade show active" id="specs" role="tabpanel">
           <!-- Specs table-->
           <div class="row pt-2">
               <div class="col-lg-7 col-sm-7">
                   <h3 class="h6">Sažetak</h3>
                   <div class=" fs-md pb-2 mb-4">
                       {!! $prod->description !!}
                   </div>


                   @if ($prod->author_web_url or $prod->serial_web_url or $prod->wiki_url or $prod->youtube_channel or $prod->youtube_product_url or $prod->goodreads_author_url or $prod->goodreads_book_url)

                       <h3 class="h6 mt-4">Multimedia i linkovi</h3>
                       <ul class="list-unstyled fs-sm pb-2">
                           @if ($prod->youtube_product_url)
                               <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">YouTube Video:</span><span><i class="ci-youtube text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->youtube_product_url }}">Pogledajte video</a></span></li>
                           @endif

                           @if ($prod->youtube_channel)
                                <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">YouTube Kanal:</span><span><i class="ci-youtube text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->youtube_channel }}">Pogledajte video</a></span></li>
                           @endif

                           @if ($prod->wiki_url)
                                   <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Wikipedia:</span><span><i class="ci-link text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->wiki_url }}">Pogledajte stranicu</a></span></li>
                           @endif

                           @if ($prod->author_web_url)
                                   <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Web stranica autora:</span><span><i class="ci-link text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->author_web_url }}">Pogledajte stranicu</a></span></li>
                           @endif

                            @if ($prod->serial_web_url)
                                   <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Web stranica serijala:</span><span><i class="ci-link text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->serial_web_url }}">Pogledajte stranicu</a></span></li>
                            @endif

                            @if ($prod->goodreads_author_url)
                                   <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Goodreads stranica autora:</span><span><i class="ci-link text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->goodreads_author_url }}">Pogledajte stranicu</a></span></li>

                            @endif

                            @if ($prod->goodreads_book_url)
                                   <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Goodreads stranica knjige:</span><span><i class="ci-link text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->goodreads_book_url }}">Pogledajte stranicu</a></span></li>

                            @endif
                       </ul>

                   @endif
               </div>
               <div class="col-lg-5 col-sm-5 ">
                   <h3 class="h6">Dodatne informacije</h3>
                   <ul class="list-unstyled fs-sm pb-2">


                       @if ($prod->author)
                               <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Autor:</span><span><a href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">{{ Illuminate\Support\Str::limit($prod->author->title, 30) }}</a></span></li>
                       @endif
                       @if ($hasKnownPublisher)
                               <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Nakladnik:</span><span><a href="{{ route('catalog.route.publisher', ['publisher' => $prod->publisher]) }}">{{ Illuminate\Support\Str::limit($prod->publisher->title, 30) }}</a> </span></li>
                       @endif


                       @if ($prod->binding)
                           <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Uvez:</span><span>{{ $prod->binding  }}</span></li>
                        @endif
                       @if ($prod->origin)
                           <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Mjesto izdavanja:</span><span>{{ $prod->origin  }}</span></li>
                       @endif
                        @if ($prod->year)
                           <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Godina izdanja:</span><span>{{ $prod->year }}</span></li>
                       @endif
                       @if ($prod->pages)
                           <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Broj stranica:</span><span>{{ $prod->pages }}</span></li>
                      @endif
                           @if ($prod->letter)
                               <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Pismo:</span><span>{{ $prod->letter }}</span></li>
                           @endif
                       @if ($prod->dimensions)
                           <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Dimenzije:</span><span>{{ $prod->dimensions  }}</span></li>
                       @endif
                       @if ($prod->isbn)
                            <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">EAN:</span><span>{{ $prod->isbn }}</span></li>
                       @endif

                   </ul>

               </div>
           </div>

           <hr class="my-4">

           <section id="reviews" class="pt-2">
               <div class="row pt-2 pb-3">
                   <div class="col-lg-4 col-md-5 mb-3">
                       <h3 class="h4 mb-1">{{ $reviewsCount }} {{ $reviewsCount === 1 ? 'komentar' : 'komentara' }}</h3>

                       @if ($reviewsCount)
                           <div class="star-rating me-2">
                               @for ($i = 0; $i < 5; $i++)
                                   @if (floor($reviewsAverage) - $i >= 1)
                                       <i class="ci-star-filled fs-sm text-accent me-1"></i>
                                   @else
                                       <i class="ci-star fs-sm text-muted me-1"></i>
                                   @endif
                               @endfor
                           </div>
                           <span class="d-inline-block align-middle">{{ number_format($reviewsAverage, 1) }} prosječna ocjena</span>
                       @else
                           <p class="text-muted mb-0">Podijelite svoje iskustvo s ovom knjigom i pomozite drugim kupcima.</p>
                       @endif
                   </div>

                   <div class="col-lg-8 col-md-7">
                       @for ($i = 5; $i > 0; $i--)
                           <div class="d-flex align-items-center mb-2">
                               <div class="text-nowrap me-3"><span class="d-inline-block align-middle text-muted">{{ $i }}</span><i class="ci-star-filled fs-xs ms-1"></i></div>
                               <div class="w-100">
                                   <div class="progress" style="height: 4px;">
                                       <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $prod->percentreviews($reviews->where('stars', $i)->count(), $reviewsCount) }}%;" aria-valuenow="{{ $prod->percentreviews($reviews->where('stars', $i)->count(), $reviewsCount) }}" aria-valuemin="0" aria-valuemax="100"></div>
                                   </div>
                               </div>
                               <span class="text-muted ms-3">{{ $reviews->where('stars', $i)->count() }}</span>
                           </div>
                       @endfor
                   </div>
               </div>

               <hr class="mt-4 mb-3">

               <div class="row py-4">
                   <div class="col-md-7">
                       @if($reviewsCount)
                           @foreach($reviews as $review)
                               <div class="product-review pb-4 mb-4 border-bottom">
                                   <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                       <div class="d-flex align-items-center me-4 pe-2">
                                           <div>
                                               <h4 class="fs-sm mb-0">{{ trim($review->fname . ' ' . $review->lname) ?: $review->fname }}</h4>
                                               <span class="fs-ms fw-light text-muted">{{ \Carbon\Carbon::make($review->created_at)->locale('hr')->format('d.m.Y.') }}</span>
                                           </div>
                                       </div>

                                       <div class="star-rating" style="vertical-align: top !important;">
                                           @for ($i = 0; $i < 5; $i++)
                                               @if (floor($review->stars) - $i >= 1)
                                                   <i class="star-rating-icon ci-star-filled active"></i>
                                               @else
                                                   <i class="star-rating-icon ci-star"></i>
                                               @endif
                                           @endfor
                                       </div>
                                   </div>

                                   <p class="fs-md mb-2">{{ $review->message }}</p>
                               </div>
                           @endforeach
                       @else
                           <p class="mb-0">Trenutno nema komentara za ovaj naslov.</p>
                       @endif
                   </div>

                   <div class="col-md-5 mt-4 mt-md-0">
                       <div class="bg-secondary py-grid-gutter px-grid-gutter rounded-3">
                           <h3 class="h4 pb-2">Napišite komentar</h3>

                           <form class="needs-validation" method="post" action="{{ route('komentar.proizvoda') }}" novalidate>
                               @csrf

                               <div class="mb-3">
                                   <label class="form-label" for="review-name">Vaše ime <span class="text-danger">*</span></label>
                                   <input class="form-control" type="text" required id="review-name" name="name" value="{{ old('name', optional(auth()->user())->name) }}">
                                   @error('name')
                                       <div class="fs-sm text-danger mt-1">{{ $message }}</div>
                                   @enderror
                                   <div class="invalid-feedback">Upišite ime.</div>
                               </div>

                               <div class="mb-3">
                                   <label class="form-label" for="review-email">Email <span class="text-danger">*</span></label>
                                   <input class="form-control" type="email" required id="review-email" name="email" value="{{ old('email', optional(auth()->user())->email) }}">
                                   @error('email')
                                       <div class="fs-sm text-danger mt-1">{{ $message }}</div>
                                   @enderror
                                   <div class="invalid-feedback">Upišite ispravan email.</div>
                               </div>

                               <div class="mb-3">
                                   <label class="form-label" for="review-stars">Ocjena <span class="text-danger">*</span></label>
                                   <select class="form-select" required id="review-stars" name="stars">
                                       <option value="">Odaberite ocjenu</option>
                                       @for ($i = 5; $i >= 1; $i--)
                                           <option value="{{ $i }}" {{ (string) old('stars') === (string) $i ? 'selected' : '' }}>{{ $i }} / 5</option>
                                       @endfor
                                   </select>
                                   @error('stars')
                                       <div class="fs-sm text-danger mt-1">{{ $message }}</div>
                                   @enderror
                                   <div class="invalid-feedback">Odaberite ocjenu.</div>
                               </div>

                               <div class="mb-3">
                                   <label class="form-label" for="review-message">Komentar <span class="text-danger">*</span></label>
                                   <textarea class="form-control" rows="6" required id="review-message" name="message">{{ old('message') }}</textarea>
                                   @error('message')
                                       <div class="fs-sm text-danger mt-1">{{ $message }}</div>
                                   @enderror
                                   <div class="invalid-feedback">Upišite komentar.</div>
                               </div>

                               <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
                               <input type="hidden" name="product_id" value="{{ $prod->id }}">
                               <input type="hidden" name="recaptcha" id="recaptcha_review">

                               <button class="btn btn-primary btn-shadow d-block w-100" type="submit">Pošalji komentar</button>
                           </form>
                       </div>
                   </div>
               </div>
           </section>
       </div>
       <!-- Reviews tab-->

   </div>
</div>
</div>
</section>

@if ($prod->author && $authorProducts->count())
    <section class="pb-5 mb-2 mb-xl-4">
        <div class="flex-wrap justify-content-between align-items-center text-center">
            <h2 class="h3 mb-4 pt-1 font-title me-3 text-center">Još knjiga autora {{ $prod->author->title }}</h2>
        </div>
        <div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled pt-2 product-page-carousel">
            <div class="tns-carousel-inner" data-carousel-options='@json($productShelfCarouselOptions)'>
                @foreach ($authorProducts as $authorProduct)
                    <div>
                        @include('front.catalog.category.product', ['product' => $authorProduct])
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

@if ($hasKnownPublisher && $publisherProducts->count())
    <section class="pb-5 mb-2 mb-xl-4">
        <div class="flex-wrap justify-content-between align-items-center text-center">
            <h2 class="h3 mb-4 pt-1 font-title me-3 text-center">Više knjiga nakladnika {{ $prod->publisher->title }}</h2>
        </div>
        <div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled pt-2 product-page-carousel">
            <div class="tns-carousel-inner" data-carousel-options='@json($productShelfCarouselOptions)'>
                @foreach ($publisherProducts as $publisherProduct)
                    <div>
                        @include('front.catalog.category.product', ['product' => $publisherProduct])
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

@if ($relatedProducts->count())
    <section class="pb-5 mb-2 mb-xl-4">
        <div class="flex-wrap justify-content-between align-items-center text-center">
            <h2 class="h3 mb-4 pt-1 font-title me-3 text-center">{{ $relatedHeading }}</h2>
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

@endsection

@push('js_after')
<script type="application/ld+json">
{!! collect($crumbs)->toJson() !!}
</script>
<script type="application/ld+json">
{!! collect(\App\Helpers\Metatags::productSchema($prod, $reviews))->toJson() !!}
</script>
<script type="application/ld+json">
{!! collect($bookscheme)->toJson() !!}
</script>
<script type='text/javascript' src='https://platform-api.sharethis.com/js/sharethis.js#property=6134a372eae16400120a5035&product=sop' async='async'></script>
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.sitekey') }}"></script>

<script>
    function scrollToReviewsSection() {
        const reviewsSection = document.getElementById('reviews');

        if (reviewsSection) {
            reviewsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    $('#openReview').on('click', function(e) {
        e.preventDefault();
        scrollToReviewsSection();
    });

    @if ($hasReviewErrors || session('review_submitted'))
        $(function() {
            scrollToReviewsSection();
        });
    @endif
</script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const reviewForm = document.querySelector('form[action="{{ route('komentar.proizvoda') }}"]');
        const reviewTokenInput = document.getElementById('recaptcha_review');

        if (!reviewForm || !reviewTokenInput || typeof grecaptcha === 'undefined') {
            return;
        }

        reviewForm.addEventListener('submit', (event) => {
            if (reviewForm.dataset.recaptchaReady === '1') {
                reviewForm.dataset.recaptchaReady = '0';
                return;
            }

            event.preventDefault();

            grecaptcha.ready(() => {
                grecaptcha.execute('{{ config('services.recaptcha.sitekey') }}', { action: 'product_review' })
                    .then((token) => {
                        reviewTokenInput.value = token;
                        reviewForm.dataset.recaptchaReady = '1';
                        reviewForm.submit();
                    });
            });
        });
    });
</script>
@include('front.layouts.modals.wishlist-email')
@endpush
