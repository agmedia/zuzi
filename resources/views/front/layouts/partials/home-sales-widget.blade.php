@php
    $salesCollections = collect($collections ?? [])->filter()->values();
    $featuredProducts = collect($featured_products ?? [])->filter()->values();
    $homeSalesFeaturedCarouselOptions = [
        'items' => 1,
        'controls' => true,
        'nav' => true,
        'autoplay' => $featuredProducts->count() > 1,
        'autoplayButtonOutput' => false,
        'autoplayTimeout' => 5500,
        'mouseDrag' => true,
        'touch' => true,
        'swipeAngle' => 30,
        'preventActionWhenRunning' => true,
        'preventScrollOnTouch' => 'auto',
    ];
    $homeSalesCollectionIcons = [
        'knjige-do-5-eura' => 'fa-bolt',
        'knjige-od-5-do-10-eura' => 'fa-gem',
        'najpopularnije-ovaj-mjesec' => 'fa-star',
        'verdens-100-klasici' => 'fa-book-open',
        'najprodavanije-ovaj-mjesec' => 'fa-fire',
    ];
@endphp

@if ($salesCollections->isNotEmpty() || $featuredProducts->isNotEmpty())
    <section class="home-sales-hub mb-4" aria-labelledby="home-sales-hub-title">
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-3">
            <div>
                <span class="home-sales-hub__eyebrow">Brzi put do kupnje</span>
                <h2 class="h3 mb-2 font-title" id="home-sales-hub-title">Knjige koje se najbrže klikaju, biraju i kupuju</h2>
                <p class="text-muted mb-0">Uhvati povoljne ulove, naslove koje kupci već biraju i bestsellere koji ovog mjeseca najbrže odlaze.</p>
            </div>

            <a class="btn btn-outline-primary btn-sm align-self-start align-self-lg-center" href="{{ route('catalog.route.curated', ['collection' => 'najprodavanije-ovaj-mjesec']) }}">
                Top prodaja mjeseca <i class="ci-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-3 align-items-stretch">
            @if ($featuredProducts->isNotEmpty())
                <div class="col-12 col-xl-5 d-flex">
                    <div class="home-sales-hub__featured-shell w-100">
                        <div class="tns-carousel widget-touch-carousel home-sales-hub__featured-slider w-100">
                            <div class="tns-carousel-inner" data-carousel-options='@json($homeSalesFeaturedCarouselOptions)'>
                                @foreach ($featuredProducts as $featuredProductData)
                                    @php
                                        $featuredProduct = data_get($featuredProductData, 'product');
                                    @endphp
                                    <div>
                                        <a
                                            class="home-sales-hub__featured h-100 text-decoration-none"
                                            href="{{ url($featuredProduct->url) }}"
                                        >
                                            <div class="row g-0 align-items-center h-100">
                                                <div class="col-sm-5">
                                                    <div class="home-sales-hub__featured-image-wrap">
                                                        <img
                                                            class="home-sales-hub__featured-image"
                                                            src="{{ $featuredProduct->thumb }}"
                                                            alt="Naslovnica knjige {{ $featuredProduct->name }}"
                                                            width="320"
                                                            height="380"
                                                            loading="lazy"
                                                        >
                                                    </div>
                                                </div>

                                                <div class="col-sm-7">
                                                    <div class="home-sales-hub__featured-copy">
                                                        <span class="home-sales-hub__featured-badge">
                                                            Bestseller #{{ data_get($featuredProductData, 'position') }} ovog mjeseca
                                                        </span>

                                                        <h3 class="h4 mb-2">{{ $featuredProduct->name }}</h3>
                                                        <p class="text-muted mb-3">Naslov koji kupci trenutno grabe bez puno razmišljanja.</p>

                                                        <div class="home-sales-hub__featured-price mt-3">
                                                            @if ($featuredProduct->main_price > $featuredProduct->main_special)
                                                                <small class="text-muted d-block"><s>{{ $featuredProduct->main_price_text }}</s></small>
                                                                <strong>{{ $featuredProduct->main_special_text }}</strong>
                                                            @else
                                                                <strong>{{ $featuredProduct->main_price_text }}</strong>
                                                            @endif
                                                        </div>

                                                        <span class="home-sales-hub__featured-cta home-sales-hub__cta-button btn btn-outline-primary btn-sm mt-3">
                                                            Pogledaj artikl <i class="ci-arrow-right ms-1"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-12 {{ $featuredProducts->isNotEmpty() ? 'col-xl-7' : '' }}">
                <div class="row g-3 h-100">
                    @foreach ($salesCollections as $salesCollection)
                        @php
                            $salesCollectionPath = trim((string) parse_url($salesCollection['url'], PHP_URL_PATH), '/');
                            $salesCollectionKey = $salesCollection['slug'] ?? basename($salesCollectionPath);
                            $salesCollectionIcon = $homeSalesCollectionIcons[$salesCollectionKey] ?? 'fa-book';
                        @endphp
                        <div class="col-12 col-md-6">
                            <a
                                class="home-sales-hub__card home-sales-hub__card--{{ $loop->index + 1 }} card border-0 h-100 text-decoration-none"
                                href="{{ $salesCollection['url'] }}"
                                style="--sales-card-accent: {{ $salesCollection['accent'] }}; --sales-card-surface: {{ $salesCollection['surface'] }};"
                            >
                                <div class="home-sales-hub__card-body card-body d-flex flex-column h-100">
                                    <div class="home-sales-hub__card-top d-flex justify-content-between align-items-start gap-2 mb-3">
                                        <span class="home-sales-hub__card-badge">
                                            <i class="fas {{ $salesCollectionIcon }}" aria-hidden="true"></i>
                                            {{ $salesCollection['badge'] }}
                                        </span>
                                        <span class="home-sales-hub__card-count">{{ $salesCollection['count_label'] }}</span>
                                    </div>

                                    <span class="home-sales-hub__card-eyebrow">{{ $salesCollection['eyebrow'] }}</span>
                                    <h3 class="h5 mb-2">{{ $salesCollection['title'] }}</h3>
                                    <p class="text-muted mb-0 flex-grow-1">{{ $salesCollection['description'] }}</p>

                                    <span class="home-sales-hub__card-cta home-sales-hub__cta-button btn btn-outline-primary btn-sm mt-3">
                                        {{ $salesCollection['cta'] }} <i class="ci-arrow-right ms-1"></i>
                                    </span>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <a class="home-sales-hub__loyalty d-block text-decoration-none mt-3" href="{{ url('info/loyalty-klub-sve-sto-trebas-znati') }}">
            <div class="home-sales-hub__loyalty-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="home-sales-hub__loyalty-copy">
                    <span class="home-sales-hub__loyalty-kicker">
                        <i class="fas fa-award" aria-hidden="true"></i>
                        Loyalty Klub
                    </span>
                    <h3 class="h5 mb-1">Skupljaj bodove pri svakoj kupnji</h3>
                    <p class="mb-0">1 € = 1 bod, 100 bodova = 5 € popusta, a prva kupnja donosi 50 bodova dobrodošlice.</p>
                </div>

                <div class="home-sales-hub__loyalty-actions d-flex flex-wrap align-items-center gap-2">
                    <span class="home-sales-hub__loyalty-pill">
                        <i class="fas fa-coins" aria-hidden="true"></i>
                        1 € = 1 bod
                    </span>
                    <span class="home-sales-hub__loyalty-pill">
                        <i class="fas fa-gift" aria-hidden="true"></i>
                        100 bodova = 5 €
                    </span>
                    <span class="home-sales-hub__loyalty-cta btn btn-primary btn-sm">
                        Saznaj više <i class="ci-arrow-right ms-1"></i>
                    </span>
                </div>
            </div>
        </a>
    </section>
@endif
