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
        $deliveryTooltip = 'Dostava unutar 24 sata.';
        $salesBadgeType = $product->sales_badge_type ?? (!empty($product->is_best_seller) ? 'bestseller' : (!empty($product->is_popular) ? 'popular' : null));
        $bestSellerTooltip = 'Bestseller';
        $popularTooltip = 'Popularno';
        $salesBadgeTooltip = $salesBadgeType === 'bestseller' ? $bestSellerTooltip : $popularTooltip;
        $bestSellerIconPath = 'M528 0c8.7998 0 16-7.2002 16-16v-32c0-8.7998-7.2002-16-16-16h-416c-8.7998 0-16 7.2002-16 16v32c0 8.7998 7.2002 16 16 16h416zM592 320c26.5 0 48-21.5 48-48s-21.5-48-48-48c-2.59961 0-5.2002 .400391-7.7002 .799805l-72.2998-192.8h-384l-72.2998 192.8c-2.5-.399414-5.10059-.799805-7.7002-.799805c-26.5 0-48 21.5-48 48s21.5996 48 48.0996 48s48-21.5 48-48c0-7.09961-1.69922-13.7998-4.39941-19.7998l72.2998-43.4004c15.2998-9.2002 35.2998-4 44.2002 11.6006l81.5 142.6c-10.7002 8.7998-17.7002 22-17.7002 37c0 26.5 21.5 48 48 48s48-21.5 48-48c0-15-7-28.2002-17.7002-37l81.5-142.6c8.90039-15.6006 28.7998-20.8008 44.2002-11.6006l72.4004 43.4004c-2.80078 6.09961-4.40039 12.7002-4.40039 19.7998c0 26.5 21.5 48 48 48z';
        $popularIconPath = 'M259.3 17.8 194 150.2 47.9 171.5c-26.2 3.8-36.7 36-17.7 54.6l105.7 103-25 145.5c-4.5 26.3 23.2 46 46.4 33.7L288 439.6l130.7 68.7c23.2 12.2 50.9-7.4 46.4-33.7l-25-145.5 105.7-103c19-18.5 8.5-50.8-17.7-54.6L382 150.2 316.7 17.8c-11.7-23.6-45.6-23.9-57.4 0Z';
        $salesBadgeIconPath = $salesBadgeType === 'bestseller' ? $bestSellerIconPath : $popularIconPath;
        $salesBadgeViewBox = $salesBadgeType === 'bestseller' ? '0 0 640 512' : '0 0 576 512';
        $salesBadgeTransform = $salesBadgeType === 'bestseller' ? 'translate(0 512) scale(1 -1)' : null;
        $salesBadgeIconSize = $salesBadgeType === 'popular' ? '12' : '13.5';
    @endphp

    <div class="card product-card shadow pb-2 position-relative @if($isCartShelfLayout) cart-shelf-card @endif @if($isCartShelfLayout && $isBookmarkerProduct) cart-shelf-card--bookmarkers @endif">
        <div style="position:absolute; top:.75rem; left:.75rem; right:.75rem; z-index:5; display:flex; justify-content:space-between; align-items:flex-start;">
            @if ($product->main_price > $product->main_special)
                <span class="badge bg-zuzi fw-700 badge-shadow" style="position:static;">-{{ number_format(floatval(\App\Helpers\Helper::calculateDiscount($product->price, $product->special())), 0) }}%</span>
            @else
                <span></span>
            @endif
            <div style="display:flex; align-items:flex-start; gap:.45rem;">
                @if (!empty($product->delivery_24h))
                    <span
                        class="badge rounded-pill badge-shadow"
                        title="{{ $deliveryTooltip }}"
                        aria-label="{{ $deliveryTooltip }}"
                        style="position:static; background:#e50077; color:#fff;"
                    >
                        <i class="ci-delivery me-1"></i>Dostava 24h
                    </span>
                @endif
            </div>
        </div>
        <a class="card-img-top d-block overflow-hidden text-center position-relative @if($isCartShelfLayout) cart-shelf-card__image-link @endif" href="{{ url($product->url) }}" @if($bookmarkerImageLinkStyle) style="{{ $bookmarkerImageLinkStyle }}" @endif>
            <img class="@if($isCartShelfLayout) cart-shelf-card__image @endif" loading="lazy" src="{{ $productImage }}" width="250" height="300" alt="{{ $productImageAlt }}" @if($bookmarkerImageStyle) style="{{ $bookmarkerImageStyle }}" @endif>
            @if ($salesBadgeType)
                <span
                    class="badge-shadow"
                    title="{{ $salesBadgeTooltip }}"
                    aria-label="{{ $salesBadgeTooltip }}"
                    style="position:absolute; left:.55rem; bottom:.55rem; z-index:3; display:inline-flex; align-items:center; justify-content:center; width:1.65rem; height:1.65rem; border-radius:999px; border:1px solid rgba(229,0,119,.28); background:rgba(255,255,255,.98); color:#e50077; box-shadow:0 .35rem .9rem rgba(31,45,61,.18);"
                >
                    <svg viewBox="{{ $salesBadgeViewBox }}" width="{{ $salesBadgeIconSize }}" height="{{ $salesBadgeIconSize }}" aria-hidden="true" focusable="false" style="display:block;">
                        <path fill="currentColor" d="{{ $salesBadgeIconPath }}" @if($salesBadgeTransform) transform="{{ $salesBadgeTransform }}" @endif/>
                    </svg>
                </span>
            @endif
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
