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
            480 => ['items' => 2, 'controls' => true, 'nav' => true],
            720 => ['items' => 3],
            1140 => ['items' => 4],
        ],
    ];
@endphp

@push('css_after')
    <style>
        .cart-recommendations-carousel .tns-ovh,
        .cart-recommendations-carousel .tns-item,
        .cart-recommendations-carousel .tns-carousel-inner {
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

            @if(isset($cartRecommendations) && $cartRecommendations->count())
                <section class="mt-4 pt-2">
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

        </section>
        <!-- Sidebar-->
        <aside class="col-lg-4 pt-4 pt-lg-0 ps-xl-5">

            <cart-view-aside route="kosarica" continueurl="{{ \Illuminate\Support\Facades\URL::previous() }}" checkouturl="{{ route('naplata') }}"></cart-view-aside>
        </aside>
    </div>

</div>

@endsection
