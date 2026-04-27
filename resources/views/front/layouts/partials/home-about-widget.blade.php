@php
    $aboutUrl = route('catalog.route.page', ['page' => $aboutPage]);
    $aboutSquareImage = asset('media/onama/zuzi-onana-final.jpg');
    $aboutHeroTitle = 'Broj 1 online knjižara i antikvarijat u Hrvatskoj';
    $aboutHeroIntro = 'Više od knjižare. Mjesto gdje počinju dobre priče.';
    $aboutHeroBody = 'Zuzi je danas jedna od najvećih online knjižara i antikvarijata u Hrvatskoj, s više od 84.000 naslova i istinskom ljubavlju prema knjigama.';
@endphp

<section class="mb-4">
    <style>
        .home-about-widget__image {
            display: block;
            width: 562px;
            height: auto;
            max-width: 100%;
            max-height: 450px;
            border-radius: 15px;
        }
    </style>

    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="row g-0 align-items-center">
            <div class="col-lg-6">
                <div class="card-body p-4 p-xl-5">


                    <h2 class="h2 text-primary font-title mb-3">{{ $aboutHeroTitle }}</h2>
                    <p class="fs-lg text-dark mb-2">{{ $aboutHeroIntro }}</p>
                    <p class="text-muted mb-4">{{ $aboutHeroBody }}</p>

                    <a class="btn btn-primary btn-shadow" href="{{ $aboutUrl }}">
                        O nama <i class="ci-arrow-right ms-1"></i>
                    </a>
                    <a class="btn btn-dark btn-shadow" href="{{ $aboutUrl }}">
                        Kontaktirajte nas <i class="ci-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-6 bg-light">
                <div class="p-3 p-xl-4 d-flex justify-content-center justify-content-lg-end align-items-center">
                    <img
                        class="home-about-widget__image shadow-sm"
                        src="{{ $aboutSquareImage }}"
                        alt="ZUZI Shop knjižara i antikvarijat"
                        loading="lazy"
                        width="562"
                        height="450"
                        style="border-radius:10px"
                    >
                </div>
            </div>
        </div>
    </div>
</section>
