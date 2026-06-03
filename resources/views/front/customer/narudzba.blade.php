@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand('Narudžba #' . $order->id))
@section('description', \App\Models\Seo::description(null, 'Pregled detalja narudžbe na korisničkom računu ' . \App\Models\Seo::brand() . '.'))

@push('css_after')
    <style>
        .account-order-meta-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1.15rem;
            margin-bottom: 1.25rem;
            padding: 1.15rem 1.25rem;
            border: 1px solid #edf0f5;
            border-radius: .5rem;
            background: #fbfcff;
        }

        .account-order-meta-strip--tracking {
            grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
            align-items: center;
        }

        .account-order-meta-label {
            color: #6c7485;
            font-size: .82rem;
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: .35rem;
        }

        .account-order-meta-value {
            color: #373f50;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .account-order-meta-note {
            color: #7d879c;
            font-size: .85rem;
            line-height: 1.35;
            margin-top: .15rem;
        }

        .account-order-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: flex-end;
        }

        .account-order-products-table th,
        .account-order-products-table td {
            vertical-align: middle;
        }

        .account-order-product-cell {
            min-width: 360px;
        }

        .account-order-product-info {
            align-items: center;
            display: grid;
            gap: 1rem;
            grid-template-columns: 4.75rem minmax(0, 1fr);
        }

        .account-order-product-thumb {
            align-items: center;
            background: #fbfcff;
            border: 1px solid #edf0f5;
            border-radius: .4rem;
            display: flex;
            height: 6.1rem;
            justify-content: center;
            overflow: hidden;
            padding: .35rem;
            width: 4.75rem;
        }

        .account-order-product-thumb img {
            display: block;
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }

        .account-order-product-title {
            color: #373f50;
            display: inline-block;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .account-order-totals-wrap {
            display: flex;
            justify-content: flex-end;
            margin-top: 1.4rem;
            padding-top: 1.25rem;
            border-top: 1px solid #edf0f5;
        }

        .account-order-totals {
            width: min(100%, 520px);
        }

        .account-order-totals-title {
            color: #373f50;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: .65rem;
        }

        .account-order-total-row {
            align-items: flex-start;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            padding: .55rem 0;
            border-bottom: 1px solid #edf0f5;
        }

        .account-order-total-row:last-child {
            border-bottom: 0;
        }

        .account-order-total-row.is-grand {
            align-items: center;
            margin-top: .3rem;
            padding-top: .85rem;
            border-top: 2px solid #e2e7ef;
            color: #373f50;
            font-size: 1.08rem;
        }

        .account-order-total-label {
            color: #5b6680;
            line-height: 1.35;
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .account-order-total-value {
            color: #373f50;
            flex: 0 0 auto;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }

        .account-order-total-row.is-grand .account-order-total-label,
        .account-order-total-row.is-grand .account-order-total-value {
            color: #232735;
            font-weight: 800;
        }

        @media (max-width: 1199.98px) {
            .account-order-meta-strip,
            .account-order-meta-strip--tracking {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .account-order-actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 575.98px) {
            .account-order-meta-strip,
            .account-order-meta-strip--tracking {
                grid-template-columns: 1fr;
            }

            .account-order-product-cell {
                min-width: 300px;
            }

            .account-order-product-info {
                grid-template-columns: 4rem minmax(0, 1fr);
            }

            .account-order-product-thumb {
                height: 5.25rem;
                width: 4rem;
            }

            .account-order-totals {
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')

    @php
        $orderDate = \Illuminate\Support\Carbon::make($order->created_at)->format('d.m.Y');
        $trackingDate = $order->shipping_tracking_updated_at ? \Illuminate\Support\Carbon::make($order->shipping_tracking_updated_at)->format('d.m.Y H:i') : null;
        $statusTitle = optional($order->status)->title ?: 'Nepoznat status';
        $grandTotal = $order->totals->firstWhere('code', 'total') ?: $order->totals->last();
    @endphp

    @include('front.customer.layouts.header')

    <section class="account-page pb-5 mb-2 mb-md-4">
        <div class="row account-layout g-4">
            @include('front.customer.layouts.sidebar')

            <section class="col-lg-8 col-xl-9 account-content-column">
                <div class="account-content-card">
                    <nav class="mb-4" aria-label="breadcrumb">
                        <ol class="breadcrumb flex-lg-nowrap mb-0">
                            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('moj-racun') }}">Moj račun</a></li>
                            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('moje-narudzbe') }}">Narudžbe</a></li>
                            <li class="breadcrumb-item text-nowrap active" aria-current="page">Narudžba #{{ $order->id }}</li>
                        </ol>
                    </nav>

                    <div class="account-card-header">
                        <div class="account-card-titlewrap">
                            <span class="account-card-icon"><i class="ci-bag"></i></span>
                            <div>
                                <h2 class="account-card-title">Narudžba #{{ $order->id }}</h2>
                                <p class="account-card-subtitle">Detalji narudžbe zaprimljene {{ $orderDate }}.</p>
                            </div>
                        </div>
                        <a class="btn btn-outline-primary btn-sm account-logout-button" href="{{ route('moje-narudzbe') }}">
                            <i class="ci-arrow-left me-2"></i>Povratak na sve narudžbe
                        </a>
                    </div>

                    @include('front.layouts.partials.session')

                    <div class="account-order-meta-strip">
                        <div>
                            <div class="account-order-meta-label">Status</div>
                            <span class="badge bg-info account-status-badge m-0">{{ $statusTitle }}</span>
                        </div>
                        <div>
                            <div class="account-order-meta-label">Datum</div>
                            <div class="account-order-meta-value">{{ $orderDate }}</div>
                        </div>
                        <div>
                            <div class="account-order-meta-label">Plaćanje</div>
                            <div class="account-order-meta-value">{{ $order->payment_method ?: 'Nije upisano' }}</div>
                        </div>
                        <div>
                            <div class="account-order-meta-label">Ukupno</div>
                            <div class="account-order-meta-value">{{ number_format($order->total, 2, ',', '.') }} €</div>
                        </div>
                    </div>

                    <div class="account-order-meta-strip account-order-meta-strip--tracking">
                        <div>
                            <div class="account-order-meta-label">Dostava</div>
                            <div class="account-order-meta-value">{{ $order->shipping_method ?: 'Nije upisano' }}</div>
                            @if($trackingCarrier)
                                <div class="account-order-meta-note">{{ $trackingCarrierLabel }}</div>
                            @endif
                        </div>
                        <div>
                            <div class="account-order-meta-label">Tracking</div>
                            @if($order->shipping_tracking_status)
                                <div class="account-order-meta-value">{{ $order->shipping_tracking_status }}</div>
                                @if($trackingDate)
                                    <div class="account-order-meta-note">Osvježeno {{ $trackingDate }}</div>
                                @endif
                            @else
                                <div class="account-order-meta-value text-muted">Nije dostupno</div>
                            @endif
                        </div>
                        <div>
                            <div class="account-order-meta-label">Broj pošiljke</div>
                            @if($order->tracking_code || $order->shipping_parcel_id)
                                <div class="account-order-meta-value">{{ $order->tracking_code ?: $order->shipping_parcel_id }}</div>
                            @else
                                <div class="account-order-meta-value text-muted">Nije upisan</div>
                            @endif
                        </div>
                        <div class="account-order-actions">
                            @if($order->shipping_tracking_url)
                                <a class="btn btn-sm btn-outline-primary" href="{{ $order->shipping_tracking_url }}" target="_blank" rel="noopener">
                                    Praćenje pošiljke
                                </a>
                            @endif
                            @if($canRefreshTracking)
                                <form action="{{ route('moje-narudzbe.tracking.refresh', ['order' => $order->id]) }}" method="POST" class="mb-0">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="ci-reload me-2"></i>Osvježi status
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="account-section">
                        <h3 class="account-section-title"><i class="ci-cart"></i>Artikli</h3>

                        @if($order->products->isNotEmpty())
                            <div class="table-responsive fs-md account-table-shell">
                                <table class="table table-hover account-order-products-table mb-0">
                                    <thead>
                                    <tr>
                                        <th>Artikl</th>
                                        <th class="text-center">Količina</th>
                                        <th class="text-end">Cijena</th>
                                        <th class="text-end">Ukupno</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($order->products as $product)
                                        @php
                                            $productUrl = optional($product->real)->url;
                                            $productImage = optional($product->product)->image;
                                            $productImageUrl = $productImage ? asset($productImage) : asset('media/avatars/avatar0.jpg');
                                        @endphp

                                        <tr>
                                            <td class="py-3 account-order-product-cell">
                                                <div class="account-order-product-info">
                                                    @if ($productUrl)
                                                        <a class="account-order-product-thumb" href="{{ url($productUrl) }}" aria-label="{{ $product->name }}">
                                                            <img src="{{ $productImageUrl }}" alt="">
                                                        </a>
                                                    @else
                                                        <span class="account-order-product-thumb" aria-hidden="true">
                                                            <img src="{{ $productImageUrl }}" alt="">
                                                        </span>
                                                    @endif

                                                    <div>
                                                        <h4 class="mb-1">
                                                            @if ($productUrl)
                                                                <a class="account-order-product-title" href="{{ url($productUrl) }}">{{ $product->name }}</a>
                                                            @else
                                                                <span class="account-order-product-title">{{ $product->name }}</span>
                                                            @endif
                                                        </h4>
                                                        @if ( ! $productUrl)
                                                            <div class="fs-sm text-muted">Proizvod više nije dostupan u katalogu.</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3 text-center">{{ $product->quantity }}</td>
                                            <td class="py-3 text-end">{{ number_format($product->price, 2, ',', '.') }} €</td>
                                            <td class="py-3 text-end fw-medium">{{ number_format($product->total, 2, ',', '.') }} €</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="account-empty-state">
                                <div>Ova narudžba nema upisanih artikala.</div>
                            </div>
                        @endif
                    </div>

                    <div class="account-order-totals-wrap">
                        <div class="account-order-totals">
                            <h3 class="account-order-totals-title">Pregled plaćanja</h3>
                            @forelse ($order->totals as $total)
                                @php($isGrandTotal = $grandTotal && (int) $grandTotal->id === (int) $total->id)
                                <div class="account-order-total-row {{ $isGrandTotal ? 'is-grand' : '' }}">
                                    <span class="account-order-total-label">{{ $total->title }}</span>
                                    <span class="account-order-total-value">{{ number_format($total->value, 2, ',', '.') }} €</span>
                                </div>
                            @empty
                                <div class="account-order-total-row is-grand">
                                    <span class="account-order-total-label">Ukupno</span>
                                    <span class="account-order-total-value">{{ number_format($order->total, 2, ',', '.') }} €</span>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="account-form-actions d-flex flex-wrap justify-content-between gap-2">
                        <a class="btn btn-outline-primary" href="{{ route('moje-narudzbe') }}">
                            <i class="ci-arrow-left me-2"></i>Povratak na sve narudžbe
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </section>

@endsection
