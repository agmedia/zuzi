@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand('Moj korisnicki racun'))
@section('description', \App\Models\Seo::description(null, 'Uredite podatke svojeg korisnickog racuna na ' . \App\Models\Seo::brand() . '.'))

@section('content')

    @include('front.customer.layouts.header')

    <section class="account-page pb-5 mb-2 mb-md-4">
        <div class="row account-layout g-4">
            @include('front.customer.layouts.sidebar')

            <section class="col-lg-8 col-xl-9 account-content-column">
                <div class="account-content-card">
                    <div class="account-card-header">
                        <div class="account-card-titlewrap">
                            <span class="account-card-icon"><i class="ci-user"></i></span>
                            <div>
                                <h2 class="account-card-title">Moji podaci</h2>
                                <p class="account-card-subtitle">Uredite kontakt, adresu dostave i podatke za račun.</p>
                            </div>
                        </div>
                        <form action="{{ route('logout') }}" method="POST" class="mb-0">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm account-logout-button">
                                <i class="ci-sign-out me-2"></i>Odjava
                            </button>
                        </form>
                    </div>

                    @include('front.layouts.partials.session')

                    <form action="{{ route('moj-racun.snimi', ['user' => $user]) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        {{ method_field('PATCH') }}

                        <div class="account-section">
                            <h2 class="account-section-title"><i class="ci-user"></i>Osnovni podaci</h2>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="checkout-fn">Ime</label>
                                        <input class="form-control @error('fname') is-invalid @enderror" id="checkout-fn" type="text" name="fname" value="{{ $user->details->fname }}">
                                        @error('fname') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="checkout-ln">Prezime</label>
                                        <input class="form-control @error('lname') is-invalid @enderror" id="checkout-ln" type="text" name="lname" value="{{ $user->details->lname }}">
                                        @error('lname') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="checkout-email">E-mail adresa</label>
                                        <input class="form-control @error('email') is-invalid @enderror" id="checkout-email" type="email" readonly name="email" value="{{ $user->email }}">
                                        @error('email') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="checkout-phone">Telefon</label>
                                        <input class="form-control" id="checkout-phone" type="text" name="phone" value="{{ $user->details->phone }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="account-section">
                            <h2 class="account-section-title"><i class="ci-location"></i>Adresa dostave</h2>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="checkout-address">Adresa</label>
                                        <input class="form-control @error('address') is-invalid @enderror" id="checkout-address" type="text" name="address" value="{{ $user->details->address }}">
                                        @error('address') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="checkout-city">Grad</label>
                                        <input class="form-control @error('city') is-invalid @enderror" id="checkout-city" type="text" name="city" value="{{ $user->details->city }}">
                                        @error('city') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="checkout-zip">Poštanski broj</label>
                                        <input class="form-control @error('zip') is-invalid @enderror" id="checkout-zip" type="text" name="zip" value="{{ $user->details->zip }}">
                                        @error('zip') <div id="val-username-error" class="invalid-feedback animated fadeIn">Ime je obvezno</div> @enderror
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3" wire:ignore>
                                        <label class="form-label" for="checkout-country">Država</label>
                                        <select class="form-select g @error('state') is-invalid @enderror" id="checkout-country" name="state">
                                            <option value=""></option>
                                            @foreach ($countries as $country)
                                                <option value="{{ $country['name'] }}" {{ $country['name'] == $user->details->state ? 'selected' : '' }}>{{ $country['name'] }}</option>
                                            @endforeach
                                        </select>
                                        @error('state') <div id="val-username-error" class="invalid-feedback animated fadeIn">Država je obvezna</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="account-section">
                            <h2 class="account-section-title"><i class="ci-briefcase"></i>Podaci za račun</h2>
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="checkout-company">Tvrtka</label>
                                        <input class="form-control" id="checkout-company" type="text" name="company" value="{{ $user->details->company }}">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="checkout-oib">OIB</label>
                                        <input class="form-control" id="checkout-oib" type="text" name="oib" value="{{ $user->details->oib }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="account-section mb-0">
                            <h2 class="account-section-title"><i class="ci-star"></i>Affiliate naziv</h2>
                            <div class="mb-3">
                                <label class="form-label" for="affiliate_name">Affiliate naziv</label>
                                <input class="form-control" id="affiliate_name" type="text" name="affiliate_name" value="{{ $user->details->affiliate_name }}">
                            </div>
                        </div>

                        <div class="account-form-actions d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4">Snimi promjene</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </section>


@endsection
