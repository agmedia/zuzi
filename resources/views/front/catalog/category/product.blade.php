<div class="article " >

<div class="card product-card  pb-3">
    @if ($product->main_price > $product->main_special)
        <span class="badge rounded-pill bg-primary mt-1 ms-1 badge-shadow">-{{ number_format(floatval(\App\Helpers\Helper::calculateDiscount($product->price, $product->special())), 0) }}%</span>


    @endif


        <a class="card-img-top d-block overflow-hidden" href="{{ url($product->url) }}">
        <img load="lazy" src="{{ $product->thumb }}" width="250" height="300" alt="{{ $product->name }}">




            </a>
                <div class="card-body pt-2" style="min-height: 126px;">

                        @if ($product->author)
                                <a class="product-meta d-block fs-xs pb-1" href="{{ url($product->author->url) }}">{{ $product->author->title }}</a>
                            @else
                                <a class="product-meta d-block fs-xs pb-1" href="#">Nepoznato</a>
                            @endif



                             <h3 class="product-title fs-sm"><a href="{{ url($product->url) }}">{{ $product->name }}</a></h3>


                            {{--     @if ($product->category_string)
                                <div class="d-flex flex-wrap justify-content-between align-items-center">
                                    <div class="fs-sm me-2"><i class="ci-book text-muted" style="font-size: 11px;"></i> {!! $product->category_string !!}</div>
                                </div>
                            @endif --}}


                            @if ($product->main_price > $product->main_special)
                                <div class="product-price"><small><span class="text-muted">NC 30 dana: {{ $product->main_special_text }}  @if($product->secondary_price_text) {{ $product->secondary_special_text }} @endif</span></small></div>
                                <div class="product-price"><span class="text-accent">{{ $product->main_price_text }}  @if($product->secondary_price_text) {{ $product->secondary_price_text }} @endif</span></div>
                            @else
                                <div class="product-price"><span class="text-accent">{{ $product->main_price_text }}  @if($product->secondary_price_text) <small class="text-muted">{{ $product->secondary_price_text }} </small>@endif</span></div>
                            @endif


            </div>

        <div class="product-floating-btn">
            <add-to-cart-btn-simple id="{{ $product->id }}"></add-to-cart-btn-simple>
        </div>
</div>

</div>

