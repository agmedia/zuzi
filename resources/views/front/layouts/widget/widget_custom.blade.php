<!-- {"title": "Slider Index", "description": "Index main slider."} -->

<section class="tns-carousel mb-3 rounded-3 bg-light shadow ">
    <div class="tns-carousel-inner" data-carousel-options="{&quot;items&quot;: 1, &quot;mode&quot;: &quot;gallery&quot;, &quot;nav&quot;: true, &quot;responsive&quot;: {&quot;0&quot;: {&quot;nav&quot;: true, &quot;controls&quot;: true}, &quot;576&quot;: {&quot;nav&quot;: false, &quot;controls&quot;: true}}}">
        @foreach($data as  $widget)
            <div>
                <div class="pt-3  px-md-5 text-center text-xl-start   px-2 mb-3 " >
                    <div class="d-xl-flex justify-content-between align-items-center px-4  mx-auto" style="max-width: 1226px;">
                        <div class=" py-sm-3 pb-0 me-xl-4 mx-auto mx-xl-0" style="max-width: 550px;">

                            <h2 class="h3 text-primary font-title mb-3 mb-sm-1">{{ $widget['title'] }} </h2>

                            <p class="text-dark d-none d-sm-block fs-md">{{ $widget['subtitle'] }}</p>
                            <div class="d-flex flex-wrap justify-content-center justify-content-xl-start"><a class="btn btn-primary btn-shadow me-2 mb-2" href="{{ url($widget['url']) }}" role="button">Pogledajte ponudu <i class="ci-arrow-right ms-2 me-n1"></i></a></div>
                        </div>
                        <div class="p-3"><img src="{{ $widget['image'] }}" alt="{{ $widget['title'] }}" width="350" height="350"></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
<!-- How it works-->


<!-- How it works-->

