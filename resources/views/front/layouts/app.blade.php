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
    <link rel="stylesheet" media="screen" href="{{ asset('css/theme.css?v=6.0021') }}">
    <script src="{{ asset('js/jquery/jquery-2.1.1.min.js') }}"></script>

    <!-- Mailchimp embed CSS -->


    <style>
        /* Mailchimp container unutar modala */
        #mc_embed_signup{
            background:#fff;

            width:100%;
        }

        /* Floating Newsletter button */
        .newsletter-fab {
            position: fixed;
            right: 1.5rem;
            bottom: 20px; /* da ne sjedi preko "Scroll to top" gumba */
            z-index: 1050;
            border-radius: 999px;
            padding: 0.75rem 1.4rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
        }

        #newsletterModal .modal-content{
            border-radius: 1rem;
        }

        #newsletterModal #mc_embed_signup input[type=email]{
            width: 100%;
            border-radius: .375rem;
            border: 1px solid #ced4da;
            padding: .5rem .75rem;
        }

        #newsletterModal #mc_embed_signup input[type=email]:focus{
            border-color:#e50077;
            box-shadow:0 0 0 .15rem rgba(229,0,119,.15);
        }

        #mc_embed_signup .button{
            background:#e50077;
            border:0;
            border-radius:999px;
            padding:.6rem 2rem;
            font-weight:600;
            color:#fff;
        }
        #mc_embed_signup .button:hover{
            background:#c70064;
        }

    </style>

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



<!-- Newsletter Modal -->
<div class="modal fade" id="newsletterModal" tabindex="-1" aria-labelledby="newsletterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="newsletterModalLabel">Newsletter prijava</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zatvori"></button>
            </div>

            <div class="modal-body">

                <div id="mc_embed_signup">
                    <form
                        action="https://zuzi.us19.list-manage.com/subscribe/post?u=7fab5e15d1806ca44435921d5&amp;id=f17217dc77&amp;v_id=5011&amp;f_id=00595be7f0"
                        method="post"
                        id="mc-embedded-subscribe-form"
                        name="mc-embedded-subscribe-form"
                        class="validate"
                        target="_blank">

                        <div id="mc_embed_signup_scroll">



                            <div class="indicates-required mb-3">
                                <span class="asterisk">*</span> označava obavezno polje
                            </div>

                            <!-- EMAIL -->
                            <div class="mc-field-group mb-3">
                                <label for="mce-EMAIL">Email adresa <span class="asterisk">*</span></label>
                                <input type="email" name="EMAIL" class="required email" id="mce-EMAIL" required>
                            </div>

                            <!-- GDPR BLOK -->
                            <div id="mergeRow-gdpr" class="mergeRow gdpr-mergeRow content__gdprBlock mc-field-group mb-3">

                                <div class="content__gdpr mb-3">
                                    <label class="fw-bold">Dozvola za marketing</label>
                                    <p class="small">
                                        Molimo odaberite sve načine na koje biste željeli primati obavijesti
                                        od ZUZI, obrt za uslužne djelatnosti:
                                    </p>

                                    <fieldset class="mc_fieldset gdprRequired mc-field-group" name="interestgroup_field">
                                        <!-- EMAIL – defaultno označen -->
                                        <label class="checkbox subfield d-flex align-items-center gap-2 mb-2" for="gdpr_58118">
                                            <input type="checkbox"
                                                   id="gdpr_58118"
                                                   name="gdpr[58118]"
                                                   class="gdpr"
                                                   value="Y"
                                                   checked>
                                            <span>Email</span>
                                        </label>

                                        <!-- SMS – sakriven -->
                                        <label class="checkbox subfield d-none" for="gdpr_58122">
                                            <input type="checkbox"
                                                   id="gdpr_58122"
                                                   name="gdpr[58122]"
                                                   class="gdpr"
                                                   value="Y">
                                            <span>SMS</span>
                                        </label>
                                    </fieldset>

                                    <p class="small mt-2">
                                        Možete se odjaviti u bilo kojem trenutku klikom na poveznicu u podnožju naših e-poruka.
                                        Za informacije o našim praksama zaštite privatnosti, molimo posjetite našu web stranicu.
                                    </p>
                                </div>

                                <div class="content__gdprLegal small text-muted">
                                    <p>
                                        Mailchimp koristimo kao našu marketinšku platformu. Klikom na gumb u nastavku za prijavu
                                        potvrđujete da će vaši podaci biti proslijeđeni Mailchimpu na obradu.
                                        <a href="https://mailchimp.com/legal/terms" target="_blank" rel="noopener">Saznajte više</a>.
                                    </p>
                                </div>

                            </div>


                            <!-- RESPONSE MESSAGES -->
                            <div id="mce-responses" class="clear mb-3">
                                <div class="response" id="mce-error-response" style="display:none;"></div>
                                <div class="response" id="mce-success-response" style="display:none;"></div>
                            </div>

                            <!-- HONEYPOT -->
                            <div style="position:absolute; left:-5000px;" aria-hidden="true">
                                <input type="text" name="b_7fab5e15d1806ca44435921d5_f17217dc77" tabindex="-1" value="">
                            </div>

                            <!-- SUBMIT -->
                            <div class="optionalParent">
                                <div class="clear foot">
                                    <input type="submit" name="subscribe" id="mc-embedded-subscribe" class="button" value="Prijava">
                                </div>
                            </div>

                        </div>
                    </form>
                </div>

            </div>

        </div>
    </div>
</div>



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

<script type="text/javascript" src="//s3.amazonaws.com/downloads.mailchimp.com/js/mc-validate.js"></script>
<script type="text/javascript">
    (function($) {
        window.fnames = new Array();
        window.ftypes = new Array();
        fnames[0] = 'EMAIL'; ftypes[0] = 'email';
        fnames[1] = 'FNAME'; ftypes[1] = 'text';
        fnames[2] = 'LNAME'; ftypes[2] = 'text';
        fnames[4] = 'PHONE'; ftypes[4] = 'phone';
        fnames[5] = 'BIRTHDAY'; ftypes[5] = 'birthday';
    }(jQuery));
    var $mcj = jQuery.noConflict(true);
</script>

@stack('js_after')

</body>
</html>
