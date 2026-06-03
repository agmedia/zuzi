<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    @php
        $seoTitle = \App\Models\Seo::title(trim($__env->yieldContent('title')));
        $seoDescription = \App\Models\Seo::description(trim($__env->yieldContent('description')));
        $seoCanonical = trim($__env->yieldContent('canonical')) ?: \App\Models\Seo::canonical(request());
        $seoRobots = trim($__env->yieldContent('robots')) ?: \App\Models\Seo::robots(request());
        $seoImage = \App\Models\Seo::image(trim($__env->yieldContent('seo_image')));
        $seoImageAlt = \App\Models\Seo::title(trim($__env->yieldContent('seo_image_alt')) ?: $seoTitle);
        $seoType = trim($__env->yieldContent('og_type')) ?: \App\Models\Seo::ogType(request());
        $seoPublishedTime = trim($__env->yieldContent('seo_published_time'));
        $seoUpdatedTime = trim($__env->yieldContent('seo_updated_time'));
    @endphp

    <title>{{ $seoTitle }}</title>
    <!-- SEO Meta Tags-->
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="author" content="Zuzi Shop">
    <meta name="robots" content="{{ $seoRobots }}">
    <link rel="canonical" href="{{ $seoCanonical }}" />
    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="{{ $seoType }}" />
    <meta property="og:title" content="{{ $seoTitle }}" />
    <meta property="og:description" content="{{ $seoDescription }}" />
    <meta property="og:url" content="{{ $seoCanonical }}" />
    <meta property="og:site_name" content="ZUZI Shop" />
    <meta property="og:image" content="{{ $seoImage }}" />
    <meta property="og:image:secure_url" content="{{ $seoImage }}" />
    <meta property="og:image:alt" content="{{ $seoImageAlt }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $seoTitle }}" />
    <meta name="twitter:description" content="{{ $seoDescription }}" />
    <meta name="twitter:image" content="{{ $seoImage }}" />
    <meta name="twitter:image:alt" content="{{ $seoImageAlt }}" />
    @if($seoPublishedTime)
        <meta property="article:published_time" content="{{ $seoPublishedTime }}" />
    @endif
    @if($seoUpdatedTime)
        <meta property="og:updated_time" content="{{ $seoUpdatedTime }}" />
        @if($seoType === 'article')
            <meta property="article:modified_time" content="{{ $seoUpdatedTime }}" />
        @endif
    @endif
    @stack('meta_tags')
    <!-- Viewport-->
    <meta name="viewport" content="width=device-width, user-scalable=no" />
    <meta name="google-site-verification" content="Iq6KaWRTW8e-u9BG8MKUjITt4_fVi92rZl8E5Dyrx-0" />

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="stylesheet" media="screen" href="{{ asset('vendor/simplebar/dist/simplebar.min.css') }}"/>
    <link rel="stylesheet" media="screen" href="{{ asset('css/theme.css?v=6.0035') }}">
    <script src="{{ asset('js/jquery/jquery-2.1.1.min.js') }}"></script>


    <!-- Favicon and Touch Icons-->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('media/img/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('media/img/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('media/img/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('media/img/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('media/img/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('media/img/favicon-16x16.png') }}">
    <link rel="mask-icon" href="{{ asset('media/img/safari-pinned-tab.svg') }}" color="#e50077">
    <meta name="msapplication-TileColor" content="#e50077">
    <meta name="theme-color" content="#ffffff">



    @stack('css_after')
    @include('front.layouts.partials.cookie-consent-head')

    <style>
        .spinner {
            width: 40px;
            height: 40px;
            margin: 100px auto;
            background-color: #333;

            border-radius: 100%;
            -webkit-animation: sk-scaleout 1.0s infinite ease-in-out;
            animation: sk-scaleout 1.0s infinite ease-in-out;
        }

        .impersonation-banner {
            position: sticky;
            top: 0;
            z-index: 1060;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .75rem;
            padding: .75rem 1rem;
            border-bottom: 1px solid rgba(95, 67, 0, .18);
            background: #fff4d5;
            color: #5f4300;
            font-size: .875rem;
            line-height: 1.35;
            text-align: center;
        }

        .impersonation-banner strong {
            color: #332300;
        }

        .impersonation-banner form {
            margin: 0;
        }

        @media (max-width: 575.98px) {
            .impersonation-banner {
                flex-direction: column;
                gap: .5rem;
            }
        }

        @media screen and (-webkit-min-device-pixel-ratio:0) {
            .form-control {
                font-size: 16px;
            }
        }


        @-webkit-keyframes sk-scaleout {
            0% { -webkit-transform: scale(0) }
            100% {
                -webkit-transform: scale(1.0);
                opacity: 0;
            }
        }

        @keyframes sk-scaleout {
            0% {
                -webkit-transform: scale(0);
                transform: scale(0);
            } 100% {
                  -webkit-transform: scale(1.0);
                  transform: scale(1.0);
                  opacity: 0;
              }
        }
        [v-cloak] .v-cloak--block {
            display: block;
        }
        [v-cloak] .v-cloak--inline {
            display: inline;
        }
        [v-cloak] .v-cloak--inlineBlock {
            display: inline-block;
        }
        [v-cloak] .v-cloak--hidden {
            display: none;
        }
        [v-cloak] .v-cloak--invisible {
            visibility: hidden;
        }
        .v-cloak--block,
        .v-cloak--inline,
        .v-cloak--inlineBlock {
            display: none;
        }

        .product-bogo-line {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            max-width: 100%;
            min-height: 1.25rem;
            margin-top: .25rem;
            outline: 0;
            cursor: help;
        }

        .product-bogo-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.85rem;
            height: 1.25rem;
            padding: 0 .45rem;
            border-radius: 999px;
            background: #2f3747;
            color: #fff;
            font-size: .7rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: 0;
            white-space: nowrap;
        }

        .product-bogo-text {
            color: #7d879c;
            font-size: .75rem;
            line-height: 1.1;
            white-space: nowrap;
        }

        .product-bogo-info {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1rem;
            height: 1rem;
            border-radius: 999px;
            background: rgba(229, 0, 119, .1);
            color: #e50077;
            font-size: .68rem;
            font-weight: 800;
            line-height: 1;
        }

        .product-bogo-line::before,
        .product-bogo-line::after {
            position: absolute;
            left: 0;
            opacity: 0;
            pointer-events: none;
            transition: opacity .16s ease, transform .16s ease;
            z-index: 25;
        }

        .product-bogo-line::before {
            content: "";
            bottom: calc(100% + .28rem);
            width: .65rem;
            height: .65rem;
            margin-left: .65rem;
            background: #2f3747;
            transform: translateY(.22rem) rotate(45deg);
        }

        .product-bogo-line::after {
            content: attr(data-bogo-tooltip);
            bottom: calc(100% + .55rem);
            width: 14rem;
            max-width: min(14rem, 72vw);
            padding: .68rem .76rem;
            border-radius: .5rem;
            background: #2f3747;
            color: #fff;
            box-shadow: 0 .75rem 1.65rem rgba(43, 52, 69, .22);
            font-size: .76rem;
            font-weight: 600;
            line-height: 1.28;
            text-align: left;
            white-space: normal;
            transform: translateY(.22rem);
        }

        .product-bogo-line:hover::before,
        .product-bogo-line:hover::after,
        .product-bogo-line:focus::before,
        .product-bogo-line:focus::after {
            opacity: 1;
            transform: translateY(0) rotate(45deg);
        }

        .product-bogo-line:hover::after,
        .product-bogo-line:focus::after {
            transform: translateY(0);
        }

        .page-carousel-widget {
            position: relative;
        }

        .page-carousel-widget--background {
            margin-top: 1.25rem;
            padding: 2.15rem 1.35rem 1.9rem !important;
            border: 1px solid rgba(229, 0, 119, 0.14);
            border-radius: .75rem;
            background: #fff6fb;
            box-shadow: 0 1rem 2.5rem rgba(43, 52, 69, .09), inset 0 0 0 1px rgba(255, 255, 255, .72);
            overflow: hidden;
        }

        .page-carousel-widget--background::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: .25rem;
            background: linear-gradient(90deg, #e50077 0%, #ff8abc 42%, #73c7f7 100%);
        }

        .page-carousel-widget--background::after {
            content: "";
            position: absolute;
            top: .25rem;
            bottom: 0;
            left: 0;
            width: .28rem;
            background: #e50077;
            opacity: .7;
        }

        .page-carousel-widget--container:not(.page-carousel-widget--background) {
            padding: 1.5rem 1.25rem !important;
            border: 1px solid rgba(43, 52, 69, .08);
            border-radius: .75rem;
            background: #fff;
            box-shadow: 0 .75rem 2rem rgba(43, 52, 69, .08);
        }

        .page-carousel-widget__header {
            gap: 1rem;
        }

        .review-widget-heading {
            min-width: 0;
        }

        .review-widget-cta {
            display: inline-flex;
            align-items: center;
        }

        .review-widget-cta .btn {
            border-radius: 999px;
            white-space: nowrap;
        }

        .review-widget-quote {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            height: 100%;
            margin-bottom: 0;
        }

        .review-widget-card {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            position: relative;
            height: auto !important;
            min-height: 0 !important;
            padding: 1.35rem 1.25rem 1.2rem;
            border: 1px solid rgba(var(--cz-primary-rgb), .08) !important;
            border-radius: .65rem;
            background: #fff;
            overflow: hidden;
            transition: transform .18s ease, border-color .18s ease;
        }

        .review-widget-card::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: .22rem;
            border-radius: .65rem .65rem 0 0;
            background: rgba(var(--cz-primary-rgb), .08);
        }

        .review-widget-card:hover {
            transform: translateY(-2px);
            border-color: rgba(var(--cz-primary-rgb), .12) !important;
        }

        .review-widget-product-head {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            margin-bottom: .75rem;
        }

        .review-widget-product-image-link {
            display: block !important;
            flex: 0 0 20% !important;
            width: 20% !important;
            max-width: 3.5rem !important;
            min-width: 2.25rem;
        }

        .review-widget-product-image {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            height: auto !important;
            aspect-ratio: 2 / 3;
            object-fit: contain;
            border-radius: .35rem;


        }

        .review-widget-product-copy {
            flex: 1 1 auto;
            min-width: 0;
            padding-top: .1rem;
        }

        .review-widget-title {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 0;
            color: #e50077;
            font-weight: 700;
            line-height: 1.4;
        }

        .review-widget-title.mb-2 {
            margin-bottom: .35rem !important;
        }

        .review-widget-title:hover {
            color: #c80068;
        }

        .review-widget-stars .star-rating {
            line-height: 1;
            white-space: nowrap;
        }

        .review-widget-stars .star-rating-icon {
            margin-right: .1rem;
            color: #f59f56;
        }

        .review-widget-message {
            display: -webkit-box !important;
            -webkit-line-clamp: 8;
            -webkit-box-orient: vertical;
            overflow: hidden !important;
            color: #4b566b;
            line-height: 1.6;
            height: auto !important;
            max-height: 12.8em !important;
            min-height: 0;
            text-overflow: ellipsis;
        }

        .review-widget-card-footer {
            gap: .75rem;
            margin-top: 1.15rem !important;
            border-top: 1px solid rgba(var(--cz-primary-rgb), .1);
        }

        .review-widget-author {
            flex: 1 1 0;
            min-width: 0;
            color: #373f50;
            font-weight: 700;
        }

        .review-widget-author i {
            color: #e50077;
        }

        .review-widget-link {
            color: #e50077;
            font-size: inherit;
            font-weight: 700;
            line-height: 1.2;
            text-align: right;
            white-space: nowrap;
        }

        .review-widget-link:hover {
            color: #c80068;
        }

        .review-widget-masonry {
            column-count: 4;
            column-gap: 1.25rem;
        }

        .review-widget-masonry-item {
            display: inline-block;
            width: 100%;
            margin-bottom: 1rem;
            break-inside: avoid;
        }

        @media (max-width: 767.98px) {
            .page-carousel-widget--background,
            .page-carousel-widget--container:not(.page-carousel-widget--background) {
                margin-right: -.25rem;
                margin-left: -.25rem;
                padding-right: .95rem !important;
                padding-left: .95rem !important;
            }

            .review-widget-cta {
                width: 100%;
                justify-content: stretch;
            }

            .review-widget-cta .btn {
                width: 100%;
            }

            .review-widget-card {
                height: auto !important;
                min-height: 0 !important;
            }

            .review-widget-masonry {
                column-count: 1;
            }
        }

    </style>
    @if (config('app.env') == 'production')
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            window.cookieAnalyticsAllowed = false;
            window.cookieMarketingAllowed = false;
            window.canTrackAnalytics = () => false;

            function getStoredCookieConsent() {
                const match = document.cookie.match(/(?:^|;\s*)cc_cookie=([^;]+)/);

                if (!match) {
                    return null;
                }

                try {
                    return JSON.parse(decodeURIComponent(match[1]));
                } catch (error) {
                    return null;
                }
            }

            window.applyGooglePrivacySettings = function (marketingGranted) {
                const marketingAllowed = marketingGranted === true;

                gtag('set', 'ads_data_redaction', ! marketingAllowed);
                gtag('set', 'allow_google_signals', marketingAllowed);
                gtag('set', 'allow_ad_personalization_signals', marketingAllowed);
            };

            window.updateGoogleConsentFromCookie = function (analyticsGranted, marketingGranted) {
                window.cookieAnalyticsAllowed = analyticsGranted === true;
                window.cookieMarketingAllowed = marketingGranted === true;
                window.applyGooglePrivacySettings(marketingGranted);

                gtag('consent', 'update', {
                    analytics_storage: analyticsGranted ? 'granted' : 'denied',
                    ad_storage: marketingGranted ? 'granted' : 'denied',
                    ad_user_data: marketingGranted ? 'granted' : 'denied',
                    ad_personalization: marketingGranted ? 'granted' : 'denied'
                });
            };
            gtag('consent', 'default', {
                analytics_storage: 'denied',
                ad_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied',
                wait_for_update: 500
            });
            window.applyGooglePrivacySettings(false);

            const storedConsent = getStoredCookieConsent();

            if (storedConsent && Array.isArray(storedConsent.categories)) {
                window.updateGoogleConsentFromCookie(
                    storedConsent.categories.includes('analytics'),
                    storedConsent.categories.includes('marketing')
                );
            }
        </script>

        @yield('google_data_layer')

        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','GTM-M6Q5GRCN');</script>
        <!-- End Google Tag Manager -->
        <!-- Google Tag Manager -->

        <!-- Global site tag (gtag.js) - Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.measurement_id') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', '{{ config('services.google_analytics.measurement_id') }}', {
                allow_google_signals: window.cookieMarketingAllowed === true,
                allow_ad_personalization_signals: window.cookieMarketingAllowed === true
            });
        </script>


    @endif



