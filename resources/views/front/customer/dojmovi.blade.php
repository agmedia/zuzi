@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand('Moji dojmovi'))
@section('description', \App\Models\Seo::description(null, 'Pregled dojmova citatelja u korisnickom racunu na ' . \App\Models\Seo::brand() . '.'))

@php
    $accountReviewImageUrl = function ($image): string {
        $image = trim((string) $image);

        if (blank($image)) {
            return '';
        }

        if (\Illuminate\Support\Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        return rtrim((string) config('settings.images_domain'), '/') . '/' . ltrim($image, '/');
    };

    $accountReviewFallbackImage = $accountReviewImageUrl('media/avatars/avatar0.jpg');
@endphp

@push('css_after')
    <style>
        .account-review-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            margin-bottom: 1.35rem;
        }

        .account-review-summary-item {
            border: 1px solid #edf0f5;
            border-radius: .5rem;
            background: #fbfcff;
            padding: 1rem;
        }

        .account-review-summary-label {
            color: #6c7485;
            font-size: .82rem;
            line-height: 1.35;
        }

        .account-review-summary-value {
            color: #373f50;
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1.2;
            margin-top: .35rem;
        }

        .account-review-prompt-grid,
        .account-review-list {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: .85rem;
        }

        .account-review-prompt {
            display: grid;
            grid-template-columns: 4.5rem minmax(0, 1fr) auto;
            gap: .9rem;
            align-items: center;
            border: 1px solid #edf0f5;
            border-radius: .5rem;
            background: #fff;
            padding: .85rem;
        }

        .account-review-prompt__image {
            width: 4.5rem;
            aspect-ratio: 2 / 3;
            object-fit: contain;
            border-radius: .35rem;
            background: #f7f8fb;
        }

        .account-review-card {
            border: 1px solid #edf0f5;
            border-radius: .5rem;
            background: #fff;
            padding: 1rem;
        }

        .account-review-card__layout {
            display: grid;
            grid-template-columns: 4.75rem minmax(0, 1fr) auto;
            gap: .95rem;
            align-items: flex-start;
        }

        .account-review-card__cover {
            display: block;
            width: 4.75rem;
        }

        .account-review-card__image {
            display: block;
            width: 4.75rem;
            aspect-ratio: 2 / 3;
            border-radius: .35rem;
            background: #f7f8fb;
            object-fit: contain;
        }

        .account-review-card__head {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: .75rem;
            margin-bottom: .75rem;
        }

        .account-review-card__product {
            color: #373f50;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .account-review-card__meta {
            color: #7d879c;
            font-size: .86rem;
            line-height: 1.45;
        }

        .account-review-card__signals {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .35rem .55rem;
            margin-top: .35rem;
        }

        .account-review-badge {
            display: inline-flex;
            align-items: center;
            min-height: 1.45rem;
            border-radius: 999px;
            font-size: .74rem;
            font-weight: 700;
            line-height: 1;
            padding: .28rem .5rem;
        }

        .account-review-badge--verified {
            background: rgba(66, 214, 151, .12);
            color: #198754;
        }

        .account-review-badge--helpful {
            background: #f6f7fb;
            color: #5b6680;
        }

        .account-review-card__title {
            color: #373f50;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.35;
            margin-bottom: .4rem;
        }

        .account-review-card__message {
            color: #4b566b;
            font-size: .96rem;
            line-height: 1.58;
            margin-bottom: .75rem;
        }

        .account-review-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
            line-height: 1;
            padding: .42rem .65rem;
        }

        .account-review-status--approved {
            background: rgba(66, 214, 151, .16);
            color: #12885a;
        }

        .account-review-status--pending {
            background: rgba(255, 193, 7, .18);
            color: #936100;
        }

        .account-review-card__details {
            display: grid;
            gap: .35rem;
            color: #5b6680;
            font-size: .9rem;
            line-height: 1.5;
            margin-bottom: .75rem;
        }

        .account-review-card__details strong {
            color: #373f50;
        }

        .account-review-tags {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            margin-bottom: .75rem;
        }

        .account-review-tag {
            border-radius: 999px;
            background: #f6f7fb;
            color: #5b6680;
            font-size: .76rem;
            font-weight: 700;
            line-height: 1;
            padding: .32rem .55rem;
        }

        .account-review-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding-top: .75rem;
            border-top: 1px solid #edf0f5;
        }

        @media (max-width: 767.98px) {
            .account-review-summary-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .account-review-card__layout {
                grid-template-columns: 3.75rem minmax(0, 1fr);
                gap: .8rem;
            }

            .account-review-card__cover,
            .account-review-card__image {
                width: 3.75rem;
            }

            .account-review-card__status {
                grid-column: 2;
                grid-row: 1;
                justify-self: flex-start;
                margin-top: 2rem;
            }

            .account-review-prompt {
                grid-template-columns: 3.75rem minmax(0, 1fr);
            }

            .account-review-prompt .btn {
                grid-column: 1 / -1;
                width: 100%;
            }

            .account-review-prompt__image {
                width: 3.75rem;
            }
        }
    </style>
