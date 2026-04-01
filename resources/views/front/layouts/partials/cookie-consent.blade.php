@php
    $cookieTitle = 'Koristimo kolačiće';
    $cookieMessage = 'Koristimo kolačiće za ispravan rad stranice i bolje korisničko iskustvo.';
    $cookieAcceptLabel = 'U redu';
    $cookiePolicyLabel = 'Više informacija';
    $cookiePolicyUrl = route('catalog.route.page', ['page' => 'opci-uvjeti-kupnje']);
    $cookiePreferencesTitle = 'Postavke kolačića';
    $cookiePreferencesAcceptAll = 'Prihvati sve';
    $cookiePreferencesAcceptNecessary = 'Samo nužni';
    $cookiePreferencesSave = 'Spremi odabir';
    $cookieNecessaryTitle = 'Nužni kolačići';
    $cookieNecessaryDescription = 'Neki kolačići na ovoj internetskoj stranici neophodni su za pravilno funkcioniranje stranice stoga ih nije moguće onemogućiti.';
    $cookieAnalyticsTitle = 'Analitički kolačići';
    $cookieAnalyticsDescription = 'Analitički kolačići nam pomažu kako bismo poboljšali našu internetsku stranicu sakupljajući i analizirajući podatke o njenoj posjećenosti.';
    $cookieMarketingTitle = 'Marketinški kolačići';
    $cookieMarketingDescription = 'Marketinški kolačići služe za praćenje posjetitelja u korištenju internet stranice u svrhu omogućavanja prikazivanja relevantnih oglasa oglašivača trećih strana.';
    $cookieLocale = app()->getLocale();
    $cookieDescription = $cookieMessage;

    if ($cookiePolicyUrl !== '') {
        $cookieDescription .= ' <a href="'.e($cookiePolicyUrl).'">'.e($cookiePolicyLabel).'</a>';
    }
@endphp

<button
    type="button"
    id="cookie-consent-floating-button"
    aria-label="Cookie postavke"
>
    <img src="{{ asset('media/img/cookie-svg.svg') }}" alt="" width="24" height="24" loading="lazy" />
</button>

