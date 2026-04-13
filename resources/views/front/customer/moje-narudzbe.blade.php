@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand('Moje narudzbe'))
@section('description', \App\Models\Seo::description(null, 'Pregled prethodnih narudzbi na korisnickom racunu ' . \App\Models\Seo::brand() . '.'))
@php
    $purchaseRecommendationCarouselOptions = [
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
        .purchase-recommendations-carousel .tns-ovh,
        .purchase-recommendations-carousel .tns-item,
        .purchase-recommendations-carousel .tns-carousel-inner {
            touch-action: pan-y pinch-zoom;
        }
    </style>
@endpush

@section('content')

    <!-- Order Details Modal-->
    @foreach ($orders as $order)
        <div class="modal fade" id="order-details{{ $order->id }}">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Broj narudžbe - {{ $order->id }}</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pb-0">
                        @foreach ($order->products as $product)
                            @php
                                $productUrl = optional($product->real)->url;
                                $productImage = optional($product->product)->image;
                            @endphp

                            <div class="d-sm-flex justify-content-between mb-4 pb-3 pb-sm-2 border-bottom">
                                <div class="d-sm-flex text-center text-sm-start">
                                    @if ($productUrl)
                                        <a class="d-inline-block flex-shrink-0 mx-auto" href="{{ url($productUrl) }}" style="width: 10rem;">
                                            <img src="{{ $productImage ? asset($productImage) : asset('media/avatars/avatar0.jpg') }}" alt="{{ $product->name }}">
                                        </a>
                                    @else
                                        <span class="d-inline-block flex-shrink-0 mx-auto" style="width: 10rem;">
                                            <img src="{{ $productImage ? asset($productImage) : asset('media/avatars/avatar0.jpg') }}" alt="{{ $product->name }}">
                                        </span>
                                    @endif
                                    <div class="ps-sm-4 pt-2">
                                        <h3 class="product-title fs-base mb-2">
                                            @if ($productUrl)
                                                <a href="{{ url($productUrl) }}">{{ $product->name }}</a>
                                            @else
                                                <span>{{ $product->name }}</span>
                                            @endif
                                        </h3>
                                        @if ( ! $productUrl)
                                            <div class="fs-sm text-muted">Proizvod više nije dostupan u katalogu.</div>
                                        @endif
                                        <div class="fs-lg text-accent pt-2">{{ number_format($product->price, 2, ',', '.') }} €</div>
                                    </div>
                                </div>
                                <div class="pt-2 ps-sm-3 mx-auto mx-sm-0 text-center">
                                    <div class="text-muted mb-2 fs-sm">Količina:</div>{{ $product->quantity }}
                                </div>
                                <div class="pt-2 ps-sm-3 mx-auto mx-sm-0 text-center">
                                    <div class="text-muted mb-2 fs-sm">Ukupno</div>{{ number_format($product->total, 2, ',', '.') }} €
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <!-- Footer-->
                    <div class="modal-footer flex-wrap justify-content-between bg-secondary fs-md">
                        @foreach ($order->totals as $total)
                            <div class="px-2 py-1"><span class="text-muted">{{ $total->title }}:&nbsp;</span><span>{{ number_format($total->value, 2, ',', '.') }} €</span></div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @include('front.customer.layouts.header')

    <section class="pb-5 mb-2 mb-md-4">
        <div class="row">
        @include('front.customer.layouts.sidebar')

            <!-- Content  -->
            <section class="col-lg-8">
                <!-- Toolbar-->
                <div class="d-none d-lg-flex justify-content-between align-items-center pt-lg-3 pb-4 pb-lg-5 mb-lg-3">
                    <h6 class="fs-base text-primary mb-0">Pogledajte povijest svoji narudžbi:</h6><a class="btn btn-primary btn-sm" href="{{ route('logout') }}"><i class="ci-sign-out me-2"></i>Odjava</a>
                </div>
                <!-- Orders list-->
                <div class="table-responsive fs-md mb-4">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Broj narudžbe #</th>
                            <th>Datum</th>
                            <th>Status</th>
                            <th>Ukupno</th>
                            <th>Narudžba</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="py-3"><a class="nav-link-style fw-medium fs-sm" href="#order-details{{ $order->id }}" data-bs-toggle="modal">{{ $order->id }}</a></td>
                                <td class="py-3">{{ \Illuminate\Support\Carbon::make($order->created_at)->format('d.m.Y') }}</td>
                                <td class="py-3"><span class="badge bg-info m-0">{{ optional($order->status)->title ?: 'Nepoznat status' }}</span></td>
                                <td class="py-3">{{ number_format($order->total, 2, ',', '.') }} €</td>
                                <td class="py-3"><a class="badge bg-primary text-white m-0 " href="#order-details{{ $order->id }}" data-bs-toggle="modal">Pregled</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center font-size-sm" colspan="4">
                                    <label>Trenutno nemate narudžbi...</label>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $orders->links() }}

                @if(isset($purchaseRecommendations) && $purchaseRecommendations->count())
                    <section class="mt-4 pt-2">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                            <div>
                                <h2 class="h4 mb-1">S obzirom na vaše prethodne kupnje, preporučujemo</h2>
                                <p class="text-muted mb-0">Odabrali smo slične naslove koje bi vas mogli zanimati na temelju knjiga koje ste već kupovali.</p>
                            </div>
                        </div>

                        <div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled pt-2 purchase-recommendations-carousel">
                            <div class="tns-carousel-inner" data-carousel-options='@json($purchaseRecommendationCarouselOptions)'>
                                @foreach ($purchaseRecommendations as $product)
                                    <div>
                                        @include('front.catalog.category.product', ['product' => $product])
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

            </section>
        </div>
    </section>

@endsection