@endpush

@section('content')

    @include('front.customer.layouts.header')

    <section class="account-page pb-5 mb-2 mb-md-4">
        <div class="row account-layout g-4">
            @include('front.customer.layouts.sidebar')

            <section class="col-lg-8 col-xl-9 account-content-column">
                <div class="account-content-card">
                    <div class="account-card-header">
                        <div class="account-card-titlewrap">
                            <span class="account-card-icon"><i class="ci-star"></i></span>
                            <div>
                                <h2 class="account-card-title">Moji dojmovi</h2>
                                <p class="account-card-subtitle">Pratite svoje dojmove, status odobrenja i knjige koje još čekaju vašu preporuku.</p>
                            </div>
                        </div>
                        <form action="{{ route('logout') }}" method="POST" class="mb-0">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm account-logout-button">
                                <i class="ci-sign-out me-2"></i>Odjava
                            </button>
                        </form>
                    </div>

                    @include('front.layouts.partials.session')

                    <div class="account-review-summary-grid">
                        <div class="account-review-summary-item">
                            <div class="account-review-summary-label">Objavljeni dojmovi</div>
                            <div class="account-review-summary-value">{{ $approvedReviewsCount }}</div>
                        </div>
                        <div class="account-review-summary-item">
                            <div class="account-review-summary-label">Čekaju odobrenje</div>
                            <div class="account-review-summary-value">{{ $pendingReviewsCount }}</div>
                        </div>
                        <div class="account-review-summary-item">
                            <div class="account-review-summary-label">Loyalty nagrada</div>
                            <div class="account-review-summary-value">{{ \App\Models\Back\Marketing\Review::rewardPoints() }} bodova</div>
                        </div>
                    </div>

                    @if($pendingProducts->count())
                        <div class="account-section">
                            <h2 class="account-section-title"><i class="ci-book"></i>Čekaju vaš dojam</h2>
                            <div class="account-review-prompt-grid">
                                @foreach($pendingProducts as $orderProduct)
                                    @php
                                        $product = $orderProduct->real;
                                        $catalogProduct = $orderProduct->product;
                                        $productUrl = $product && filled($product->url) ? url($product->url) : null;
                                        $productImage = $product && filled($product->image) ? (string) $product->image : (string) optional($catalogProduct)->image;
                                        $productImage = $accountReviewImageUrl($productImage);
                                    @endphp

                                    <div class="account-review-prompt">
                                        <img class="account-review-prompt__image" src="{{ $productImage ?: $accountReviewFallbackImage }}" alt="Naslovnica knjige {{ $orderProduct->name }}">
                                        <div class="min-w-0">
                                            <div class="account-review-card__product">{{ $orderProduct->name }}</div>
                                            <div class="account-review-card__meta">Pomozite drugim čitateljima kratkim dojmom.</div>
                                        </div>
                                        @if($productUrl)
                                            <a class="btn btn-sm btn-primary" href="{{ $productUrl }}#review-form">Podijeli dojam</a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="account-section mb-0">
                        <h2 class="account-section-title"><i class="ci-star-filled"></i>Vaši dojmovi</h2>

                        @forelse($reviews as $review)
                            @php
                                $reviewProduct = $review->product;
                                $reviewUrl = $reviewProduct && filled($reviewProduct->url) ? url($reviewProduct->url) . '#reviews' : null;
                                $reviewProductImage = $reviewProduct && filled($reviewProduct->thumb)
                                    ? (string) $reviewProduct->thumb
                                    : (string) optional($reviewProduct)->image;
                                $reviewProductImage = $accountReviewImageUrl($reviewProductImage);

                                $reviewTagLabels = $review->tagLabels();
                                $isVerifiedPurchase = $review->isVerifiedPurchase();
                            @endphp

                            <article class="account-review-card mb-3">
                                <div class="account-review-card__layout">
                                    @if($reviewUrl)
                                        <a class="account-review-card__cover" href="{{ $reviewUrl }}" aria-label="Pogledaj knjigu {{ $reviewProduct->name }}">
                                            <img class="account-review-card__image" src="{{ $reviewProductImage ?: $accountReviewFallbackImage }}" alt="Naslovnica knjige {{ $reviewProduct->name }}">
                                        </a>
                                    @else
                                        <span class="account-review-card__cover">
                                            <img class="account-review-card__image" src="{{ $reviewProductImage ?: $accountReviewFallbackImage }}" alt="Naslovnica knjige">
                                        </span>
                                    @endif

                                    <div class="min-w-0">
                                        <div class="account-review-card__head">
                                            <div>
                                                @if($reviewUrl)
                                                    <a class="account-review-card__product" href="{{ $reviewUrl }}">{{ $reviewProduct->name }}</a>
                                                @else
                                                    <div class="account-review-card__product">{{ $reviewProduct->name ?? 'Proizvod više nije dostupan' }}</div>
                                                @endif
                                                <div class="account-review-card__meta">
                                                    {{ \Illuminate\Support\Carbon::make($review->created_at)->format('d.m.Y') }} · Ocjena {{ (int) $review->stars }}/5
                                                    @if($review->has_spoilers)
                                                        · Sadrži spoilere
                                                    @endif
                                                </div>
                                                @if($isVerifiedPurchase || (int) $review->helpful_count > 0)
                                                    <div class="account-review-card__signals">
                                                        @if($isVerifiedPurchase)
                                                            <span class="account-review-badge account-review-badge--verified"><i class="ci-check-circle me-1"></i>Provjerena kupnja</span>
                                                        @endif
                                                        @if((int) $review->helpful_count > 0)
                                                            <span class="account-review-badge account-review-badge--helpful"><i class="ci-thumb-up me-1"></i>{{ (int) $review->helpful_count }} korisno</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        @if(filled($review->title))
                                            <div class="account-review-card__title">{{ $review->title }}</div>
                                        @endif

                                        <p class="account-review-card__message">{{ strip_tags($review->message) }}</p>

                                        @if(filled($review->recommended_for) || filled($review->liked_most))
                                            <div class="account-review-card__details">
                                                @if(filled($review->recommended_for))
                                                    <div><strong>Preporuka:</strong> {{ $review->recommended_for }}</div>
                                                @endif
                                                @if(filled($review->liked_most))
                                                    <div><strong>Najviše se svidjelo:</strong> {{ $review->liked_most }}</div>
                                                @endif
                                            </div>
                                        @endif

                                        @if(count($reviewTagLabels))
                                            <div class="account-review-tags">
                                                @foreach($reviewTagLabels as $tagLabel)
                                                    <span class="account-review-tag">{{ $tagLabel }}</span>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="account-review-actions">
                                            <span class="account-review-card__meta">Bodovi se dodjeljuju nakon odobrenja, do {{ \App\Models\Back\Marketing\Review::monthlyLimit() }} mjesečno.</span>
                                            @if($reviewUrl)
                                                <a class="btn btn-sm btn-outline-primary" href="{{ $reviewUrl }}">Pogledaj na knjizi</a>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="account-review-card__status">
                                        @if($review->status)
                                            <span class="account-review-status account-review-status--approved">Objavljeno</span>
                                        @else
                                            <span class="account-review-status account-review-status--pending">Čeka odobrenje</span>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="account-empty-state">
                                <div>
                                    <i class="ci-star d-block fs-3 mb-3 text-muted"></i>
                                    <div>Još niste podijelili nijedan dojam.</div>
                                    <a class="btn btn-primary btn-sm mt-3" href="{{ route('moje-narudzbe') }}">Pogledaj narudžbe</a>
                                </div>
                            </div>
                        @endforelse

                        {{ $reviews->links() }}
                    </div>
                </div>
            </section>
        </div>
    </section>

@endsection
