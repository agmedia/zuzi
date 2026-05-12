@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand('Moje narudzbe'))
@section('description', \App\Models\Seo::description(null, 'Pregled prethodnih narudzbi na korisnickom racunu ' . \App\Models\Seo::brand() . '.'))

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

    <section class="account-page pb-5 mb-2 mb-md-4">
        <div class="row account-layout g-4">
            @include('front.customer.layouts.sidebar')

            <section class="col-lg-8 col-xl-9 account-content-column">
                <div class="account-content-card">
                    <div class="account-card-header">
                        <div class="account-card-titlewrap">
                            <span class="account-card-icon"><i class="ci-bag"></i></span>
                            <div>
                                <h2 class="account-card-title">Narudžbe</h2>
                                <p class="account-card-subtitle">Pregledajte status, iznos i detalje svih prethodnih narudžbi.</p>
                            </div>
                        </div>
                        <form action="{{ route('logout') }}" method="POST" class="mb-0">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm account-logout-button">
                                <i class="ci-sign-out me-2"></i>Odjava
                            </button>
                        </form>
                    </div>

                    <div class="table-responsive fs-md account-table-shell mb-4">
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
                                    <td class="py-3"><span class="badge bg-info account-status-badge m-0">{{ optional($order->status)->title ?: 'Nepoznat status' }}</span></td>
                                    <td class="py-3 fw-medium">{{ number_format($order->total, 2, ',', '.') }} €</td>
                                    <td class="py-3"><a class="btn btn-sm btn-outline-primary" href="#order-details{{ $order->id }}" data-bs-toggle="modal">Pregled</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="p-0 border-0" colspan="5">
                                        <div class="account-empty-state">
                                            <div>
                                                <i class="ci-bag d-block fs-3 mb-3 text-muted"></i>
                                                <div>Trenutno nemate narudžbi.</div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $orders->links() }}
                </div>
            </section>
        </div>
    </section>

@endsection
