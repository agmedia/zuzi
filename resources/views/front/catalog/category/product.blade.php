<div class="article px-0 mb-2 px-1" >
    @php
        $reviewsCount = (int) ($product->reviews_count ?? 0);
        $reviewsAverage = $reviewsCount ? round((float) ($product->reviews_avg_stars ?? 0), 1) : 0;
    @endphp

    <div class="card product-card shadow pb-2 position-relative">
        <div style="position:absolute; top:.75rem; left:.75rem; right:.75rem; z-index:5; display:flex; justify-content:space-between; align-items:flex-start;">
            @if ($product->main_price > $product->main_special)
                <span class="badge bg-primary badge-shadow" style="position:static;">-{{ number_format(floatval(\App\Helpers\Helper::calculateDiscount($product->price, $product->special())), 0) }}%</span>
            @else
                <span></span>
            @endif
            @if (!empty($product->delivery_24h))
                <span class="badge rounded-pill badge-shadow" style="position:static; background:#e50077; color:#fff;">
                    <i class="ci-delivery me-1"></i>24 sata
                </span>
            @endif
        </div>
        <a class="card-img-top d-block overflow-hidden text-center" href="{{ url($product->url) }}">
            <img loading="lazy" src="{{ str_replace('.webp','-thumb.webp', $product->image) }}" width="250" height="300" alt="Naslovnica knjige {{ $product->name }}">
        </a>
        <div class="card-body pt-2" style="min-height: 120px;">

            <div class="d-flex flex-wrap justify-content-between align-items-start pb-1">
                <div class="text-muted fs-xs me-1">
                    @if($product->author)
                        <a class="product-meta fw-medium" href="{{ $product->author->url }}">{{ $product->author->title }}</a>
                    @else
                        <span class="product-meta fw-medium">Nepoznat autor</span>
                    @endif
                </div>

            </div>

            <h3 class="product-title fs-sm text-truncate"><a href="{{ url($product->url) }}">{{ $product->name }}</a></h3>

            @if ($reviewsCount)
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div class="star-rating">
                        @for ($i = 0; $i < 5; $i++)
                            @if (floor($reviewsAverage) - $i >= 1)
                                <i class="star-rating-icon ci-star-filled active"></i>
                            @else
                                <i class="star-rating-icon ci-star"></i>
                            @endif
                        @endfor
                    </div>
                    <span class="fs-xs text-muted">{{ number_format($reviewsAverage, 1) }}/5</span>
                </div>
            @endif

            @if ($product->main_price > $product->main_special)
                <div class="product-price"><small><span class="text-muted">NC30: <s>{{ $product->main_price_text }}</s>  @if($product->secondary_price_text){{ $product->secondary_price_text }} @endif</span></small>
               <span class="text-dark fs-md">{{ $product->main_special_text }} @if($product->secondary_special_text) <small class="text-muted">{{ $product->secondary_special_text }}</small> @endif</span></div>
            @else
                <div class="product-price"><span class="text-dark fs-md">{{ $product->main_price_text }}  @if($product->secondary_price_text) <small class="fs-sm text-muted">{{ $product->secondary_price_text }} </small>@endif</span></div>
            @endif
        </div>
        <div class="product-floating-btn">
            <add-to-cart-btn-simple id="{{ $product->id }}" available="{{ $product->quantity }}"></add-to-cart-btn-simple>
        </div>
    </div>
</div>
