@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand('Moje narudzbe'))
@section('description', \App\Models\Seo::description(null, 'Pregled prethodnih narudzbi na korisnickom racunu ' . \App\Models\Seo::brand() . '.'))

@section('content')

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
	                                <th>Dostava</th>
	                                <th>Ukupno</th>
	                                <th>Narudžba</th>
	                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($orders as $order)
                                <tr>
	                                    <td class="py-3"><a class="nav-link-style fw-medium fs-sm" href="{{ route('moje-narudzbe.show', ['order' => $order->id]) }}">{{ $order->id }}</a></td>
	                                    <td class="py-3">{{ \Illuminate\Support\Carbon::make($order->created_at)->format('d.m.Y') }}</td>
	                                    <td class="py-3"><span class="badge bg-info account-status-badge m-0">{{ optional($order->status)->title ?: 'Nepoznat status' }}</span></td>
	                                    <td class="py-3">
	                                        @if($order->shipping_tracking_status)
	                                            <span class="d-block fs-sm">{{ $order->shipping_tracking_status }}</span>
	                                            @if($order->shipping_tracking_url)
	                                                <a class="fs-sm" href="{{ $order->shipping_tracking_url }}" target="_blank" rel="noopener">Praćenje pošiljke</a>
	                                            @endif
	                                        @else
	                                            <span class="text-muted fs-sm">Nije dostupno</span>
	                                        @endif
	                                    </td>
	                                    <td class="py-3 fw-medium">{{ number_format($order->total, 2, ',', '.') }} €</td>
	                                    <td class="py-3"><a class="btn btn-sm btn-outline-primary" href="{{ route('moje-narudzbe.show', ['order' => $order->id]) }}">Pregled</a></td>
	                                </tr>
                            @empty
                                <tr>
	                                    <td class="p-0 border-0" colspan="6">
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
