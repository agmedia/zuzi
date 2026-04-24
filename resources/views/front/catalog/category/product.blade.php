<div class="article px-0 mb-2 px-1" >
    @php
        $reviewsCount = (int) ($product->reviews_count ?? 0);
        $reviewsAverage = $reviewsCount ? round((float) ($product->reviews_avg_stars ?? 0), 1) : 0;
        $cardLayout = $cardLayout ?? null;
        $isCartShelfLayout = $cardLayout === 'cart-shelf';
        $cartShelfBadgeText = trim((string) ($cartShelfBadgeText ?? ''));
        $productCategories = $product->relationLoaded('categories') ? $product->categories : collect();
        $isBookmarkerProduct = $productCategories->contains(function ($category) {
            return (string) data_get($category, 'slug') === 'bookmarkeri';
        });
        $productImage = $isBookmarkerProduct ? $product->image : str_replace('.webp', '-thumb.webp', $product->image);
        $productImageAlt = $isBookmarkerProduct ? 'Fotografija proizvoda ' . $product->name : 'Naslovnica knjige ' . $product->name;
        $bookmarkerImageLinkStyle = $isBookmarkerProduct && ! $isCartShelfLayout ? 'display:flex;align-items:center;justify-content:center;min-height:15rem;padding:.75rem .5rem;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);' : '';
        $bookmarkerImageStyle = $isBookmarkerProduct && ! $isCartShelfLayout ? 'display:block;width:auto;height:auto;max-width:100%;max-height:15rem;margin:0 auto;object-fit:contain;object-position:center;' : '';
    @endphp

    <div class="card product-card shadow pb-2 position-relative @if($isCartShelfLayout) cart-shelf-card @endif @if($isCartShelfLayout && $isBookmarkerProduct) cart-shelf-card--bookmarkers @endif">
        <div style="position:absolute; top:.75rem; left:.75rem; right:.75rem; z-index:5; display:flex; justify-content:space-between; align-items:flex-start;">
            @if ($product->main_price > $product->main_special)
                <span class="badge bg-zuzi fw-700 badge-shadow" style="position:static;">-{{ number_format(floatval(\App\Helpers\Helper::calculateDiscount($product->price, $product->special())), 0) }}%</span>
            @else
                <span></span>
            @endif
            @if (!empty($product->delivery_24h))
                <span class="badge rounded-pill badge-shadow" style="position:static; background:#e50077; color:#fff;">
                    <i class="ci-delivery me-1"></i>24 sata
                </span>
            @endif
        </div>
        <a class="card-img-top d-block overflow-hidden text-center @if($isCartShelfLayout) cart-shelf-card__image-link @endif" href="{{ url($product->url) }}" @if($bookmarkerImageLinkStyle) style="{{ $bookmarkerImageLinkStyle }}" @endif>
            <img class="@if($isCartShelfLayout) cart-shelf-card__image @endif" loading="lazy" src="{{ $productImage }}" width="250" height="300" alt="{{ $productImageAlt }}" @if($bookmarkerImageStyle) style="{{ $bookmarkerImageStyle }}" @endif>
        </a>
        <div class="@if($isCartShelfLayout) card-body cart-shelf-card__body py-2 @else card-body pt-2 d-flex flex-column @endif" @if(! $isCartShelfLayout) style="min-height: 138px;" @endif>

            @unless($isCartShelfLayout)
                <div class="d-flex flex-wrap justify-content-between align-items-start pb-1">
                    <div class="text-muted fs-xs me-1">
                        @if($product->author)
                            <a class="product-meta fw-medium" href="{{ $product->author->url }}">{{ $product->author->title }}</a>
                        @elseif($isBookmarkerProduct)
                            <span class="product-meta fw-medium">Bookmarker</span>
                        @else
                            <span class="product-meta fw-medium">Nepoznat autor</span>
                        @endif
                    </div>
                </div>
            @endunless

            <h3 class="product-title fs-sm @if($isCartShelfLayout) mt-2 mb-1 cart-shelf-card__title @else text-truncate @endif"><a href="{{ url($product->url) }}">{{ $product->name }}</a></h3>

            @if ($reviewsCount && ! $isCartShelfLayout)
                <div class="d-flex align-items-center mb-1">
                    <div class="star-rating" aria-label="Ocjena {{ number_format($reviewsAverage, 1) }} od 5">
                        @for ($i = 0; $i < 5; $i++)
                            @if (floor($reviewsAverage) - $i >= 1)
                                <i class="star-rating-icon ci-star-filled active"></i>
                            @else
                                <i class="star-rating-icon ci-star"></i>
                            @endif
                        @endfor
                    </div>
                </div>
            @endif

            @if ($isCartShelfLayout && $cartShelfBadgeText !== '')
                <div class="cart-shelf-card__context-label">{{ $cartShelfBadgeText }}</div>
            @endif

            <div class="@if($isCartShelfLayout) cart-shelf-card__price-group @else mt-auto @endif">
                @if ($product->main_price > $product->main_special)
                    <div class="product-price"><small><span class="text-muted">NC30: <s>{{ $product->main_price_text }}</s>  @if($product->secondary_price_text){{ $product->secondary_price_text }} @endif</span></small>
                   <span class="text-dark fs-md">{{ $product->main_special_text }} @if($product->secondary_special_text) <small class="text-muted">{{ $product->secondary_special_text }}</small> @endif</span></div>
                @else
                    <div class="product-price"><span class="text-dark fs-md">{{ $product->main_price_text }}  @if($product->secondary_price_text) <small class="fs-sm text-muted">{{ $product->secondary_price_text }} </small>@endif</span></div>
                @endif
            </div>
        </div>
        <div class="product-floating-btn">
            <add-to-cart-btn-simple id="{{ $product->id }}" available="{{ $product->quantity }}"></add-to-cart-btn-simple>
        </div>
    </div>
</div>
