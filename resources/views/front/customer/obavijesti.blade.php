@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand('Obavijesti korisničkog računa'))
@section('description', \App\Models\Seo::description(null, 'Pregled obavijesti korisničkog računa na ' . \App\Models\Seo::brand() . '.'))
@php
    $purchaseRecommendationCarouselOptions = [
        'items' => 2,
        'gutter' => 16,
        'controls' => true,
        'nav' => true,
        'autoHeight' => false,
        'mouseDrag' => true,
        'touch' => true,
        'swipeAngle' => 30,
        'preventActionWhenRunning' => true,
        'preventScrollOnTouch' => 'auto',
        'responsive' => [
            0 => ['items' => 2, 'controls' => true, 'nav' => true],
            480 => ['items' => 2, 'controls' => true, 'nav' => true],
            720 => ['items' => 3],
            1140 => ['items' => 4],
        ],
    ];
@endphp

@push('css_after')
    <style>
        .purchase-recommendations-carousel .tns-ovh,
        .purchase-recommendations-carousel .tns-item,
        .purchase-recommendations-carousel .tns-carousel-inner {
            touch-action: pan-y pinch-zoom;
        }

        .purchase-recommendations-carousel .tns-item > div,
        .purchase-recommendations-carousel .article,
        .purchase-recommendations-carousel .product-card {
            display: flex;
        }

        .purchase-recommendations-carousel .tns-item > div,
        .purchase-recommendations-carousel .article,
        .purchase-recommendations-carousel .product-card {
            width: 100%;
        }

        .purchase-recommendations-carousel .tns-item > div,
        .purchase-recommendations-carousel .article,
        .purchase-recommendations-carousel .product-card {
            height: 100%;
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
                            <span class="account-card-icon"><i class="ci-announcement"></i></span>
                            <div>
                                <h2 class="account-card-title">Obavijesti za vaš račun</h2>
                                <p class="account-card-subtitle">Ovdje su važne poruke, pogodnosti i personalizirane ponude.</p>
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

                    @if($notice['active'])
                        <div class="account-notice-panel">
                            @if($notice['title'])
                                <h2 class="account-notice-panel__title mb-4">{{ $notice['title'] }}</h2>
                            @endif

                            @if($notice['intro'])
                                <p class="account-notice-panel__text mb-0">{!! nl2br(e($notice['intro'])) !!}</p>
                            @endif

                            <div class="account-notice-panel__coupon">
                                @if($notice['coupon_code'])
                                    <button
                                        type="button"
                                        class="account-notice-panel__copy-button"
                                        data-coupon-code="{{ $notice['coupon_code'] }}"
                                        aria-label="Kopiraj kupon kod {{ $notice['coupon_code'] }}"
                                        title="Kopiraj kod"
                                    >
                                        <span class="account-notice-panel__copy-icon" aria-hidden="true"></span>
                                    </button>
                                @endif
                                @if($notice['coupon_label'])
                                    <div class="account-notice-panel__coupon-label mb-3">{{ $notice['coupon_label'] }}</div>
                                @endif
                                @if($notice['coupon_code'])
                                    <button
                                        type="button"
                                        class="account-notice-panel__code account-notice-panel__code-button mb-3 js-account-coupon-code"
                                        data-coupon-code="{{ $notice['coupon_code'] }}"
                                        aria-label="Kopiraj kupon kod {{ $notice['coupon_code'] }}"
                                        title="Kopiraj kod"
                                    >
                                        <span>{{ $notice['coupon_code'] }}</span>
                                    </button>
                                @endif
                                @if($notice['discount_text'])
                                    <div class="account-notice-panel__discount">{{ $notice['discount_text'] }}</div>
                                @endif
                            </div>

                            @if($notice['outro'])
                                <p class="account-notice-panel__text mb-4">{!! nl2br(e($notice['outro'])) !!}</p>
                            @endif

                            @if($notice['button_text'] && $notice['button_url'])
                                <a
                                    class="btn btn-primary account-notice-panel__button"
                                    href="{{ $notice['button_url'] }}"
                                    @if($notice['coupon_code']) data-coupon-code="{{ $notice['coupon_code'] }}" data-coupon-navigate="true" @endif
                                >
                                    {{ $notice['button_text'] }}
                                </a>
                            @endif

                            @if($notice_valid_until)
                                <div class="account-notice-panel__date mt-4">
                                    Kupon vrijedi do: <strong>{{ $notice_valid_until }}</strong>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="account-empty-state">
                            <div>
                                <i class="ci-announcement d-block fs-3 mb-3 text-muted"></i>
                                <div>Trenutno nema novih obavijesti.</div>
                            </div>
                        </div>
                    @endif

                    @if(isset($purchaseRecommendations) && $purchaseRecommendations->count())
                        <section class="account-recommendations mt-5 pt-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                                <div>
                                    <h2 class="account-section-title mb-2"><i class="ci-star"></i>Preporuke za vas</h2>
                                    <p class="text-muted mb-0">Odabrali smo slične naslove koje bi vas mogli zanimati na temelju knjiga koje ste već kupovali.</p>
                                </div>
                            </div>

                            <div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled pt-2 purchase-recommendations-carousel">
                                <div class="tns-carousel-inner" data-carousel-options='@json($purchaseRecommendationCarouselOptions)'>
                                    @foreach ($purchaseRecommendations as $product)
                                        <div>
                                            @include('front.catalog.category.product', ['product' => $product])
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                    @endif
                </div>
            </section>
        </div>
    </section>

@endsection

@push('js_after')
    <script>
        (function () {
            const copyText = function (text) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                }

                return new Promise(function (resolve, reject) {
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    textArea.setAttribute('readonly', '');
                    textArea.style.position = 'absolute';
                    textArea.style.left = '-999999px';

                    document.body.prepend(textArea);
                    textArea.select();

                    try {
                        document.execCommand('copy') ? resolve() : reject();
                    } catch (error) {
                        reject(error);
                    } finally {
                        textArea.remove();
                    }
                });
            };

            const showToast = function (type, message) {
                if (type === 'success' && window.ToastSuccess) {
                    window.ToastSuccess.fire(message);
                    return;
                }

                if (window.ToastWarning) {
                    window.ToastWarning.fire(message);
                    return;
                }

                window.alert(message);
            };

            const resolveCopyResult = function (copyPromise) {
                return new Promise(function (resolve) {
                    let settled = false;
                    const finish = function (result) {
                        if (settled) {
                            return;
                        }

                        settled = true;
                        resolve(result);
                    };

                    copyPromise.then(function () {
                        finish(true);
                    }).catch(function () {
                        finish(false);
                    });

                    window.setTimeout(function () {
                        finish(false);
                    }, 800);
                });
            };

            const activateCoupon = function (code) {
                if (!window.axios) {
                    return Promise.resolve(null);
                }

                return window.axios.post('cart/coupon', { coupon: code }, { timeout: 5000 })
                    .then(function (response) {
                        return response.data;
                    })
                    .catch(function () {
                        return null;
                    });
            };

            const handleCoupon = function (event, trigger) {
                const code = String(trigger.dataset.couponCode || '').trim().toUpperCase();
                const shouldNavigate = trigger.dataset.couponNavigate === 'true';
                const navigateUrl = trigger.getAttribute('href');

                if (!code) {
                    return;
                }

                event.preventDefault();

                Promise.all([
                    resolveCopyResult(copyText(code)),
                    activateCoupon(code),
                ]).then(function (results) {
                    const copied = results[0];
                    const activation = results[1];

                    if (activation && activation.success) {
                        showToast('success', copied
                            ? 'Kod je kopiran i spremljen za košaricu.'
                            : (activation.message || 'Kod je spremljen za košaricu.')
                        );
                    } else if (copied) {
                        showToast('success', 'Kod je kopiran u međumemoriju.');
                    } else {
                        showToast('warning', 'Kod nije bilo moguće kopirati.');
                    }

                    if (shouldNavigate && navigateUrl) {
                        window.setTimeout(function () {
                            window.location.href = navigateUrl;
                        }, 450);
                    }
                });
            };

            const bindCouponTriggers = function () {
                document.querySelectorAll('[data-coupon-code]').forEach(function (trigger) {
                    if (trigger.dataset.couponBound === 'true') {
                        return;
                    }

                    trigger.dataset.couponBound = 'true';
                    trigger.addEventListener('click', function (event) {
                        handleCoupon(event, trigger);
                    });
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindCouponTriggers);
            } else {
                bindCouponTriggers();
            }

            window.setTimeout(bindCouponTriggers, 0);
            window.setTimeout(bindCouponTriggers, 500);
        })();
    </script>
@endpush
