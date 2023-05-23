@extends('front.layouts.app')

@section('content')

    <!-- Page Title (Light)-->
    <div class=" bg-dark pt-4 pb-3" style="background-image: url({{ config('settings.images_domain') . 'media/img/zuzi-bck.svg' }});background-repeat: repeat-x;background-position-y: bottom;">
        <div class="container d-lg-flex justify-content-between py-2 py-lg-3">
            <div class="order-lg-2 mb-3 mb-lg-0 pt-lg-2">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-light flex-lg-nowrap justify-content-center justify-content-lg-start">
                        <li class="breadcrumb-item"><a class="text-nowrap" href="/"><i class="ci-home"></i>Naslovnica</a></li>
                        <li class="breadcrumb-item text-nowrap active" aria-current="page">Kontakt</li>
                    </ol>
                </nav>
            </div>
            <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
                <h1 class="h3 text-light mb-0">Kontaktirajte nas</h1>
            </div>
        </div>
    </div>

    <section class="spikesg" ></section>
    <!-- Contact detail cards-->
    <section class="container pt-grid-gutter">
        <div class="row">

            @include('front.layouts.partials.success-session')

            <div class="col-12 col-sm-6 mb-5">

                        <h3 class=" mb-2">Impressum</h3>
                        <p>

                           <strong> ZUZI, obrt za uslužne djelatnosti, VL. MIRJANA VULIĆ ŠALDIĆ</strong></p>

                <p> Sjedište: Antuna Šoljana 33, 10000 Zagreb<br><br>

                            OIB: 69101336685<br>
                            MBO: 97981036<br>
                            Broj obrtnice: 21011606742<br>
                    <br>
                            IBAN: HR1624020061140345999<br>
                            Banka: ERSTE & STEIERMÄRKISCHE BANK d.d. Rijeka<br>
                            Swift: ESBCHR22<br><br>

                            IBAN: HR0623900011101297120<br>
                            Banka: HRVATSKA POSTANSKA BANKA d.d. Zagreb<br>
                            Swift: HPBZHR2X
                </p>

            </div>

            <div class="col-12 col-sm-6 mb-5 ">
                <h2 class="h4 mb-4">Pošaljite upit</h2>
                <form action="{{ route('poruka') }}" method="POST" class="mb-3">
                    @csrf
                    <div class="row g-3">
                        <div class="col-sm-12">
                            <label class="form-label" for="cf-name">Vaše ime:&nbsp;@include('back.layouts.partials.required-star')</label>
                            <input class="form-control" type="text" name="name" id="cf-name" placeholder="">
                            @error('name')<div class="text-danger font-size-sm">Molimo upišite vaše ime!</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="cf-email">Email adresa:&nbsp;@include('back.layouts.partials.required-star')</label>
                            <input class="form-control" type="email" id="cf-email" placeholder="" name="email">
                            @error('email')<div class="invalid-feedback">Molimo upišite ispravno email adresu!</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="cf-phone">Broj telefona:&nbsp;@include('back.layouts.partials.required-star')</label>
                            <input class="form-control" type="text" id="cf-phone" placeholder="" name="phone">
                            @error('phone')<div class="invalid-feedback">Molimo upišite broj telefona!</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="cf-message">Upit:&nbsp;@include('back.layouts.partials.required-star')</label>
                            <textarea class="form-control" id="cf-message" rows="6" placeholder="" name="message"></textarea>
                            @error('message')<div class="invalid-feedback">Molimo upišite poruku!</div>@enderror
                            <button class="btn btn-primary mt-4" type="submit">Pošaljite upit</button>
                        </div>
                    </div>
                    <input type="hidden" name="recaptcha" id="recaptcha">
                </form>
            </div>

        </div>
    </section>




    <!-- Split section: Map + Contact form-->
    <div class="container-fluid px-0" id="map">
        <div class="row g-0">
            <div class="col-lg-12 iframe-full-height-wrap">


                <iframe class="iframe-full-height" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2781.460285624646!2d15.88745341256823!3d45.802039410712396!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4765d12d0d165b0f%3A0xa370e29cb63b7a2e!2sZuzi%20Shop!5e0!3m2!1shr!2shr!4v1684309041472!5m2!1shr!2shr" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>

        </div>
    </div>

@endsection

@push('js_after')
    @include('front.layouts.partials.recaptcha-js')
@endpush
