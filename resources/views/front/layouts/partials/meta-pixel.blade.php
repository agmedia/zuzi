@if (config('app.env') === 'production' && config('services.meta_pixel.id'))
    <script src="{{ asset('js/meta-pixel.js') }}?v={{ filemtime(public_path('js/meta-pixel.js')) }}"></script>
    <script>
        (function () {
            if (!window.ZuziMetaPixel || typeof window.ZuziMetaPixel.createTracker !== 'function') {
                return;
            }

            const tracker = window.ZuziMetaPixel.createTracker(
                window,
                @json((string) config('services.meta_pixel.id'))
            );

            window.updateMetaConsentFromCookie = function (marketingGranted, consentDecided) {
                tracker.setConsent(marketingGranted === true, consentDecided === true);
            };

            if (
                window.CookieConsent
                && typeof window.CookieConsent.validConsent === 'function'
                && window.CookieConsent.validConsent()
            ) {
                window.updateMetaConsentFromCookie(
                    window.CookieConsent.acceptedCategory('marketing'),
                    true
                );
            } else {
                tracker.setConsent(false, false);
            }
        })();
    </script>
@endif
