@extends('front.layouts.app')

@php
    $pageTitle = 'Dojmovi čitatelja';
    $pageDescription = 'Pročitajte dojmove kupaca i čitatelja o knjigama iz Zuzi Shopa.';
    $reviewImageUrl = function ($image) {
        $image = trim((string) $image);

        if ($image === '') {
            return null;
        }

        if (! \Illuminate\Support\Str::contains($image, '-thumb.')) {
            $image = preg_replace('/\.(jpe?g|png|webp)$/i', '-thumb.webp', $image);
        }

        if (! \Illuminate\Support\Str::startsWith($image, ['http://', 'https://'])) {
            $image = rtrim((string) config('settings.images_domain'), '/') . '/' . ltrim($image, '/');
        }

        return $image;
    };
@endphp

@section('title', \App\Models\Seo::appendBrand($pageTitle))
@section('description', \App\Models\Seo::description(null, $pageDescription))

@push('css_after')
    <style>
        .public-reviews-page {
            max-width: 1480px;
            margin: 0 auto;
            padding-bottom: 3rem;
        }

        .public-reviews-header {
            gap: 1rem;
        }

        .public-reviews-lead {
            max-width: 42rem;
            color: #7d879c;
            line-height: 1.55;
        }

        .public-reviews-masonry {
            column-count: 5;
            column-gap: 1rem;
        }

        .public-review-item {
            display: inline-block;
            width: 100%;
            margin: 0 0 1rem;
            break-inside: avoid;
            -webkit-column-break-inside: avoid;
            page-break-inside: avoid;
        }

        .public-review-card {
            display: flex;
            flex-direction: column;
            padding: 1.2rem 1.15rem 1.05rem;
            border: 1px solid rgba(43, 52, 69, .08);
            border-radius: .5rem;
            background: #fff;
            box-shadow: 0 .25rem .5625rem -.0625rem rgba(0, 0, 0, .03),
                0 .275rem 1.25rem -.0625rem rgba(0, 0, 0, .05);
            font-size: .9375rem;
        }

        .public-review-product {
            display: flex;
            gap: .75rem;
            align-items: flex-start;
            min-width: 0;
            margin-bottom: .8rem;
        }

        .public-review-product__image-link {
            flex: 0 0 3.2rem;
            width: 3.2rem;
        }

        .public-review-product__image {
            display: block;
            width: 100%;
            aspect-ratio: 2 / 3;
            object-fit: contain;
            border-radius: .35rem;
        }

        .public-review-product__copy {
            min-width: 0;
        }

        .public-review-product__title {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            color: #e50077;
            font-weight: 700;
            line-height: 1.35;
            text-decoration: none;
        }

        .public-review-product__title:hover,
        .public-review-link:hover {
            color: #c80068;
        }

        .public-review-stars {
            margin-top: .35rem;
            line-height: 1;
            white-space: nowrap;
        }

        .public-review-stars .star-rating-icon {
            margin-right: .08rem;
            color: #f59f56;
        }

        .public-review-title {
            margin-bottom: .45rem;
            color: #373f50;
            font-weight: 700;
            line-height: 1.35;
        }

        .public-review-message {
            color: #4b566b;
            line-height: 1.58;
        }

        .public-review-footer {
            display: flex;
            gap: .75rem;
            align-items: center;
            justify-content: space-between;
            padding-top: .9rem;
            margin-top: .95rem;
            border-top: 1px solid rgba(var(--cz-primary-rgb), .1);
        }

        .public-review-author {
            flex: 1 1 0;
            min-width: 0;
            overflow: hidden;
            color: #373f50;
            font-weight: 700;
            white-space: nowrap;
        }

        .public-review-author i {
            flex: 0 0 auto;
            color: #e50077;
        }

        .public-review-author-name {
            display: block;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .public-review-link {
            flex: 0 0 auto;
            color: #e50077;
            font-weight: 700;
            line-height: 1.2;
            text-align: right;
            text-decoration: none;
            white-space: nowrap;
        }

        @media (max-width: 1599.98px) {
            .public-reviews-masonry {
                column-count: 4;
            }
        }

        @media (max-width: 1199.98px) {
            .public-reviews-masonry {
                column-count: 3;
            }
        }

        @media (max-width: 767.98px) {
            .public-reviews-masonry {
                column-count: 2;
                column-gap: .85rem;
            }

            .public-review-card {
                padding: 1rem;
            }
        }

        @media (max-width: 575.98px) {
            .public-reviews-header {
                align-items: flex-start !important;
            }

            .public-reviews-masonry {
                column-count: 1;
            }
        }
    </style>
@endpush

@section('content')
    <div class="public-reviews-page">
        <nav class="mb-4" aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap">
                <li class="breadcrumb-item">
                    <a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a>
                </li>
                <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $pageTitle }}</li>
            </ol>
        </nav>

        <section class="public-reviews-header d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2">
            <div>
                <h1 class="h2 mb-2">{{ $pageTitle }}</h1>
                <p class="public-reviews-lead mb-0">Iskustva kupaca prije sljedećeg knjiškog izbora.</p>
            </div>
            <a class="btn btn-primary btn-sm btn-shadow" href="{{ route('moji-dojmovi') }}">Podijeli dojam</a>
        </section>

        @if($reviews->count())
            <div class="public-reviews-masonry">
                @foreach($reviews as $review)
                    @php
                        $reviewProduct = $review->product;
                        $reviewProductTitle = $reviewProduct->name ?? 'Obrisan artikl';
                        $reviewUrl = $reviewProduct && filled($reviewProduct->url)
                            ? url($reviewProduct->url)
                            : null;
                        $reviewReviewsUrl = $reviewUrl ? $reviewUrl . '#reviews' : null;
                        $reviewProductImage = $reviewProduct ? $reviewImageUrl($reviewProduct->image ?? null) : null;
                        $reviewExcerpt = \Illuminate\Support\Str::limit(
                            trim(preg_replace('/\s+/', ' ', strip_tags((string) $review->message))),
                            200,
                            '...'
                        );
                    @endphp

                    <article class="public-review-item">
                        <div class="public-review-card">
                            <div class="public-review-product">
                                @if($reviewProductImage)
                                    <a class="public-review-product__image-link" href="{{ $reviewReviewsUrl ?: $reviewUrl }}">
                                        <img class="public-review-product__image" src="{{ $reviewProductImage }}" alt="Naslovnica knjige {{ $reviewProductTitle }}" loading="lazy" decoding="async">
                                    </a>
                                @endif

                                <div class="public-review-product__copy">
                                    @if($reviewReviewsUrl)
                                        <a class="public-review-product__title" href="{{ $reviewReviewsUrl }}">{{ $reviewProductTitle }}</a>
                                    @else
                                        <div class="public-review-product__title text-muted">{{ $reviewProductTitle }}</div>
                                    @endif

                                    <div class="public-review-stars" aria-label="Ocjena {{ (int) $review->stars }} od 5">
                                        @for ($i = 0; $i < 5; $i++)
                                            @if (floor($review->stars) - $i >= 1)
                                                <i class="star-rating-icon ci-star-filled active"></i>
                                            @elseif ($review->stars - $i > 0)
                                                <i class="star-rating-icon ci-star"></i>
                                            @else
                                                <i class="star-rating-icon ci-star"></i>
                                            @endif
                                        @endfor
                                    </div>
                                </div>
                            </div>

                            @if(filled($review->title))
                                <div class="public-review-title">{{ $review->title }}</div>
                            @endif

                            <div class="public-review-message">{{ $reviewExcerpt }}</div>

                            <footer class="public-review-footer">
                                <span class="public-review-author d-inline-flex align-items-center">
                                    <i class="ci-user me-2"></i>
                                    <span class="public-review-author-name">{{ trim($review->fname . ' ' . $review->lname) }}</span>
                                </span>
                                @if($reviewReviewsUrl)
                                    <a class="public-review-link" href="{{ $reviewReviewsUrl }}">Pročitaj više</a>
                                @endif
                            </footer>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="pt-3">
                {{ $reviews->onEachSide(1)->links() }}
            </div>
        @else
            <div class="py-5 text-center text-muted">
                Trenutno nema objavljenih dojmova.
            </div>
        @endif
    </div>
@endsection