</head>

<!-- Body-->
<body class="bg-bck">
@if (config('app.env') == 'production')
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-M6Q5GRCN"
                      height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
@endif
<div id="agapp">
    <div v-cloak>

        <div class="v-cloak--inline"> <!-- Parts that will be visible before compiled your HTML -->
            <div class="spinner"></div>
        </div>

        <div class="v-cloak--hidden">
            @if (auth()->check() && session('impersonator_id'))
                <div class="impersonation-banner" role="status">
                    <span>
                        Pregledavate račun kao
                        <strong>{{ auth()->user()->email }}</strong>
                        @if (session('impersonator_email'))
                            (admin: {{ session('impersonator_email') }})
                        @endif
                    </span>
                    <form action="{{ route('users.impersonate.stop') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning">Vrati me u admin</button>
                    </form>
                </div>
            @endif
            @include('front.layouts.partials.header')
            <main class="offcanvas-enabled ">
                <section class="ps-lg-4 pe-lg-3 pt-2 page-wrapper">
                    <div class="px-2 pt-2">
                        @yield('content')
                    </div>
                </section>

                @include('front.layouts.partials.footer')
                @include('front.layouts.partials.handheld')
            </main>
        </div>
    </div>
</div>


<!-- Back To Top Button-->
<a class="btn-scroll-top d-none d-md-block" aria-label="Scroll To Top" href="#top" data-scroll data-fixed-element><span class="btn-scroll-top-tooltip text-muted fs-sm me-2">Top</span><i class="btn-scroll-top-icon ci-arrow-up">   </i></a>

