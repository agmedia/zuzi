@extends('front.layouts.app')

@section('title', $collection['meta_title'])
@section('description', $collection['meta_description'])

@push('css_after')
    <style>
        .curated-landing__hero {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 1rem;
            background: var(--curated-surface, linear-gradient(135deg, #fff5ea 0%, #fffaf5 100%));
            box-shadow: 0 0.15rem 0.5rem rgba(15, 23, 42, 0.04);
        }

        .curated-landing__hero::after {
            content: none;
        }

        .curated-landing__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.78);
            color: var(--curated-accent, #e50077);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .curated-landing__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 0.9rem;
        }

        .curated-landing__meta span {
            display: inline-flex;
            align-items: center;
            padding: 0.45rem 0.72rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            color: #344054;
            font-size: 0.88rem;
            font-weight: 600;
        }

        .curated-landing__title {
            font-size: 1.7rem;
            line-height: 1.1;
        }

        .curated-landing__lead,
        .curated-landing__body {
            max-width: 72rem;
            font-size: 0.98rem;
            line-height: 1.55;
        }

        .curated-landing__lead {
            color: #4b5563;
        }

        .curated-landing__body {
            color: #6b7280;
        }

        @media (max-width: 767.98px) {
            .curated-landing__hero {
                border-radius: 0.95rem;
            }

            .curated-landing__title {
                font-size: 1.45rem;
            }
        }
    </style>
@endpush

@section('content')
    <nav class="mb-3" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $collection['title'] }}</li>
        </ol>
    </nav>

    <section
        class="curated-landing__hero p-3 p-lg-4 mb-4"
        style="--curated-accent: {{ $collection['accent'] }}; --curated-surface: {{ $collection['surface'] }};"
    >
        <div class="position-relative" style="z-index: 1;">
            <span class="curated-landing__eyebrow">{{ $collection['badge'] }}</span>
            <h1 class="curated-landing__title h2 font-title mt-2 mb-2">{{ $collection['title'] }}</h1>
            <p class="curated-landing__lead fs-md mb-1">{{ $collection['lead'] }}</p>
            <p class="curated-landing__body fs-md mb-0">{{ $collection['body'] }}</p>

            <div class="curated-landing__meta">
                <span>{{ $collection['count_label'] }}</span>
                @if (! empty($collection['meta_pill']))
                    <span>{{ $collection['meta_pill'] }}</span>
                @endif
            </div>
        </div>
    </section>

    <products-view
        ids="{{ $collection['ids_json'] ?? '' }}"
        price-min="{{ $collection['price_min'] ?? '' }}"
        price-max="{{ $collection['price_max'] ?? '' }}"
        preserve-order="{{ ! empty($collection['preserve_order']) ? '1' : '' }}"
        default-sort="{{ $collection['default_sort'] ?? '' }}"
    ></products-view>
@endsection
