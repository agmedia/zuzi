<!doctype html>
<html lang="{{ config('app.locale') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

    <title>Antikvarijat Biblos</title>

    <meta name="description" content="Dobrodošli na stranice Antikvarijata Biblos, Palmotićeva 28, Zagreb. Radno vrijeme pon-pet 09-20h, sub 09-14h">
    <meta name="author" content="pixelcave">
    <meta name="robots" content="noindex, nofollow">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Icons -->
    <link rel="shortcut icon" href="{{ asset('media/img/faviconbiblos.png') }}">
    <link rel="icon" sizes="192x192" type="image/png" href="{{ asset('media/img/faviconbiblos.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('media/img/faviconbiblos.png') }}">

    <!-- Fonts and Styles -->
    @stack('css_before')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" id="css-main" href="{{ asset('css/dashmix.css') }}">

    <!-- You can include a specific file from public/css/themes/ folder to alter the default color theme of the template. eg: -->
<!-- <link rel="stylesheet" id="css-theme" href="{{ asset('css/themes/xwork.css') }}"> -->
@stack('css_after')

<!-- Scripts -->
    <script>window.Laravel = {!! json_encode(['csrfToken' => csrf_token(),]) !!};</script>
</head>
<body>

<div id="page-container">
    <main id="main-container">
        @yield('content')
    </main>
</div>

<!-- Dashmix Core JS -->
<script src="{{ asset('/js/dashmix.app.js') }}"></script>

<!-- Laravel Original JS -->
<script src="{{ asset('/js/laravel.app.js') }}"></script>

@stack('js_after')
</body>
</html>