<!-- Sign in / sign up modal-->
@include('front.layouts.modals.login')
@include('front.layouts.partials.cookie-consent')



<!-- Vendor Styles including: Font Icons, Plugins, etc.-->
<link rel="stylesheet" media="screen" href="{{ asset('css/tiny-slider.css') }}?v=1.2"/>
<style>
    .tns-carousel,
    .tns-carousel .tns-ovh,
    .tns-carousel .tns-carousel-inner {
        touch-action: pan-y pinch-zoom;
    }

    .widget-card-carousel .tns-item {
        padding: 0.35rem 0.35rem 0.75rem;
    }
</style>
<!-- Vendor scrits: js libraries and plugins-->


<script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('js/simplebar.min.js') }}"></script>
<script src="{{ asset('js/tiny-slider.js?v=2.3') }}"></script>
<script src="{{ asset('js/smooth-scroll.polyfills.min.js') }}"></script>
<script src="{{ asset('js/imagesloaded/imagesloaded.pkgd.min.js') }}"></script>
<script src="{{ asset('js/shufflejs/dist/shuffle.min.js') }}"></script>

<script src="{{ asset('vendor/lightgallery/lightgallery.min.js') }}"></script>
<script src="{{ asset('vendor/lightgallery/plugins/fullscreen/lg-fullscreen.min.js') }}"></script>
<script src="{{ asset('vendor/lightgallery/plugins/zoom/lg-zoom.min.js') }}"></script>

