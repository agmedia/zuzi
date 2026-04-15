@extends('front.layouts.app')

@section('title', $collection['meta_title'])
@section('description', $collection['meta_description'])

@push('css_after')
    <style>
        .curated-landing__hero {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(15, 23, 42, 0.06);
            border-radius: 1.5rem;
            background: var(--curated-surface, linear-gradient(135deg, #fff5ea 0%, #fffaf5 100%));
            box-shadow: 0 1.2rem 3rem rgba(15, 23, 42, 0.08);
        }

        .curated-landing__hero::after {
            content: "";
            position: absolute;
            inset: auto -8% -40% auto;
            width: 18rem;
            height: 18rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.55);
            pointer-events: none;
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
            gap: 0.75rem;
            margin-top: 1.25rem;
        }

        .curated-landing__meta span {
            display: inline-flex;
            align-items: center;
            padding: 0.55rem 0.8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            color: #344054;
            font-size: 0.92rem;
            font-weight: 600;
        }

        @media (max-width: 767.98px) {
            .curated-landing__hero {
                border-radius: 1.1rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="container-xl px-0">
        <nav class="mb-3" aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap">
                <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $collection['title'] }}</li>
            </ol>
        </nav>

        <section
            class="curated-landing__hero p-4 p-lg-5 mb-4"
            style="--curated-accent: {{ $collection['accent'] }}; --curated-surface: {{ $collection['surface'] }};"
        >
            <div class="position-relative" style="z-index: 1;">
                <span class="curated-landing__eyebrow">{{ $collection['badge'] }}</span>
                <h1 class="display-6 font-title mt-3 mb-3">{{ $collection['title'] }}</h1>
                <p class="lead mb-2">{{ $collection['lead'] }}</p>
                <p class="text-muted mb-0" style="max-width: 52rem;">{{ $collection['body'] }}</p>

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
    </div>
@endsection
