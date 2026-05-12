@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand('Obavijesti korisničkog računa'))
@section('description', \App\Models\Seo::description(null, 'Pregled obavijesti korisničkog računa na ' . \App\Models\Seo::brand() . '.'))

@push('css_after')
    <style>
        .account-notice-card {
            background: #fff;
            border-radius: .5rem;
            box-shadow: 0 1rem 3rem rgba(43, 52, 69, .08);
            margin-left: auto;
            margin-right: auto;
            max-width: 780px;
            padding: 3rem 3.5rem;
            text-align: center;
        }

        .account-notice-card__title {
            color: #e50077;
            font-size: 1.9rem;
            font-weight: 700;
            letter-spacing: 0;
            line-height: 1.2;
        }

        .account-notice-card__text {
            color: #161616;
            font-size: 1.05rem;
            line-height: 1.45;
        }

        .account-notice-card__coupon {
            border: 3px dashed #e50077;
            margin: 2rem auto;
            max-width: 650px;
            padding: 1.9rem 1.5rem;
        }

        .account-notice-card__coupon-label {
            color: #161616;
            font-size: 1.05rem;
            line-height: 1.3;
        }

        .account-notice-card__code {
            color: #e50077;
            font-size: 2.35rem;
            font-weight: 700;
            letter-spacing: 0;
            line-height: 1.2;
        }

        .account-notice-card__discount {
            color: #161616;
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .account-notice-card__button {
            font-size: 1.05rem;
            font-weight: 700;
            min-width: 250px;
            padding-bottom: .95rem;
            padding-top: .95rem;
        }

        .account-notice-card__date {
            color: #5f6368;
            font-size: 1rem;
        }

        @media (max-width: 575.98px) {
            .account-notice-card {
                padding: 2rem 1.25rem;
            }

            .account-notice-card__title {
                font-size: 1.55rem;
            }

            .account-notice-card__text,
            .account-notice-card__coupon-label,
            .account-notice-card__discount,
            .account-notice-card__button {
                font-size: .95rem;
            }

            .account-notice-card__code {
                font-size: 1.9rem;
            }

            .account-notice-card__button {
                min-width: 0;
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')

    @include('front.customer.layouts.header')

    <section class="pb-5 mb-2 mb-md-4">
        <div class="row">
            @include('front.customer.layouts.sidebar')

            <section class="col-lg-8">
                <div class="d-none d-lg-flex justify-content-between align-items-center pt-lg-3 pb-4 pb-lg-5 mb-lg-3">
                    <h6 class="fs-base text-primary mb-0">Obavijesti za vaš račun:</h6>
                    <form action="{{ route('logout') }}" method="POST" class="mb-0">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ci-sign-out me-2"></i>Odjava
                        </button>
                    </form>
                </div>

                @include('front.layouts.partials.session')

                @if($notice['active'])
                    <div class="account-notice-card">
                        @if($notice['title'])
                            <h2 class="account-notice-card__title mb-4">{{ $notice['title'] }}</h2>
                        @endif

                        @if($notice['intro'])
                            <p class="account-notice-card__text mb-0">{!! nl2br(e($notice['intro'])) !!}</p>
                        @endif

                        <div class="account-notice-card__coupon">
                            @if($notice['coupon_label'])
                                <div class="account-notice-card__coupon-label mb-3">{{ $notice['coupon_label'] }}</div>
                            @endif
                            @if($notice['coupon_code'])
                                <div class="account-notice-card__code mb-3">{{ $notice['coupon_code'] }}</div>
                            @endif
                            @if($notice['discount_text'])
                                <div class="account-notice-card__discount">{{ $notice['discount_text'] }}</div>
                            @endif
                        </div>

                        @if($notice['outro'])
                            <p class="account-notice-card__text mb-4">{!! nl2br(e($notice['outro'])) !!}</p>
                        @endif

                        @if($notice['button_text'] && $notice['button_url'])
                            <a class="btn btn-primary account-notice-card__button" href="{{ $notice['button_url'] }}">
                                {{ $notice['button_text'] }}
                            </a>
                        @endif

                        @if($notice_valid_until)
                            <div class="account-notice-card__date mt-4">
                                Kupon vrijedi do: <strong>{{ $notice_valid_until }}</strong>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="alert alert-info d-flex" role="alert">
                        <div class="alert-icon">
                            <i class="ci-announcement"></i>
                        </div>
                        <div>Trenutno nema novih obavijesti.</div>
                    </div>
                @endif
            </section>
        </div>
    </section>

@endsection