<script>
    window.cookieAnalyticsAllowed = window.cookieAnalyticsAllowed === true;
    window.cookieMarketingAllowed = window.cookieMarketingAllowed === true;
    window.canTrackAnalytics = () => window.cookieAnalyticsAllowed === true;

    const syncGoogleConsent = () => {
        if (!window.CookieConsent) {
            return;
        }

        const analyticsGranted = window.CookieConsent.acceptedCategory('analytics');
        const marketingGranted = window.CookieConsent.acceptedCategory('marketing');

        window.cookieAnalyticsAllowed = analyticsGranted;
        window.cookieMarketingAllowed = marketingGranted;
        window.canTrackAnalytics = () => window.cookieAnalyticsAllowed === true;

        if (typeof window.updateGoogleConsentFromCookie === 'function') {
            window.updateGoogleConsentFromCookie(analyticsGranted, marketingGranted);
        }
    };

    const cookieConsentConfig = {
        disablePageInteraction: true,
        guiOptions: {
            consentModal: {
                layout: 'box',
                position: 'middle center',
                equalWeightButtons: true,
                flipButtons: false
            },
            preferencesModal: {
                layout: 'box',
                position: 'middle center'
            }
        },
        categories: {
            necessary: {
                enabled: true,
                readOnly: true
            },
            analytics: {
                enabled: false,
                readOnly: false
            },
            marketing: {
                enabled: false,
                readOnly: false
            }
        },
        onFirstConsent: () => syncGoogleConsent(),
        onConsent: () => syncGoogleConsent(),
        onChange: () => syncGoogleConsent(),
        language: {
            default: @json($cookieLocale),
            translations: {
                @json($cookieLocale): {
                    consentModal: {
                        title: @json($cookieTitle),
                        description: @json($cookieDescription),
                        acceptAllBtn: @json($cookieAcceptLabel),
                        acceptNecessaryBtn: @json($cookiePreferencesAcceptNecessary),
                        showPreferencesBtn: 'Postavke'
                    },
                    preferencesModal: {
                        title: @json($cookiePreferencesTitle),
                        acceptAllBtn: @json($cookiePreferencesAcceptAll),
                        acceptNecessaryBtn: @json($cookiePreferencesAcceptNecessary),
                        savePreferencesBtn: @json($cookiePreferencesSave),
                        sections: [
                            {
                                title: @json($cookieNecessaryTitle),
                                description: @json($cookieNecessaryDescription),
                                linkedCategory: 'necessary'
                            },
                            {
                                title: @json($cookieAnalyticsTitle),
                                description: @json($cookieAnalyticsDescription),
                                linkedCategory: 'analytics'
                            },
                            {
                                title: @json($cookieMarketingTitle),
                                description: @json($cookieMarketingDescription),
                                linkedCategory: 'marketing'
                            }
                        ]
                    }
                }
            }
        }
    };

    const ensureCookieConsentAssets = (() => {
        let loadingPromise = null;

        return () => {
            if (loadingPromise) {
                return loadingPromise;
            }

            loadingPromise = new Promise((resolve, reject) => {
                const localCssHref = @json(asset('vendor/cookieconsent/cookieconsent.css').'?v='.filemtime(public_path('vendor/cookieconsent/cookieconsent.css')));
                const cdnCssHref = 'https://cdn.jsdelivr.net/npm/vanilla-cookieconsent@3/dist/cookieconsent.css';
                const localScriptSrc = @json(asset('vendor/cookieconsent/cookieconsent.umd.js').'?v='.filemtime(public_path('vendor/cookieconsent/cookieconsent.umd.js')));
                const cdnScriptSrc = 'https://cdn.jsdelivr.net/npm/vanilla-cookieconsent@3/dist/cookieconsent.umd.js';

                if (!document.querySelector('link[data-cookie-consent-css="1"]')) {
                    const css = document.createElement('link');
                    css.rel = 'stylesheet';
                    css.href = localCssHref;
                    css.setAttribute('data-cookie-consent-css', '1');
                    css.onerror = () => {
                        css.onerror = null;
                        css.href = cdnCssHref;
                    };
                    document.head.appendChild(css);
                }

                if (window.CookieConsent && typeof window.CookieConsent.run === 'function') {
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.src = localScriptSrc;
                script.async = true;
                script.onload = () => resolve();
                script.onerror = () => {
                    script.onerror = () => reject(new Error('Failed to load cookie consent script.'));
                    script.src = cdnScriptSrc;
                };
                document.head.appendChild(script);
            });

            return loadingPromise;
        };
    })();

    const runCookieConsent = () => {
        if (!window.CookieConsent || typeof window.CookieConsent.run !== 'function') {
            return;
        }

        if (window.__cookieConsentInitialized === true) {
            syncGoogleConsent();
            return;
        }

        window.__cookieConsentInitialized = true;
        window.CookieConsent.run(cookieConsentConfig);
        syncGoogleConsent();

        if (!window.CookieConsent.validConsent()) {
            window.CookieConsent.show();
        }
    };

    const bootCookieConsent = () => {
        ensureCookieConsentAssets()
            .then(runCookieConsent)
            .catch(() => {
                window.__cookieConsentInitialized = false;
            });
    };

    const cookieFloatingButton = document.getElementById('cookie-consent-floating-button');
    if (cookieFloatingButton) {
        cookieFloatingButton.addEventListener('click', () => {
            ensureCookieConsentAssets().then(() => {
                runCookieConsent();
                if (window.CookieConsent && typeof window.CookieConsent.showPreferences === 'function') {
                    window.CookieConsent.showPreferences();
                }
            });
        });
    }

    const hasStoredCookieConsent = () => document.cookie.split(';').some((entry) => entry.trim().startsWith('cc_cookie='));

    const scheduleCookieConsentBoot = () => {
        if (hasStoredCookieConsent()) {
            bootCookieConsent();
            return;
        }

        let booted = false;
        const runBootOnce = () => {
            if (booted) {
                return;
            }

            booted = true;
            bootCookieConsent();
        };

        const interactionEvents = ['pointerdown', 'keydown', 'touchstart', 'scroll'];
        interactionEvents.forEach((eventName) => {
            window.addEventListener(eventName, runBootOnce, { once: true, passive: true });
        });

        window.setTimeout(runBootOnce, 6000);
    };

    if (document.readyState === 'complete') {
        scheduleCookieConsentBoot();
    } else {
        window.addEventListener('load', scheduleCookieConsentBoot, { once: true });
    }
</script>