<script>
    (function () {
        if (typeof window.lightGallery !== 'function') {
            return;
        }

        const originalLightGallery = window.lightGallery;

        window.lightGallery = function (element, options) {
            const galleryOptions = options || {};
            const galleryStrings = Object.assign(
                {
                    closeGallery: 'Zatvori galeriju',
                    toggleMaximize: 'Povecaj prikaz',
                    previousSlide: 'Prethodna slika',
                    nextSlide: 'Sljedeca slika',
                    download: 'Preuzmi',
                    playVideo: 'Pokreni video'
                },
                galleryOptions.strings || {},
                {
                    closeGallery: 'Zatvori galeriju'
                }
            );
            const mobileSettings = Object.assign(
                {
                    controls: false,
                    download: false,
                    showCloseIcon: true
                },
                galleryOptions.mobileSettings || {},
                {
                    showCloseIcon: true
                }
            );

            return originalLightGallery.call(this, element, Object.assign({}, galleryOptions, {
                mobileSettings: mobileSettings,
                strings: galleryStrings
            }));
        };

        Object.assign(window.lightGallery, originalLightGallery);
    })();
</script>


<!-- Main theme script-->
<script src="{{ asset('js/cart.js') }}?v={{ filemtime(public_path('js/cart.js')) }}"></script>

