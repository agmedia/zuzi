<!-- {"title": "Carousel", "description": "Widget za product carousel"} -->
@php
    $productWidgetCarouselOptions = [
        'items' => 2,
        'controls' => true,
        'nav' => true,
        'autoHeight' => false,
        'mouseDrag' => true,
        'touch' => true,
        'swipeAngle' => 30,
        'preventActionWhenRunning' => true,
        'preventScrollOnTouch' => 'auto',
        'responsive' => [
            0 => ['items' => 2, 'gutter' => 10, 'controls' => true],
            500 => ['items' => 2, 'gutter' => 10, 'controls' => true],
            768 => ['items' => 3, 'gutter' => 10],
            1100 => ['items' => 4, 'gutter' => 10],
            1500 => ['items' => 5, 'gutter' => 10],
            1600 => ['items' => 6, 'gutter' => 10],
        ],
    ];

    $products = collect($data['items'] ?? [])->filter(function ($product) {
        if (! method_exists($product, 'getRawOriginal')) {
            return filled($product->image ?? null);
        }

        $image = $product->getRawOriginal('image');

        return filled($image) && $image !== 'media/avatars/avatar0.jpg';
    })->values();
@endphp
@if ($products->isNotEmpty())
<section class="pt-0 pb-0">

    <div class="d-flex flex-wrap justify-content-between align-items-center pt-1   pb-2 mb-2">
        <h4 class="h3 mb-0 pt-3 font-title me-3">{{ $data['title'] }}  @if($data['subtitle'])  <span class="d-block fw-normal  text-dark opacity-80 mt-1 fs-base">{{ $data['subtitle'] }}</span> @endif</h4>

        @if($data['url'] !='/')
            <a class="btn btn-primary btn-sm btn-shadow mt-3" href="{{ url($data['url']) }}"><span class="d-none d-sm-inline-block">Pogledajte ponudu</span> <i class="ci-arrow-right "></i></a>
        @endif

    </div>
    <div class="tns-carousel widget-touch-carousel widget-card-carousel">
        <div class="tns-carousel-inner" data-carousel-options='@json($productWidgetCarouselOptions)'>
            @foreach ($products as $product)
                <!-- Product-->
                <div>
                    @include('front.catalog.category.product')
                </div>
            @endforeach
        </div>
    </div>


</section>
@endif
