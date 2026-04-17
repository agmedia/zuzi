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
    $reviewRewardPoints = \App\Models\Back\Marketing\Review::rewardPoints();
    $reviewMonthlyLimit = \App\Models\Back\Marketing\Review::monthlyLimit();
    $shouldOpenReviewForm = $reviewsCount > 0 || $hasReviewErrors || session('review_submitted');
    $showReviewPromoButton = ! $reviewsCount && ! $shouldOpenReviewForm;
    $giftWrapAllowed = \App\Services\GiftWrapService::isEligibleProduct($prod);
    $isTrackedQuantity = (bool) $prod->decrease;
    $isInStock = (int) $prod->quantity > 0;
    $availabilityLabel = $isInStock
        ? ($isTrackedQuantity ? 'Na stanju: ' . $prod->quantity . ' kom.' : 'Dostupno za narudžbu')
        : 'Trenutno rasprodano';
    $availabilityDetail = $isInStock
        ? ($isTrackedQuantity ? 'Prikazana količina ažurira se u stvarnom vremenu.' : 'Naslov je dostupan i spreman za kupnju.')
        : 'Ostavite obavijest i javit ćemo vam čim knjiga ponovno bude dostupna.';
    $shippingLabel = null;
    $shippingValue = null;

    if ($prod->kat) {
        $shippingLabel = 'Regionalni naslov';
        $shippingValue = 'Dostava za ovaj naslov traje oko 20 dana.';
    } elseif (! empty($prod->delivery_24h)) {
        $shippingLabel = 'Brza dostava';
        $shippingValue = 'Isporuka u roku 24 sata.';
    } elseif ($prod->shipping_time) {
        $shippingLabel = 'Rok dostave';
        $shippingValue = $prod->shipping_time . '.';
    }

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

        .review-promo-banner {
            position: relative;
            background: linear-gradient(135deg, rgba(229, 0, 119, 0.05), rgba(255, 255, 255, 0.98));
            border: 1px solid rgba(229, 0, 119, 0.12);
            border-radius: 1rem;
            box-shadow: 0 18px 40px rgba(31, 41, 55, 0.08);
            overflow: hidden;
        }

        .review-promo-banner::before {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0;
            width: 4px;
            background: linear-gradient(180deg, #e50077 0%, #ff6cae 100%);
        }

        .review-promo-banner__body {
            padding: 1.5rem 1.75rem 1.5rem 1.9rem;
        }

        .review-promo-banner__copy {
            flex: 1 1 auto;
            min-width: 0;
        }

        .review-promo-banner__copy h3 {
            color: #2f3447;
        }

        .review-promo-banner__copy p {
            color: #667085;
            max-width: 44rem;
        }

        .review-promo-banner__meta {
            color: #3c4257;
            font-size: 1rem;
            line-height: 1.6;
            max-width: 42rem;
        }

        .review-promo-banner__kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0.75rem;
            padding: 0.4rem 0.85rem;
            border: 1px solid rgba(229, 0, 119, 0.16);
            border-radius: 999px;
            color: #e50077;
            background: rgba(255, 255, 255, 0.88);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .review-promo-banner__actions {
            flex: 0 0 auto;
        }

        .review-promo-banner__pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.35rem;
            padding: 0.55rem 1rem;
            border: 1px solid rgba(229, 0, 119, 0.18);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.94);
            color: #e50077;
            font-size: 0.92rem;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
        }

        .review-promo-banner__cta {
            min-width: 240px;
            min-height: 2.75rem;
            padding: 0.8rem 1.3rem;
            border-radius: 0.75rem;
            box-shadow: 0 16px 28px rgba(229, 0, 119, 0.24);
            font-weight: 600;
        }

        .product-purchase-summary {
            position: relative;
        }

        .product-purchase-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .product-purchase-badge {
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .product-purchase-title {
            margin-bottom: 0.9rem;
            color: #2f3447;
            line-height: 1.15;
        }

        .product-review-link span {
            color: #667085;
        }

        .product-price-stack {
            margin-bottom: 1.35rem;
        }

        .product-price-stack__primary {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 0.5rem 0.85rem;
        }

        .product-price-stack__current {
            font-size: clamp(1.75rem, 2.2vw, 2rem);
            font-weight: 700;
            line-height: 1;
            color: #2f3447;
        }

        .product-price-stack__original {
            color: #98a2b3;
            font-size: 0.92rem;
        }

        .product-price-stack__secondary {
            margin-top: 0.45rem;
            color: #667085;
            font-size: 0.95rem;
        }

        .product-price-stack__secondary s {
            color: #98a2b3;
        }

        .product-price-stack__legal {
            margin-top: 0.35rem;
            color: #98a2b3;
            font-size: 0.8rem;
        }

        .product-purchase-meta {
            display: grid;
            gap: 0.8rem;
            margin-bottom: 1.4rem;
        }

        .product-purchase-meta__item {
            display: flex;
            flex-direction: column;
            gap: 0.18rem;
            padding: 0.95rem 1rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: none;
        }

        .product-purchase-meta__item--available {
            border-color: rgba(16, 185, 129, 0.18);
            background: #ffffff;
        }

        .product-purchase-meta__item--unavailable {
            border-color: rgba(245, 158, 11, 0.22);
            background: #ffffff;
        }

        .product-purchase-meta__eyebrow {
            color: #667085;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .product-purchase-meta__value {
            color: #2f3447;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.4;
        }

        .product-purchase-meta__help {
            color: #667085;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .product-purchase-box {
            margin: 0 0 1.5rem;
            padding: 0.85rem;
            border: 1px solid rgba(229, 0, 119, 0.14);
            border-radius: 1.1rem;
            background: #ffffff;
            box-shadow: none;
        }

        .product-purchase-box--unavailable {
            border-color: rgba(148, 163, 184, 0.2);
            background: #ffffff;
        }

        .product-purchase-box__header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem 0.85rem;
            margin-bottom: 0.65rem;
        }

        .product-purchase-box__kicker {
            margin: 0 0 0.15rem;
            color: #e50077;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .product-purchase-box__title {
            margin: 0;
            color: #2f3447;
            font-size: 1rem;
            line-height: 1.25;
        }

        .product-purchase-box__support {
            margin: 0;
            color: #667085;
            font-size: 0.8rem;
            line-height: 1.35;
            max-width: 15rem;
        }

        @media (max-width: 991.98px) {
            .review-promo-banner__body {
                padding: 1.25rem 1.25rem 1.25rem 1.45rem;
            }

            .review-promo-banner__actions {
                width: 100%;
            }

            .review-promo-banner__cta {
                width: 100%;
            }

            .product-purchase-box {
                padding: 0.8rem;
            }

            .product-purchase-box__support {
                max-width: none;
            }
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
            <div class="h-100 bg-light shadow rounded-3 py-5 px-4 px-sm-5 product-purchase-summary">
                @if ($prod->quantity < 1 || $prod->main_price > $prod->main_special)
                    <div class="product-purchase-badges">
                        @if ($prod->quantity < 1)
                            <span class="badge bg-warning product-purchase-badge">Rasprodano</span>
                        @endif

                        @if ($prod->main_price > $prod->main_special)
                            <span class="badge bg-primary product-purchase-badge">-{{ number_format(floatval(\App\Helpers\Helper::calculateDiscount($prod->price, $prod->special())), 0) }}%</span>
                        @endif
                    </div>
                @endif

                <h1 class="h3 product-purchase-title">{{ $prod->name }}</h1>

        <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <a id="openReview" href="#reviews" class="d-inline-flex align-items-center text-decoration-none product-review-link">
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

       <div class="product-price-stack">
           <div class="product-price-stack__primary">
           @if ($prod->main_price > $prod->main_special)
               <span class="product-price-stack__current">{{ $prod->main_special_text }}</span>
               <span class="product-price-stack__original"><s>*{{ $prod->main_price_text }}</s></span>

           @else
               <span class="product-price-stack__current">{{ $prod->main_price_text }}</span>
           @endif
           </div>

   @if($prod->secondary_price_text)
       <div class="product-price-stack__secondary">
           @if ($prod->main_price > $prod->main_special)
               <span>{{ $prod->secondary_special_text }}</span>
               <span><s>*{{ $prod->secondary_price_text }}</s></span>
           @else
               <span>{{ $prod->secondary_price_text }}</span>
           @endif
       </div>
   @endif
   @if ($prod->main_price > $prod->main_special)
       <div class="product-price-stack__legal">
           <span>*Najniža cijena u zadnjih 30 dana.</span>
       </div>
   @endif
       </div>

            @if ($prod->quantity > 0)
                <div class="product-purchase-box">
                    <add-to-cart-btn id="{{ $prod->id }}" available="{{ $prod->quantity }}" track-stock="{{ $isTrackedQuantity ? 'true' : 'false' }}" allow-gift-wrap="{{ $giftWrapAllowed ? 'true' : 'false' }}"></add-to-cart-btn>
                </div>
            @else
                <div class="product-purchase-box product-purchase-box--unavailable">
                    <div class="product-purchase-box__header">
                        <div>
                            <p class="product-purchase-box__kicker">Dostupnost</p>
                            <h2 class="product-purchase-box__title">Pošaljite si obavijest čim knjiga ponovno stigne</h2>
                        </div>
                        <p class="product-purchase-box__support">Čim naslov opet bude dostupan, javit ćemo vam da ga možete naručiti bez dodatnog traženja.</p>
                    </div>
                    <div class="cart mb-0 mt-0 d-flex align-items-center">
                        <a class="btn btn-primary d-block w-100" href="#wishlist-modal" data-bs-toggle="modal"><i class="ci-bell"></i> Obavijesti me o dostupnosti</a>
                    </div>
                </div>
            @endif

            @if ($shippingValue)
                <div class="product-purchase-meta">
                    <div class="product-purchase-meta__item">
                        <span class="product-purchase-meta__eyebrow">{{ $shippingLabel }}</span>
                        <strong class="product-purchase-meta__value">{{ $shippingValue }}</strong>
                        <span class="product-purchase-meta__help">Besplatna dostava za narudžbe iznad {{ config('settings.free_shipping') }} €.</span>
                    </div>
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
                           <p class="fs-sm text-muted mt-2 mb-1">Vaše iskustvo pomaže drugim kupcima, a nama puno znači. Hvala vam što odvajate vrijeme za preporuku.</p>
                           <p class="fs-sm text-muted mb-0">Registrirani kupci za svaki odobreni komentar dobivaju {{ $reviewRewardPoints }} loyalty bodova, do najviše {{ $reviewMonthlyLimit }} komentara mjesečno.</p>
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

               <div class="review-promo-banner mb-4">
                   <div class="review-promo-banner__body d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                       <div class="review-promo-banner__copy">
                           <span class="review-promo-banner__kicker">Loyalty klub</span>
                           <h3 class="h4 mb-1">Podijelite dojam o knjizi</h3>
                           <p class="review-promo-banner__meta mb-0">Ostavite ocjenu i komentar te pomozite drugim kupcima pri odabiru.</p>
                       </div>

                       <div class="review-promo-banner__actions d-flex flex-wrap align-items-center justify-content-xl-end gap-2">
                           <span class="review-promo-banner__pill">{{ $reviewRewardPoints }} loyalty bodova</span>
                           <span class="review-promo-banner__pill">Do {{ $reviewMonthlyLimit }} mjesečno</span>

                           @if ($showReviewPromoButton)
                               <button
                                   id="open-review-form"
                                   class="review-promo-banner__cta btn btn-primary btn-shadow"
                                   type="button"
                                   data-open-text="Napišite prvi komentar"
                               >
                                   Napišite prvi komentar
                               </button>
                           @endif
                       </div>
                   </div>
               </div>

               @if($reviewsCount)
                   <div class="row py-4">
                       <div class="col-md-7">
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
                       </div>

                       <div class="col-md-5 mt-4 mt-md-0">
                           <div id="review-form" class="bg-secondary py-grid-gutter px-grid-gutter rounded-3">
                               <h3 class="h4 pb-2">Napišite komentar</h3>

                               @if (session('review_submitted') && session('success'))
                                   <div class="alert alert-success" role="alert">
                                       {{ session('success') }}
                                   </div>
                               @endif

                               @if (session('review_submitted') && session('error'))
                                   <div class="alert alert-danger" role="alert">
                                       {{ session('error') }}
                                   </div>
                               @endif

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
               @else
                   <div class="py-2">
                       <p class="mb-0">Trenutno nema komentara za ovaj naslov.</p>
                   </div>

                   <div class="row pt-4">
                       <div class="col-lg-8 col-xl-7">
                           <div id="review-form" class="bg-secondary py-grid-gutter px-grid-gutter rounded-3" @if (! $shouldOpenReviewForm) style="display: none;" @endif>
                               <h3 class="h4 pb-2">Budite prvi i napišite komentar</h3>

                           @if (session('review_submitted') && session('success'))
                               <div class="alert alert-success" role="alert">
                                   {{ session('success') }}
                               </div>
                           @endif

                           @if (session('review_submitted') && session('error'))
                               <div class="alert alert-danger" role="alert">
                                   {{ session('error') }}
                               </div>
                           @endif

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
               @endif
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

    function openReviewForm(scrollToForm = false) {
        const reviewFormPanel = $('#review-form');
        const reviewFormButton = $('#open-review-form');

        if (!reviewFormPanel.length) {
            return;
        }

        if (!reviewFormPanel.is(':visible')) {
            reviewFormPanel.stop(true, true).slideDown(250);
        }

        if (reviewFormButton.length) {
            reviewFormButton.hide();
        }

        if (scrollToForm) {
            setTimeout(() => {
                const formElement = document.getElementById('review-form');

                if (formElement) {
                    formElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 260);
        }
    }

    $('#openReview').on('click', function(e) {
        e.preventDefault();
        scrollToReviewsSection();
    });

    $('#open-review-form').on('click', function() {
        openReviewForm(true);
    });

    @if ($hasReviewErrors || session('review_submitted'))
        $(function() {
            scrollToReviewsSection();
            openReviewForm(false);
        });
    @endif

    @if (! $reviewsCount)
        $(function() {
            if (window.location.hash === '#review-form') {
                scrollToReviewsSection();
                openReviewForm(true);
            }
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
