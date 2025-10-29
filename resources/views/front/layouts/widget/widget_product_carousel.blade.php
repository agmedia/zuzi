<!-- {"title": "Carousel", "description": "Widget za product carousel"} -->
<section class="pt-2 pb-3">

    <div class="d-flex flex-wrap justify-content-between align-items-center pt-1   pb-2 mb-2">
        <h4 class="h3 mb-0 pt-3 font-title me-3">{{ $data['title'] }}  @if($data['subtitle'])  <span class="d-block fw-normal  text-dark opacity-80 mt-1 fs-base">{{ $data['subtitle'] }}</span> @endif</h4>

        @if($data['url'] !='/')
            <a class="btn btn-primary btn-sm btn-shadow mt-3" href="{{ url($data['url']) }}"><span class="d-none d-sm-inline-block">Pogledajte ponudu</span> <i class="ci-arrow-right "></i></a>
        @endif

    </div>
    <div class="tns-carousel">
        <div class="tns-carousel-inner" data-carousel-options='{"items": 2, "controls": true, "nav": true, "autoHeight": false, "responsive": {"0":{"items":2, "gutter": 10},"500":{"items":2, "gutter": 10},"768":{"items":3, "gutter": 10}, "1100":{"items":4, "gutter": 10}, "1500":{"items":5, "gutter": 10}, "1600":{"items":6, "gutter": 10}}}'>
            @foreach ($data['items'] as $product)
                <!-- Product-->
                <div>
                    @include('front.catalog.category.product')
                </div>
            @endforeach
        </div>
    </div>


</section>

