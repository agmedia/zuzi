<!DOCTYPE html>
<html lang="hr">
<head>
    <!-- Start cookieyes banner -->
    <script id="cookieyes" type="text/javascript" src="https://cdn-cookieyes.com/client_data/f36cda12a26aeec8707a076b/script.js"></script>
    <!-- End cookieyes banner -->
    <meta charset="utf-8">
    <title> @yield('title') </title>
    <!-- SEO Meta Tags-->
    <meta name="description" content="@yield('description')">
    <meta name="author" content="Zuzi Shop">
    @stack('meta_tags')
    <!-- Viewport-->
    <meta name="viewport" content="width=device-width, user-scalable=no" />
    <meta name="google-site-verification" content="Iq6KaWRTW8e-u9BG8MKUjITt4_fVi92rZl8E5Dyrx-0" />

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="stylesheet" media="screen" href="{{ asset('vendor/simplebar/dist/simplebar.min.css') }}"/>
    <link rel="stylesheet" media="screen" href="{{ asset('css/theme.css?v=6.001') }}">
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
        <!-- <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}

            gtag('consent', 'default', {
                'ad_storage': 'denied',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied',
                'analytics_storage': 'denied',
                'functionality_storage': 'denied',
                'personalization_storage': 'denied',
                'security_storage': 'denied'
            });
        </script>

        <script>
            function compgafad_consentGrantedAdStorage() {
                gtag('consent', 'update', {
                    'ad_storage': 'granted',
                    'ad_user_data': 'granted',
                    'ad_personalization': 'granted',
                    'analytics_storage': 'granted',
                    'functionality_storage': 'granted',
                    'personalization_storage': 'granted',
                    'security_storage': 'granted'
                });

                var ckdate = new Date();
                ckdate.setTime(ckdate.getTime() + (30*24*60*60*1000));
                document.cookie = 'cookie_consent_user_accepted=1;expires='+ckdate.toUTCString()+'; path=/';
            }

            $(document).delegate('#gdpr-cookie-accept', 'click', function() {
                compgafad_consentGrantedAdStorage();
                console.log('trigger - compgafad_consentGrantedAdStorage');
            });

        </script>-->

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
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-WWPNJL6JD5"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'G-WWPNJL6JD5');
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
<link rel="stylesheet" media="screen" href="{{ asset(config('settings.images_domain') . 'css/tiny-slider.css?v=1.2') }}"/>
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


<!-- Font icons -->
<link rel="preload" href="{{ asset('icons/cartzilla-icons.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('icons/cartzilla-icons.min.css') }}">


<!-- Main theme script-->
<script src="{{ asset('js/cart.js?v=2.9.5') }}"></script>

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
