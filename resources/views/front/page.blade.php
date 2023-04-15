@extends('front.layouts.app')
@if (request()->routeIs(['index']))
    @section ( 'title', 'Antikvarijat Biblos - Knjige, vedute i zemljovidi' )
@section ( 'description', 'Dobrodošli na stranice Antikvarijata Biblos, Palmotićeva 28, Zagreb. Radno vrijeme pon-pet 09-20h, sub 09-14h.' )


@push('meta_tags')

    <link rel="canonical" href="{{ env('APP_URL')}}" />
    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="product" />
    <meta property="og:title" content="Antikvarijat Biblos - Knjige, vedute i zemljovidi" />
    <meta property="og:description" content="Dobrodošli na stranice Antikvarijata Biblos, Palmotićeva 28, Zagreb. Radno vrijeme pon-pet 09-20h, sub 09-14h." />
    <meta property="og:url" content="{{ env('APP_URL')}}"  />
    <meta property="og:site_name" content="Antikvarijat Biblos" />
    <meta property="og:image" content="https://www.antikvarijat-biblos.hr//media/antikvarijat-biblos.jpg" />
    <meta property="og:image:secure_url" content="https://www.antikvarijat-biblos.hr//media/antikvarijat-biblos.jpg" />
    <meta property="og:image:width" content="1920" />
    <meta property="og:image:height" content="720" />
    <meta property="og:image:type" content="image/jpeg" />
    <meta property="og:image:alt" content="Antikvarijat Biblos - Knjige, vedute i zemljovidi" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Antikvarijat Biblos - Knjige, vedute i zemljovidi" />
    <meta name="twitter:description" content="Antikvarijat Biblos - Knjige, vedute i zemljovidi" />
    <meta name="twitter:image" content="https://www.antikvarijat-biblos.hr/media/antikvarijat-biblos.jpg" />

@endpush

@else
    @section ( 'title', $page->title. ' - Antikvarijat Biblos' )
@section ( 'description', $page->meta_description )
@endif

@section('content')

    @if (request()->routeIs(['index']))

        <!-- Hero section -->
        <section class="bg-accent bg-position-top-left bg-repeat-0 py-5" style="background-image: url({{ config('settings.images_domain') . 'media/img/lightslider.webp' }});-webkit-background-size: cover;-moz-background-size: cover;-o-background-size: cover;background-size: cover;">
            <div class="pb-lg-5 mb-lg-3">
                <div class="container py-lg-4 my-lg-5">
                    <div class="row mb-2 mb-sm-3">
                        <div class="col-lg-7 col-md-9  text-start">
                            <h1 class="text-white lh-base">Knjige, vedute & zemljovidi</h1>

                        </div>
                    </div>
                    <div class="row pb-lg-2 mb-3 mb-sm-3">
                        <div class="col-lg-7 col-md-8">
                            <form action="{{ route('pretrazi', ['tip' => 'all']) }}" method="get">
                                <div class="input-group input-group-lg flex-nowrap">
                                    <input type="text" class="form-control rounded-start" name="{{ config('settings.search_keyword') }}" placeholder="Pretražite po nazivu ili autoru">
                                    <button class="btn btn-primary btn-lg fs-base" type="submit"><i class="ci-search"></i></button>
                                </div>
                            </form>
                            <div class="form-text text-white py-2"><span class="text-muted-light">*</span> Sve knjige na stranici su dostupne</div>
                        </div>
                    </div>

                    <div class="row mb-3 mb-sm-3">
                        <div class="col-lg-7 col-md-9  text-start">

                            <h2 class="h5 text-white fw-light">Dobrodošli na stranice Antikvarijata Biblos</h2>
                            <p class="text-white fw-light">Palmotićeva 28, Zagreb (križanje Palmotićeve i Đorđićeve ulice) radno vrijeme ponedjeljak-petak 09-20h, subotom 09-14h.</p>
                        </div>
                    </div>

                    <div class="widget mt-4 text-md-nowrap  pb-lg-5 mb-4 mb-sm-3 text-start">
                        <a class="btn-social bs-dark bs-instagram me-2 mb-2" href="https://www.instagram.com/antikvarijat_biblos/"><i class="ci-instagram"></i></a>
                        <a class="btn-social bs-dark bs-facebook me-2 mb-2" href="https://www.facebook.com/AntikvarijatBiblos/"><i class="ci-facebook"></i></a>
                    </div>
                </div>
            </div>
        </section>

        {!! $page->description !!}

        <section class="container-fluid pt-grid-gutter bg-third">
            <div class="container">
                <div class="row">
                    <div class="col-xl-3 col-sm-6 mb-grid-gutter">
                        <a class="card h-100" href="https://www.google.com/maps/place/Biblos/@45.810942,15.9794894,17.53z/data=!4m5!3m4!1s0x4765d7aac4f8b023:0xb60bceb791b31ede!8m2!3d45.8106161!4d15.9816921?hl=hr" target="_blank">
                            <div class="card-body text-center"><i class="ci-location h3 mt-2 mb-4 text-primary"></i>
                                <h3 class="h6 mb-2">Adresa</h3>
                                <p class="fs-sm text-muted">Palmotićeva 28, Zagreb</p>
                                <div class="fs-sm text-primary">Kliknite za mapu<i class="ci-arrow-right align-middle ms-1"></i></div>
                            </div>
                        </a>
                    </div>
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
                                    <li><a class="nav-link-style text-primary" href="mailto:info@antikvarijat-biblos.hr">info@antikvarijat-biblos.hr</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    @else

        <div class=" bg-dark pt-4 pb-3" style="background-image: url({{ config('settings.images_domain') . 'media/img/indexslika.jpg' }});-webkit-background-size: cover;-moz-background-size: cover;-o-background-size: cover;background-size: cover;">
            <div class="container d-lg-flex justify-content-between py-2 py-lg-3">
                <div class="order-lg-2 mb-3 mb-lg-0 pt-lg-2">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-light flex-lg-nowrap justify-content-center justify-content-lg-start">
                            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                            <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $page->title }}</li>
                        </ol>
                    </nav>
                </div>
                <div class="order-lg-1 pe-lg-4 text-center text-lg-start">
                    <h1 class="text-light">{{ $page->title }}</h1>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="mt-5 mb-5">
                {!! $page->description !!}
            </div>
        </div>

    @endif

@endsection
