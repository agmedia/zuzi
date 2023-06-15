


<!-- Hero slider-->
<section class="tns-carousel tns-controls-lg mb-0 bg-white">
    <div class="tns-carousel-inner" data-carousel-options="{&quot;mode&quot;: &quot;gallery&quot;, &quot;responsive&quot;: {&quot;0&quot;:{&quot;nav&quot;:false, &quot;controls&quot;: false},&quot;992&quot;:{&quot;nav&quot;:false, &quot;controls&quot;: false}}}">
        <!-- Item-->
        <div class="px-lg-5" style="background-image: url({{ asset('media/img/pexels-suzy-hazelwood-3765180.jpg') }});box-shadow: inset 0 0 0 1000px rgba(55, 63, 80,.7);
            -webkit-background-size: cover;
            -moz-background-size: cover;
            -o-background-size: cover;
            background-size: cover;">
            <div class="d-lg-flex justify-content-center align-items-center ps-lg-4">
                <div class="position-relative mx-auto py-5 px-4 mb-lg-5 order-lg-1" style="max-width: 42rem; z-index: 10;">
                    <div class="pb-lg-5 mb-lg-5 pt-lg-5 mt-lg-5 text-center">
                        <span class="badge bg-dark fw-semibold fs-lg p-2 mb-4 rounded-1 from-bottom delay-3">ZUZI SHOP</span>
                        <h1 class="text-light display-6 fw-bold from-bottom delay-1">Online knjižara i antikvarijat</h1>
                        <p class="fs-xl text-light pb-3 from-bottom delay-3">Prodaja i otkup rabljenih i novih knjiga</p>
                        <div class="scale-up delay-4 mx-auto mx-lg-0"><a class="btn btn-primary" href="{{ route('catalog.route', ['group' => \App\Helpers\Helper::categoryGroupPath(true)]) }}">Pogledajte ponudu<i class="ci-arrow-right ms-2 me-n1"></i></a></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Item-->



    </div>
    <section class="spikeswtop"></section>
</section>

<section class="bg-white " >
    <div class="container py-4  ">
    <h2 class="text-center fw-bold pt-0">Popularne kategorije</h2>
    <p class="text-muted text-center mb-5">Odaberite željeni naslov iz jedne od naših kategorija</p>
        <div class="tns-carousel">
            <div class="tns-carousel-inner" data-carousel-options='{"items": 2, "controls": true, "autoHeight": false, "responsive": {"0":{"items":2, "gutter": 20},"740":{"items":2, "gutter": 20},"900":{"items":3, "gutter": 20}, "1100":{"items":4, "gutter": 30}}}'>
                @foreach ($kategorijefeatured as $cat)
                    <div class="article mb-grid-gutter">
                        <a class="card border-0 shadow" href="{{ url(\Illuminate\Support\Str::slug($cat->group) . '/' . $cat->slug) }}">
                            <span class="blog-entry-meta-label fs-sm"><i class="ci-heart text-primary me-0"></i></span>
                            <img class="card-img-top" src="{{ $cat->image }}" alt="{{ $cat->title }}">
                            <div class="card-body py-3 text-center">
                                <h3 class="h6 mt-1 text-primary">{{ $cat->title }}</h3>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</section>


