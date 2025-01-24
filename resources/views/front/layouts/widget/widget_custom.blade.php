<!-- {"title": "Slider Index", "description": "Index main slider."} -->

<section class="tns-carousel tns-controls-lg mb-0 bg-white">
    <div class="tns-carousel-inner" data-carousel-options="{&quot;items&quot;: 1, &quot;autoplay&quot;: true, &quot;mode&quot;: &quot;gallery&quot;, &quot;nav&quot;: true, &quot;responsive&quot;: {&quot;0&quot;: {&quot;nav&quot;: true, &quot;controls&quot;: true}, &quot;576&quot;: {&quot;nav&quot;: true, &quot;controls&quot;: true}}}">
        <div class="px-lg-5 py-5" style="background-image: url({{ asset('media/img/pexels-suzy-hazelwood-3765180-1.webp') }});box-shadow: inset 0 0 0 1000px rgba(55, 63, 80,.7);
            -webkit-background-size: cover;
            -moz-background-size: cover;
            -o-background-size: cover;
            background-size: cover;">
        @foreach($data as  $widget)
            <div>
                <div class="rounded-3 px-md-5 text-center text-lg-start " style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
                    <div class="d-lg-flex justify-content-between align-items-center px-4  mx-auto" style="max-width: 1226px;">
                        <div class="py-2 py-sm-3 pb-0 me-xl-4 mx-auto mx-xl-0" style="max-width: 490px;">
                            <p class="text-dark fs-sm pb-0 mb-1 mt-2 "><i class="ci-bookmark  fs-sm mt-n1 me-2"></i> PONUDA MJESECA</p>
                            <h2 class="h1 text-primary font-title mb-1">{{ $widget['title'] }} </h2>
                            <div class="star-rating mb-3"><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i><i class="star-rating-icon ci-star-filled active"></i>
                            </div>
                            <p class="text-white pb-1">{{ $widget['subtitle'] }}</p>
                            <div class="d-flex flex-wrap justify-content-center justify-content-xl-start"><a class="btn btn-primary btn-shadow me-2 mb-4" href="{{ url($widget['url']) }}" role="button">Pogledajte ponudu <i class="ci-arrow-right ms-2 me-n1"></i></a></div>
                        </div>
                        <div class="bckshelf"><a  href="{{ url($widget['url']) }}" role="button"><img src="{{ $widget['image'] }}" alt="{{ $widget['title'] }}" width="auto" height="500"></a></div>
                    </div>
                </div>
            </div>
        @endforeach
        </div>
    </div>
    <section class="spikeswtop"></section>
</section>
<!-- How it works-->

