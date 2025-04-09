<!DOCTYPE html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="utf-8">
    <title> @yield('title') </title>
    <!-- SEO Meta Tags-->
    <meta name="description" content="@yield('description')">
    <meta name="author" content="Zuzi Shop">
    @stack('meta_tags')
    <!-- Viewport-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="stylesheet" media="screen" href="{{ asset('vendor/simplebar/dist/simplebar.min.css') }}"/>
    <link rel="stylesheet" media="screen" href="{{ asset('css/theme.css?v=2.7') }}">

    <!-- Favicon and Touch Icons-->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ config('settings.images_domain') . 'media/img/favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ config('settings.images_domain') . 'media/img/favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ config('settings.images_domain') . 'media/img/favicon-16x16.png' }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ config('settings.images_domain') . 'media/img/apple-touch-icon.png' }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ config('settings.images_domain') . 'media/img/favicon-32x32.png' }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ config('settings.images_domain') . 'media/img/favicon-16x16.png' }}">
    <link rel="mask-icon" href="{{ config('settings.images_domain') . 'safari-pinned-tab.svg' }}" color="#e50077">
    <meta name="msapplication-TileColor" content="#e50077">
    <meta name="theme-color" content="#ffffff">

    @stack('css_after')

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
        @yield('google_data_layer')
        <!-- Google Tag Manager -->
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','GTM-TKZGQZV');</script>
        <!-- End Google Tag Manager -->

        <!-- Global site tag (gtag.js) - Google Analytics -->
        <!--   <script async src="https://www.googletagmanager.com/gtag/js?id=xxxxxxx"></script>-->
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', ' G-Q2GNBMK18T');
        </script>


    @endif


</head>

<!-- Body-->
<body class="bg-bck">
@if (config('app.env') == 'production')
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TKZGQZV"
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
<a class="btn-scroll-top" aria-label="Scroll To Top" href="#top" data-scroll data-fixed-element><span class="btn-scroll-top-tooltip text-muted fs-sm me-2">Top</span><i class="btn-scroll-top-icon ci-arrow-up">   </i></a>

<!-- Sign in / sign up modal-->
@include('front.layouts.modals.login')

<!-- Vendor Styles including: Font Icons, Plugins, etc.-->
<link rel="stylesheet" media="screen" href="{{ asset(config('settings.images_domain') . 'css/tiny-slider.css?v=1.2') }}"/>
<!-- Vendor scrits: js libraries and plugins-->
<script src="{{ asset('js/jquery/jquery-2.1.1.min.js') }}"></script>
<script src="{{ asset('js/jquery.ihavecookies.js?v=2.0.320') }}"></script>
<script src="{{ asset('vendor/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/simplebar/dist/simplebar.min.js') }}"></script>
<script src="{{ asset('vendor/tiny-slider/dist/min/tiny-slider.js?v=2.0') }}"></script>
<script src="{{ asset('vendor/smooth-scroll/dist/smooth-scroll.polyfills.min.js') }}"></script>
<script src="{{ asset('js/imagesloaded/imagesloaded.pkgd.min.js') }}"></script>
<script src="{{ asset('js/shufflejs/dist/shuffle.min.js') }}"></script>
<!-- Main theme script-->
<script src="{{ asset('js/cart.js?v=2.4') }}"></script>
<script src="{{ asset('vendor/lightgallery/lightgallery.min.js') }}"></script>
<script src="{{ asset('vendor/lightgallery/plugins/fullscreen/lg-fullscreen.min.js') }}"></script>
<script src="{{ asset('vendor/lightgallery/plugins/zoom/lg-zoom.min.js') }}"></script>
<script src="{{ asset('js/theme.min.js') }}"></script>





<script type="text/javascript">
    $(document).ready(function() {
        $('body').ihavecookies({

            delay: 600,
            expires: 90,

            onAccept: function(){
                var myPreferences = $.fn.ihavecookies.cookie();

            },
            uncheckBoxes: false
        });

    });
</script>

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
    const myModal = document.getElementById('signin-modal')

    myModal.addEventListener('show.bs.modal', (ev) => {
        let invoker = ev.relatedTarget
        let selected_tab = invoker.getAttribute("data-tab-id")
        const tab_btn = document.querySelector('#' + selected_tab)
        const tab = new bootstrap.Tab(tab_btn)
        tab.show()

        let head = document.getElementsByTagName('head')[0];
        let script = document.createElement('script');
        script.src = 'https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.sitekey') }}';
        head.appendChild(script);

        setInterval(() => {
            grecaptcha.ready(function() {
                grecaptcha.execute('{{ config('services.recaptcha.sitekey') }}', {action: 'register'}).then(function(token) {
                    if (token) {
                        document.getElementById('recaptcha').value = token;
                    }
                });
            });
        }, 270);
    })
</script>

@stack('js_after')

</body>
</html>
