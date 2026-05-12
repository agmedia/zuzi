@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand(__('front/cart.moj_korisnicki_racun')))
@section('description', \App\Models\Seo::description(null, 'Pregled loyalty bodova i povijesti korisnickog racuna u ' . \App\Models\Seo::brand() . '.'))
@section('content')



    @include('front.customer.layouts.header')

    <section class="account-page pb-5 mb-2 mb-md-4">
        <div class="row account-layout g-4">
            @include('front.customer.layouts.sidebar')

            <section class="col-lg-8 col-xl-9 account-content-column">
                <div class="account-content-card">
                    <div class="account-card-header">
                        <div class="account-card-titlewrap">
                            <span class="account-card-icon"><i class="ci-coins"></i></span>
                            <div>
                                <h2 class="account-card-title">Loyalty</h2>
                                <p class="account-card-subtitle">{{ __('front/cart.pogledajte_povijest_loyalty') }}.</p>
                            </div>
                        </div>
                        <form action="{{ route('logout') }}" method="POST" class="mb-0">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm account-logout-button">
                                <i class="ci-sign-out me-2"></i>{{ __('front/cart.odjava') }}
                            </button>
                        </form>
                    </div>

                    <div class="account-summary-strip">
                        <div>
                            <div class="text-muted fs-sm">{{ __('front/cart.loyalty_current_points') }}</div>
                            <div class="account-points-pill mt-2"><i class="ci-coins"></i>{{ $points }} bodova</div>
                        </div>
                    </div>

                    <div class="table-responsive fs-md account-table-shell mb-4">
                        <table class="table table-hover mb-0">
                            <thead>
                            <tr>
                                <th>{{ __('front/cart.loyalty_reference') }} #</th>
                                <th>{{ __('front/cart.loyalty_date') }}</th>
                                <th>{{ __('front/cart.loyalty_earned') }}</th>
                                <th>{{ __('front/cart.loyalty_used') }}</th>
                            </tr>
                            </thead>
                            <tbody>

                            @forelse ($loyalty as $row)
                                <tr>
                                    @if($row->reference == 'order')
                                        <td class="py-3">{{ __('front/cart.loyalty_ref_order') }} - {{ $row->reference_id }}<br><small class="text-muted">{{ $row->comment }}</small></td>

                                    @elseif($row->reference == 'admin')
                                        <td class="py-3">Admin</td>
                                    @elseif($row->reference == 'affiliate_referral')
                                        <td class="py-3">Affiliate kupac<br><small class="text-muted">{{ $row->comment }}</small></td>
                                    @elseif ($row->reference == 'product_review')
                                        @php
                                            $reviewProduct = $row->getReferenceModel()->first();
                                        @endphp
                                        <td class="py-3">
                                            {{ __('front/cart.loyalty_ref_review') }}
                                            @if($reviewProduct)
                                                - {{ $reviewProduct->name }}
                                            @else
                                                <br><small class="text-muted">Proizvod više nije dostupan.</small>
                                            @endif
                                        </td>
                                    @else
                                        <td class="py-3"></td>
                                    @endif
                                    <td class="py-3">{{ \Illuminate\Support\Carbon::make($row->created_at)->format('d.m.Y') }}</td>
                                    <td class="py-3 fw-medium text-success">+ {{ $row->earned }}</td>
                                    <td class="py-3 fw-medium">
                                        @if($row->spend == 100)
                                            - {{ __('front/cart.loyalty_100') }}
                                        @endif
                                        @if($row->spend == 200)
                                            - {{ __('front/cart.loyalty_200') }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="p-0 border-0" colspan="4">
                                        <div class="account-empty-state">
                                            <div>
                                                <i class="ci-coins d-block fs-3 mb-3 text-muted"></i>
                                                <div>{{ __('front/cart.loyalty_trenutno_nemate') }}</div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse


                            </tbody>
                        </table>
                    </div>

                    {{ $loyalty->links() }}
                </div>
            </section>
        </div>
    </section>

@endsection
