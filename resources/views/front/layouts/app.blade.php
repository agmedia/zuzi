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

    <!-- Vendor Styles including: Font Icons, Plugins, etc.-->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <!-- Main Theme Styles + Bootstrap-->
    <link rel="stylesheet" media="screen" href="{{ asset(config('settings.images_domain') . 'css/theme.css?v=1.92') }}">

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

    @stack('css_after')

    @if (config('app.env') == 'production')
        <!-- Facebook Pixel Code -->
    <!--    <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                n.callMethod.apply(n,arguments):n.queue.push(arguments)};
                if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
                n.queue=[];t=b.createElement(e);t.async=!0;
                t.src=v;s=b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t,s)}(window, document,'script',
                'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', 'xxxxxxxxxxx');
            fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
                       src="https://www.facebook.com/tr?id=xxxxxx&ev=PageView&noscript=1"
            /></noscript> -->
    @endif

    <style>
        [v-cloak] { display:none !important; }
    </style>

</head>
<!-- Body-->
<body class="bg-secondary">

@if (config('app.env') == 'production')
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TKZGQZV"
                      height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
@endif


<!-- Light topbar -->
<div class="topbar topbar-light  bg-dark">
    <div class="container">

        <div class="topbar-text text-nowrap  d-inline-block">
            <i class="ci-support"></i>
            <span class=" me-1">Podrška</span>
            <a class="topbar-link" href="tel:00385916047126">091 604 7126</a>
        </div>
        <div class="topbar-text  d-none  d-md-inline-block">Besplatna dostava za sve narudžbe iznad 70 €</div>
        <div class="ms-3 text-nowrap ">
            <a class="topbar-link me-2 d-inline-block" href="https://www.facebook.com/zuziobrt/">
                <i class="ci-facebook"></i>
            </a>

            <a class="topbar-link me-2 d-inline-block" href="https://www.instagram.com/zuziobrt/">
                <i class="ci-instagram"></i>
            </a>

            <a class="topbar-link me-0 d-inline-block" href="mailto:info@zuzi.hr">
                <i class="ci-mail"></i>
            </a>

        </div>
    </div>
</div>

<section class="spikes"></section>

<div id="agapp">
    @include('front.layouts.partials.header')

    @yield('content')
    <section class="spikesw"></section>
    @include('front.layouts.partials.footer')

    @include('front.layouts.partials.handheld')
</div>

<!-- Back To Top Button-->
<a class="btn-scroll-top" href="#top" data-scroll><span class="btn-scroll-top-tooltip text-muted fs-sm me-2">Top</span><i class="btn-scroll-top-icon ci-arrow-up"></i></a>
<!-- Vendor Styles including: Font Icons, Plugins, etc.-->
<link rel="stylesheet" media="screen" href="{{ asset(config('settings.images_domain') . 'css/tiny-slider.css?v=1.2') }}"/>
<!-- Vendor scrits: js libraries and plugins-->
<script src="{{ asset('js/jquery/jquery-2.1.1.min.js?v=1.2') }}"></script>
<script src="{{ asset('js/bootstrap.bundle.min.js?v=1.2') }}"></script>
<script src="{{ asset('js/tiny-slider.js?v=1.2') }}"></script>
<script src="{{ asset('js/smooth-scroll.polyfills.min.js?v=1.2') }}"></script>



<script src="{{ asset('js/imagesloaded/imagesloaded.pkgd.min.js') }}"></script>
<script src="{{ asset('js/shufflejs/dist/shuffle.min.js') }}"></script>
<!-- Main theme script-->

<script src="{{ asset('js/cart.js?v=1.2') }}"></script>

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

@if (config('app.env') == 'production')
    <!-- Messenger Chat Plugin Code -->

@endif

@stack('js_after')

</body>
</html>