<script src="{{ asset('js/theme.min.js') }}"></script>







<script>
    $(() => {
        $('#search-input').on('keyup', (e) => {
            if (e.keyCode == 13) {
                e.preventDefault();
                $('search-form').submit();
            }
        })
    });
</script>

<script>
    const signinModal = document.getElementById('signin-modal');
    const recaptchaSiteKey = @json(config('services.recaptcha.sitekey'));
    const shouldOpenSigninModal = @json((bool) session('auth_status'));
    let recaptchaLoader = null;

    function loadRecaptchaScript() {
        if (!recaptchaSiteKey) {
            return Promise.resolve(null);
        }

        if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
            return Promise.resolve(window.grecaptcha);
        }

        if (recaptchaLoader) {
            return recaptchaLoader;
        }

        recaptchaLoader = new Promise((resolve, reject) => {
            const existingScript = document.querySelector('script[data-recaptcha-script="signin"]');

            if (existingScript) {
                existingScript.addEventListener('load', () => resolve(window.grecaptcha), { once: true });
                existingScript.addEventListener('error', reject, { once: true });

                return;
            }

            const script = document.createElement('script');
            script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(recaptchaSiteKey)}`;
            script.async = true;
            script.defer = true;
            script.dataset.recaptchaScript = 'signin';
            script.onload = () => resolve(window.grecaptcha);
            script.onerror = reject;

            document.head.appendChild(script);
        });

        return recaptchaLoader;
    }

    function refreshSigninRecaptchaToken() {
        const tokenInput = document.getElementById('recaptcha');

        if (!tokenInput || !window.grecaptcha || !recaptchaSiteKey) {
            return;
        }

        window.grecaptcha.ready(function() {
            window.grecaptcha.execute(recaptchaSiteKey, { action: 'register' }).then(function(token) {
                if (token) {
                    tokenInput.value = token;
                }
            });
        });
    }

    function showSigninModal(selectedTab = 'pills-signin-tab') {
        if (!signinModal) {
            return;
        }

        signinModal.dataset.initialTabId = selectedTab;
        bootstrap.Modal.getOrCreateInstance(signinModal).show();
    }

    if (signinModal) {
        signinModal.addEventListener('show.bs.modal', (ev) => {
            const invoker = ev.relatedTarget;
            const selectedTab = invoker
                ? invoker.getAttribute('data-tab-id')
                : signinModal.dataset.initialTabId;

            if (selectedTab) {
                const tabButton = document.querySelector(`#${selectedTab}`);

                if (tabButton) {
                    const tab = new bootstrap.Tab(tabButton);
                    tab.show();
                }
            }

            delete signinModal.dataset.initialTabId;

            loadRecaptchaScript()
                .then(() => {
                    refreshSigninRecaptchaToken();
                })
                .catch(() => {});
        });

        const url = new URL(window.location.href);
        const requestedAuthTab = url.searchParams.get('auth');

        if (requestedAuthTab === 'signin' || requestedAuthTab === 'signup' || shouldOpenSigninModal) {
            showSigninModal(requestedAuthTab === 'signup' ? 'pills-signup-tab' : 'pills-signin-tab');

            if (requestedAuthTab) {
                url.searchParams.delete('auth');
                window.history.replaceState({}, document.title, url.toString());
            }
        }
    }
