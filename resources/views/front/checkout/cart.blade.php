@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand('Kosarica'))
@section('description', \App\Models\Seo::description(null, 'Pregled artikala u kosarici na ' . \App\Models\Seo::brand() . '.'))
@php
    $cartRecommendationCarouselOptions = [
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
            576 => ['items' => 3],
            768 => ['items' => 3],
            1200 => ['items' => 4],
            1400 => ['items' => 5],
        ],
    ];
    $bookmarkersUrl = route('catalog.route', ['group' => 'kategorija-proizvoda', 'cat' => 'bookmarkeri']);
@endphp

@push('css_after')
    <style>
        .cart-recommendations-carousel,
        .cart-bookmarkers-carousel {
            position: relative;
        }

        .cart-recommendations-carousel .tns-ovh,
        .cart-recommendations-carousel .tns-item,
        .cart-bookmarkers-carousel .tns-ovh,
        .cart-bookmarkers-carousel .tns-item,
        .cart-bookmarkers-carousel .tns-carousel-inner,
        .cart-recommendations-carousel .tns-carousel-inner {
            touch-action: pan-y pinch-zoom;
        }

        .cart-shelf-section {
            scroll-margin-top: 7rem;
        }

        .cart-shelf-section + .cart-shelf-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(226, 232, 240, 0.9);
        }

        .cart-shelf-header__link {
            flex-shrink: 0;
        }
    </style>
@endpush

@if (isset($gdl))
    @section('google_data_layer')
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({
                'event': 'view_cart',
                'ecommerce': {'items': <?php echo json_encode($gdl); ?>}
            });
        </script>
    @endsection
@endif

@section('content')


    <!-- Page title + breadcrumb-->
    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            <li class="breadcrumb-item text-nowrap active" aria-current="page">Košarica</li>
        </ol>
    </nav>
    <!-- Content-->
    <!-- Sorting-->
    <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">
        <h1 class="h2 mb-3 mb-md-0 me-3">Košarica</h1>

    </section>

    @include('front.layouts.partials.session')

<!-- Page Title-->




    <div class=" pb-5 mb-2 mb-md-4">
    <div class="row">

        <section class="col-lg-8">
            <div class="steps steps-dark pt-2 pb-3 mb-2">
                <a class="step-item current active" href="{{ route('kosarica') }}">
                    <div class="step-progress"><span class="step-count">1</span></div>
                    <div class="step-label"><i class="ci-cart"></i>Košarica</div>
                </a>
                <a class="step-item" href="{{ route('naplata', ['step' => 'podaci']) }}">
                    <div class="step-progress"><span class="step-count">2</span></div>
                    <div class="step-label"><i class="ci-user-circle"></i>Podaci</div>
                </a>
                <a class="step-item" href="{{ route('naplata', ['step' => 'dostava']) }}">
                    <div class="step-progress"><span class="step-count">3</span></div>
                    <div class="step-label"><i class="ci-package"></i>Dostava</div>
                </a>
                <a class="step-item" href="{{ route('naplata', ['step' => 'placanje']) }}">
                    <div class="step-progress"><span class="step-count">4</span></div>
                    <div class="step-label"><i class="ci-card"></i>Plaćanje</div>
                </a>
                <a class="step-item" href="{{ route('pregled') }}">
                    <div class="step-progress"><span class="step-count">5</span></div>
                    <div class="step-label"><i class="ci-eye"></i>Pregledaj</div>
                </a>
                <a class="step-item" href="javascript:void(0);">
                    <div class="step-progress"><span class="step-count">6</span></div>
                    <div class="step-label"><i class="ci-check-circle"></i>Uspješno</div>
                </a>
            </div>
            <div class="card px-3">
            <cart-view continueurl="{{ \Illuminate\Support\Facades\URL::previous() }}" checkouturl="{{ route('naplata') }}" freeship="{{ config('settings.free_shipping') }}"></cart-view>
            </div>

        </section>
        <!-- Sidebar-->
        <aside class="col-lg-4 pt-4 pt-lg-0 ps-xl-5">

            <cart-view-aside
                route="kosarica"
                continueurl="{{ \Illuminate\Support\Facades\URL::previous() }}"
                checkouturl="{{ route('naplata') }}"
                bookmarkers-target="cart-bookmarkers"
                :show-bookmarker-promo='@json(isset($cartBookmarkers) && $cartBookmarkers->count() > 0)'
            ></cart-view-aside>
        </aside>
    </div>

    @if(isset($cartRecommendations) && $cartRecommendations->count())
        <section class="mt-4 pt-2 cart-shelf-section">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="h4 mb-1">S obzirom na vašu košaricu, preporučujemo</h2>
                    <p class="text-muted mb-0">Odabrali smo slične naslove od 10 do 15 € koje kupci često dodaju prije završetka kupnje.</p>
                </div>
            </div>

            <div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled pt-2 cart-recommendations-carousel">
                <div class="tns-carousel-inner" data-carousel-options='@json($cartRecommendationCarouselOptions)'>
                    @foreach ($cartRecommendations as $product)
                        <div>
                            @include('front.catalog.category.product', ['product' => $product])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if(isset($cartBookmarkers) && $cartBookmarkers->count())
        <section id="cart-bookmarkers" class="mt-4 pt-2 cart-shelf-section">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="h4 mb-1">Dodajte bookmarker uz narudžbu</h2>
                    <p class="text-muted mb-0">Nasumično smo izdvojili 10 bookmarkera koji super sjednu kao mali dodatak uz knjigu.</p>
                </div>
                <a class="btn btn-outline-primary btn-sm cart-shelf-header__link" href="{{ $bookmarkersUrl }}">Pogledajte sve</a>
            </div>

            <div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled pt-2 cart-bookmarkers-carousel">
                <div class="tns-carousel-inner" data-carousel-options='@json($cartRecommendationCarouselOptions)'>
                    @foreach ($cartBookmarkers as $product)
                        <div>
                            @include('front.catalog.category.product', ['product' => $product])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

</div>

@endsection
