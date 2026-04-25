@extends('front.layouts.app')

@php
    $pageTitle = $giftGuide['title'] ?? 'Tražiš poklon?';
    $seoTitle = \App\Models\Seo::appendBrand($giftGuide['seo_title'] ?? $pageTitle);
    $seoDescription = \App\Models\Seo::description(null, $giftGuide['seo_description'] ?? 'Odaberi za koga tražiš poklon i pregledaj preporučene knjige i poklone.');
@endphp

@section('title', $seoTitle)
@section('description', $seoDescription)
@section('seo_image', $giftGuide['seo_image'] ?? 'media/img/category/gift-program.png')
@section('seo_image_alt', $pageTitle)

@section('content')
    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $pageTitle }}</li>
        </ol>
    </nav>

    <section class="gift-guide-hero mb-4">
        <div class="gift-guide-shell">
            <p class="gift-guide-shell__eyebrow mb-2">{{ $giftGuide['title'] ?? 'Tražiš poklon?' }}</p>
            <h1 class="gift-guide-shell__title mb-3">Odaberi za koga tražiš poklon</h1>
            <p class="gift-guide-shell__lead mb-2">{{ $giftGuide['lead'] ?? '' }}</p>
            <p class="gift-guide-shell__body mb-0">{{ $giftGuide['body'] ?? '' }}</p>

            @if (! empty($activeRecipient))
                <div class="gift-guide-shell__current">
                    Trenutno pregledavaš:
                    <strong>{{ $activeRecipient['heading'] ?? $activeRecipient['title'] }}</strong>
                </div>
            @endif

            <div class="gift-guide-selector">
                @foreach ($recipients as $recipient)
                    <a
                        href="{{ route('savjeti.za.poklone', ['recipient' => $recipient['slug']]) }}"
                        class="gift-guide-recipient{{ ($activeRecipient['slug'] ?? null) === $recipient['slug'] ? ' is-active' : '' }}"
                    >
                        <span class="gift-guide-recipient__title">{{ $recipient['title'] }}</span>
                        <span class="gift-guide-recipient__meta">{{ $recipient['meta'] }}</span>
                    </a>
                @endforeach
            </div>

            @if ($categoryLinks->isNotEmpty())
                <div class="gift-guide-category-pills">
                    @foreach ($categoryLinks as $categoryLink)
                        <a href="{{ $categoryLink['url'] }}" class="gift-guide-category-pill">{{ $categoryLink['title'] }}</a>
                    @endforeach
                </div>
            @endif

        </div>
    </section>

    <section class="gift-guide-products">
        <div class="gift-guide-products__intro">
            <p class="gift-guide-products__eyebrow mb-2">{{ $activeRecipient['title'] ?? 'Preporuke' }}</p>
            <h2 class="gift-guide-products__title mb-2">Preporučeni naslovi za lakši izbor</h2>
            <p class="gift-guide-products__body mb-0">{{ $activeRecipient['description'] ?? 'Odabrali smo kombinaciju najtraženijih naslova i kategorija za ovu publiku.' }}</p>
        </div>

        <products-view ids="{{ $ids }}" default-sort="popular"></products-view>
    </section>
@endsection

