
<section class="py-5 {{ $data['css'] }}" style="background-image: url({{ config('settings.images_domain') . 'media/img/zuzi-bck-transparent.svg' }});z-index: 10;-webkit-background-size: cover;-moz-background-size: cover;-o-background-size: cover;background-size: cover;" >
    @if ($data['container'])

                <h2 class="h3 text-center">{{ $data['title'] }}</h2>
                <p class="text-muted-light text-center ">{{ $data['subtitle'] }}</p>
                @if($data['url'] !='/')
                    <p class=" text-center">  <a class="btn btn-primary btn-shadow " href="{{ url($data['url']) }}">Pogledajte ponudu <i class="ci-arrow-right "></i></a></p>
                @endif

                <div class="tns-carousel pt-4 pb-2">
                    <div class="tns-carousel-inner" data-carousel-options='{"items": 2, "controls": true, "nav": true, "autoHeight": true, "responsive": {"0":{"items":2, "gutter": 10},"500":{"items":2, "gutter": 18},"768":{"items":3, "gutter": 20}, "1100":{"items":4, "gutter": 30}}}'>
                    @foreach ($data['items'] as $product)
                        <!-- Product-->
                            <div>
                                @include('front.catalog.category.product')
                            </div>
                        @endforeach
                    </div>

        </div>
    @else
        <div class="container">
            <h2 class="text-center fw-bold pt-4 pt-sm-3">{{ $data['title'] }}</h2>
            <p class="text-muted text-center mb-5">{{ $data['subtitle'] }}</p>
        @if($data['url'] !='/')
            <p class=" text-center">  <a class="btn btn-primary btn-shadow " href="{{ url($data['url']) }}">Pogledajte ponudu <i class="ci-arrow-right "></i></a></p>
        @endif
        <div class="tns-carousel pt-4 pb-2">
            <div class="tns-carousel-inner" data-carousel-options='{"items": 2, "controls": true, "nav": true, "autoHeight": false, "responsive": {"0":{"items":2, "gutter": 10},"500":{"items":2, "gutter": 18},"768":{"items":3, "gutter": 20}, "1100":{"items":5, "gutter": 30}}}'>
            @foreach ($data['items'] as $product)
                <!-- Product-->
                    <div>
                        @include('front.catalog.category.product')
                    </div>
                @endforeach
            </div>
        </div>
        </div>
    @endif
</section>