</script>

<script>
    (function () {
        if (window.reviewWidgetLoadMoreBound) {
            return;
        }

        window.reviewWidgetLoadMoreBound = true;

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-review-load-more]');

            if (!button) {
                return;
            }

            var section = button.closest('.review-widget-section');
            var masonry = section ? section.querySelector('[data-review-masonry]') : null;

            if (!masonry) {
                return;
            }

            var batchSize = parseInt(masonry.getAttribute('data-review-batch') || '8', 10);
            var hiddenItems = Array.prototype.slice.call(masonry.querySelectorAll('[data-review-item][hidden]'));
            var nextItems = hiddenItems.slice(0, batchSize);

            nextItems.forEach(function (item) {
                item.removeAttribute('hidden');
            });

            if (hiddenItems.length <= batchSize) {
                button.parentElement.hidden = true;
            }

            if (nextItems[0]) {
                var targetTop = nextItems[0].getBoundingClientRect().top + window.pageYOffset;
                var desiredTop = Math.max(0, targetTop - 120);
                var delta = desiredTop - window.pageYOffset;

                window.scrollBy({
                    top: Math.max(-80, Math.min(delta, Math.round(window.innerHeight * 0.22))),
                    behavior: 'smooth'
                });
            }
        });
    })();
</script>

@stack('js_after')

</body>
</html>