@push('css_after')
    <style>
        .gift-guide-hero {
            display: block;
        }

        .gift-guide-shell {
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            border: 1px solid rgba(223, 229, 238, 0.9);
            box-shadow: 0 0.2rem 0.65rem rgba(53, 56, 74, 0.04);
        }

        .gift-guide-shell {
            padding: 0.95rem 1rem 1rem;
            color: #2b2f45;
            background: linear-gradient(135deg, #ffffff 0%, #fbfbfd 58%, #fff6fa 100%);
        }

        .gift-guide-shell::before {
            content: none;
        }

        .gift-guide-shell > * {
            position: relative;
            z-index: 1;
        }

        .gift-guide-shell__eyebrow,
        .gift-guide-products__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.72rem;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .gift-guide-shell__eyebrow {
            background: rgba(229, 0, 119, 0.08);
            color: #cb2875;
        }

        .gift-guide-shell__title {
            color: #24273d;
            font-size: clamp(1.4rem, 2vw, 1.95rem);
            line-height: 1.08;
            letter-spacing: -0.03em;
        }

        .gift-guide-shell__lead {
            color: #30344b;
            font-size: 0.98rem;
            font-weight: 700;
            line-height: 1.55;
        }

        .gift-guide-shell__body {
            color: #676c80;
            font-size: 0.98rem;
            line-height: 1.6;
        }

        .gift-guide-shell__current {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin-top: 0.7rem;
            padding: 0.45rem 0.72rem;
            border-radius: 999px;
            background: rgba(75, 86, 107, 0.08);
            color: #4b566b;
            font-size: 0.9rem;
        }

        .gift-guide-selector {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.55rem;
            margin-top: 0.85rem;
        }

        .gift-guide-recipient {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.25rem;
            min-width: 0;
            min-height: 5.75rem;
            padding: 0.75rem 0.85rem;
            border-radius: 0.85rem;
            border: 1px solid #e1e7ef;
            background: rgba(255, 255, 255, 0.84);
            color: #2b2f45;
            text-decoration: none;
            transition: transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
        }

        .gift-guide-recipient:hover {
            transform: translateY(-0.125rem);
            color: #2b2f45;
            border-color: rgba(229, 0, 119, 0.2);
            background: #fff;
        }

        .gift-guide-recipient.is-active {
            border-color: rgba(229, 0, 119, 0.22);
            background: rgba(255, 243, 248, 0.92);
            box-shadow: 0 0.14rem 0.45rem rgba(229, 0, 119, 0.06);
        }

        .gift-guide-recipient__title {
            min-width: 0;
            color: #2b2f45;
            font-size: 0.95rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .gift-guide-recipient.is-active .gift-guide-recipient__title {
            color: #c71f70;
        }

        .gift-guide-recipient__meta {
            color: #6d7287;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .gift-guide-category-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.75rem;
        }

        .gift-guide-category-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.45rem 0.72rem;
            border-radius: 999px;
            border: 1px solid #dfe5ee;
            background: rgba(255, 255, 255, 0.88);
            color: #4a5064;
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1.2;
            text-decoration: none;
        }

        .gift-guide-category-pill:hover {
            color: #b60062;
            background: #fff6fa;
            border-color: rgba(229, 0, 119, 0.18);
        }

        .gift-guide-products__intro {
            margin-bottom: 1.25rem;
        }

        .gift-guide-products__eyebrow {
            background: rgba(229, 0, 119, 0.08);
            color: #cb2875;
        }

        .gift-guide-products__title {
            color: #21243a;
            font-size: clamp(1.5rem, 2.2vw, 2rem);
            line-height: 1.12;
            letter-spacing: -0.03em;
        }

        .gift-guide-products__body {
            color: #64667a;
            font-size: 1rem;
            line-height: 1.6;
        }

        @media only screen and (min-width: 1200px) {
            .gift-guide-shell__title {
                white-space: nowrap;
            }
        }

        @media only screen and (max-width: 1199px) {
            .gift-guide-selector {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media only screen and (max-width: 767px) {
            .gift-guide-shell {
                padding: 0.9rem;
                border-radius: 0.95rem;
            }

            .gift-guide-shell__title {
                font-size: clamp(1.3rem, 7vw, 1.7rem);
            }

            .gift-guide-selector {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .gift-guide-recipient {
                min-height: 5.25rem;
            }
        }

        @media only screen and (max-width: 575px) {
            .gift-guide-selector {
                grid-template-columns: 1fr;
            }

            .gift-guide-recipient {
                min-height: auto;
            }
        }
    </style>
@endpush
