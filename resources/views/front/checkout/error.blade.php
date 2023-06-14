
@extends('front.layouts.app')

@section('content')


    <div class="container pb-5 mb-sm-4">
        <div class="pt-5">
            <div class="card py-3 mt-sm-3">
                <div class="card-body text-center">
                    <h2 class="h4 pb-3 text-danger">GREŠKA..!</h2>
                    <p class="fs-sm mb-2">...</p>

                    <p class="fs-sm">Uskoro ćete primiti e-poštu s potvrdom narudžbe. </p>

                    <a class="btn btn-secondary mt-3 me-3" href="{{ route('index') }}">Nastavite pregled stranice</a>

                </div>
            </div>
        </div>
    </div>

    <section class="container-fluid pt-grid-gutter bg-third">
        <div class="container">
            <div class="row">
                <div class="col-xl-3 col-sm-6 mb-grid-gutter"><a class="card h-100" href="#map" data-scroll="">
                        <div class="card-body text-center"><i class="ci-location h3 mt-2 mb-4 text-primary"></i>
                            <h3 class="h6 mb-2">Adresa</h3>
                            <p class="fs-sm text-muted">Palmotićeva 28, Zagreb</p>
                            <div class="fs-sm text-primary">Kliknite za mapu<i class="ci-arrow-right align-middle ms-1"></i></div>
                        </div></a></div>
                <div class="col-xl-3 col-sm-6 mb-grid-gutter">
                    <div class="card h-100">
                        <div class="card-body text-center"><i class="ci-time h3 mt-2 mb-4 text-primary"></i>
                            <h3 class="h6 mb-3">Radno vrijeme</h3>
                            <ul class="list-unstyled fs-sm text-muted mb-0">
                                <li>Pon - pet: 09 - 20h</li>
                                <li class="mb-0">Sub: 09 - 14h</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6  mb-grid-gutter">
                    <div class="card h-100">
                        <div class="card-body text-center"><i class="ci-phone h3 mt-2 mb-4 text-primary"></i>
                            <h3 class="h6 mb-3">Telefoni</h3>
                            <ul class="list-unstyled fs-sm mb-0">
                                <li><a class="nav-link-style text-primary" href="tel:+38514816574"> +385 1 48 16 574</a></li>
                                <li><a class="nav-link-style text-primary" href="tel:++385981629674"> +385 98 16 29 674</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-sm-6 mb-grid-gutter">
                    <div class="card h-100">
                        <div class="card-body text-center"><i class="ci-mail h3 mt-2 mb-4 text-primary"></i>
                            <h3 class="h6 mb-3">Email adresa</h3>
                            <ul class="list-unstyled fs-sm mb-0">
                                <li><a class="nav-link-style text-primary" href="mailto:info@zuzi.hr">info@zuzi.hr</a></li>

                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
