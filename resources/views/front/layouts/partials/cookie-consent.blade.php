@php
    $cookieTitle = 'Kolačići za ugodnije listanje';
    $cookieMessage = 'Zuzi koristi kolačiće kako bi stranica radila kako treba, pretraga bila brža, a preporuke korisnije.';
    $cookieAcceptLabel = 'Prihvati sve';
    $cookiePreferencesTitle = 'Odaberi kolačiće';
    $cookiePreferencesAcceptAll = 'Prihvati sve';
    $cookiePreferencesAcceptNecessary = 'Samo nužni';
    $cookiePreferencesSave = 'Spremi odabir';
    $cookieNecessaryTitle = 'Nužni kolačići';
    $cookieNecessaryDescription = 'Bez njih webshop ne može ispravno raditi, zato su uvijek uključeni.';
    $cookieAnalyticsTitle = 'Analitika';
    $cookieAnalyticsDescription = 'Pomaže nam razumjeti kako koristiš stranicu kako bismo je mogli učiniti još boljom.';
    $cookieMarketingTitle = 'Marketing';
    $cookieMarketingDescription = 'Pomaže nam mjeriti uspješnost Google, Meta i Mailchimp kampanja te prikazati relevantnije preporuke i oglase.';
    $cookieLocale = app()->getLocale();
    $cookieDescription = $cookieMessage;
@endphp

<script>
    window.cookieAnalyticsAllowed = window.cookieAnalyticsAllowed === true;
    window.cookieMarketingAllowed = window.cookieMarketingAllowed === true;
    window.canTrackAnalytics = () => window.cookieAnalyticsAllowed === true;

    const mailchimpAttributionCookies = {
        zuzi_mc_cid: 'mc_cid'
    };
    const validMailchimpIdentifier = (value) => /^[a-z0-9_-]{1,100}$/i.test(value || '');
    const pendingMailchimpCampaignId = new URL(window.location.href).searchParams.get('mc_cid');
    const mailchimpConsentCookie = 'zuzi_marketing_consent';
    const mailchimpCookieMaxAge = 60 * 60 * 24 * 30;

    const setMailchimpConsentState = (state) => {
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${mailchimpConsentCookie}=${state}; Max-Age=${mailchimpCookieMaxAge}; Path=/; SameSite=Lax${secure}`;
    };

    const clearMailchimpAttribution = () => {
        Object.keys(mailchimpAttributionCookies).forEach((cookieName) => {
            document.cookie = `${cookieName}=; Max-Age=0; Path=/; SameSite=Lax`;
        });

        setMailchimpConsentState('denied');
    };

    const syncMailchimpAttribution = (marketingGranted) => {
        if (!marketingGranted) {
            clearMailchimpAttribution();
            return;
        }

        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        setMailchimpConsentState('granted');

        Object.entries(mailchimpAttributionCookies).forEach(([cookieName, parameterName]) => {
            const value = parameterName === 'mc_cid'
                ? pendingMailchimpCampaignId
                : new URL(window.location.href).searchParams.get(parameterName);

            if (validMailchimpIdentifier(value)) {
                document.cookie = `${cookieName}=${encodeURIComponent(value)}; Max-Age=${mailchimpCookieMaxAge}; Path=/; SameSite=Lax${secure}`;
            }
        });
    };

    const syncGoogleConsent = () => {
        if (!window.CookieConsent) {
            return;
        }

        const analyticsGranted = window.CookieConsent.acceptedCategory('analytics');
        const marketingGranted = window.CookieConsent.acceptedCategory('marketing');

        window.cookieAnalyticsAllowed = analyticsGranted;
        window.cookieMarketingAllowed = marketingGranted;
        window.canTrackAnalytics = () => window.cookieAnalyticsAllowed === true;
        syncMailchimpAttribution(marketingGranted);

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

    const runCookieConsent = ({ showConsentIfNeeded = true } = {}) => {
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

        if (showConsentIfNeeded && !window.CookieConsent.validConsent()) {
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

    const openCookiePreferences = () => {
        ensureCookieConsentAssets().then(() => {
            runCookieConsent({ showConsentIfNeeded: false });

            window.setTimeout(() => {
                if (window.CookieConsent && typeof window.CookieConsent.showPreferences === 'function') {
                    window.CookieConsent.showPreferences();
                    return;
                }

                if (window.CookieConsent && typeof window.CookieConsent.show === 'function') {
                    window.CookieConsent.show();
                }
            }, 60);
        });
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-cookie-consent-trigger]');

        if (!trigger) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        openCookiePreferences();
    });

    const hasStoredCookieConsent = () => document.cookie.split(';').some((entry) => entry.trim().startsWith('cc_cookie='));

    const scheduleCookieConsentBoot = () => {
        if (hasStoredCookieConsent() || validMailchimpIdentifier(pendingMailchimpCampaignId)) {
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
