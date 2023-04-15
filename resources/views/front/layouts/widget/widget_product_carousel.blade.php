<!-- {"title": "Product Carousel", "description": "Some description of a Product Carousel."} -->
<section class="container {{ $data['css'] }}" style="z-index: 10;">
    @if ($data['container'])
        <div class="card px-lg-2 border-0 shadow-lg">
            <div class="card-body px-4 pt-4 pb-4">
                <h2 class="h3 text-center">{{ $data['title'] }}</h2>
                <p class="text-muted-light text-center ">{{ $data['subtitle'] }}</p>
                @if($data['url'] !='/')
                    <p class=" text-center">  <a class="btn btn-primary btn-shadow " href="{{ url($data['url']) }}">Pogledajte ponudu <i class="ci-arrow-right "></i></a></p>
                @endif

                <div class="tns-carousel pt-4">
                    <div class="tns-carousel-inner" data-carousel-options='{"items": 2, "controls": true, "nav": true, "autoHeight": true, "responsive": {"0":{"items":2, "gutter": 10},"500":{"items":2, "gutter": 18},"768":{"items":3, "gutter": 20}, "1100":{"items":4, "gutter": 30}}}'>
                    @foreach ($data['items'] as $product)
                        <!-- Product-->
                            <div>
                                @include('front.catalog.category.product')
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @else
        <h2 class="h3 text-center">{{ $data['title'] }}</h2>
        <p class="text-muted-light text-center">{{ $data['subtitle'] }}</p>
        @if($data['url'] !='/')
            <p class=" text-center">  <a class="btn btn-primary btn-shadow " href="{{ url($data['url']) }}">Pogledajte ponudu <i class="ci-arrow-right "></i></a></p>
        @endif

        <div class="tns-carousel pt-4">
            <div class="tns-carousel-inner" data-carousel-options='{"items": 2, "controls": true, "nav": true, "autoHeight": true, "responsive": {"0":{"items":2, "gutter": 10},"500":{"items":2, "gutter": 18},"768":{"items":3, "gutter": 20}, "1100":{"items":4, "gutter": 30}}}'>
            @foreach ($data['items'] as $product)
                <!-- Product-->
                    <div>
                        @include('front.catalog.category.product')
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
