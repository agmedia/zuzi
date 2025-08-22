@extends('front.layouts.app')
@section ( 'title', __('front/cart.moj_korisnicki_racun') )
@section ( 'description', 'Rice Kakis Azijski Webshop - autentični Bubble Tea u četiri okusa, japanski Mochi , Nudle, Korejske grickalice i slatkiši, te veliki izbor umaka i začina.' )
@section('content')



    @include('front.customer.layouts.header')

    <div class="container pb-5 mb-2 mb-md-4">
        <div class="row">
        @include('front.customer.layouts.sidebar')

            <!-- Content  -->
            <section class="col-lg-8">
                <!-- Toolbar-->
                <div class="d-none d-lg-flex justify-content-between align-items-center pt-lg-1 pb-4 pb-lg-1 mb-lg-0">
                    <h6 class="fs-base text-primary mb-0">{{ __('front/cart.pogledajte_povijest_loyalty') }} :</h6><a class="btn btn-primary btn-sm" href="{{ route('logout') }}"><i class="ci-sign-out me-2"></i>{{ __('front/cart.odjava') }}</a>
                </div>

                <div class="d-none d-lg-flex align-items-start pt-lg-3 pb-1 pb-lg-1 mb-lg-2">
                    <p class="fs-sm text-primary mb-0"><strong>{{ __('front/cart.loyalty_current_points') }}</strong> : {{$points }}</p>
                </div>
                <!-- Orders list-->
                <div class="table-responsive fs-md mb-4">
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
                                    @if($row->target == 'order')
                                         <td class="py-3">{{ __('front/cart.loyalty_ref_order') }} - {{$row->reference_id }}</td>

                                    @elseif($row->target == 'admin')
                                            <td class="py-3">Admin </td>
                                    @elseif ($row->target == 'product_review')
                                        <td class="py-3">{{ __('front/cart.loyalty_ref_review') }}  - {{$row->reference()->first()->translation->name }} {{-- staviti naziv proizvoda preko product id --}}</td>
                                    @else
                                        <td class="py-3"></td>
                                    @endif
                                    <td class="py-3">{{ \Illuminate\Support\Carbon::make($row->created_at)->format('d.m.Y') }}</td>
                                    <td class="py-3"> + {{ $row->earned }} </td>
                                        <td class="py-3">   @if($row->spend == 100)   -  {{ __('front/cart.loyalty_100') }} @endif @if($row->spend == 200)  -  {{ __('front/cart.loyalty_200') }} @endif </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-center font-size-sm" colspan="4">
                                        <label>{{ __('front/cart.loyalty_trenutno_nemate') }}</label>
                                    </td>
                                </tr>
                            @endforelse




                        </tbody>
                    </table>
                </div>



            </section>
        </div>
    </div>

@endsection
