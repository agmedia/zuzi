@php
    $mode = $mode ?? 'link';
    $wrapperClass = $wrapperClass ?? 'mt-3';
    $buttonClass = $buttonClass ?? 'w-100';
@endphp

@once
    @push('css_after')
        <style>
            .corvus-wallet-shortcuts {
                display: none;
            }

            .corvus-wallet-shortcuts__grid {
                display: grid;
                gap: .75rem;
            }

            .corvus-wallet-form {
                width: 100%;
            }

            .corvus-wallet-form--apple {
                display: none;
                align-items: center;
                justify-content: center;
                height: 3.5rem;
                border-radius: var(--cz-btn-border-radius, .3125rem);
                background: #000;
                overflow: hidden;
            }

            .corvus-wallet-button {
                display: none !important;
                align-items: center;
                justify-content: center;
                min-height: 3.5rem;
                height: 3.5rem;
                border-radius: .5rem;
                font-weight: 700;
                letter-spacing: 0;
                width: 100%;
            }

            .corvus-wallet-button--apple-native,
            .corvus-wallet-button--apple-fallback {
                min-width: 140px;
                padding: 0;
                border: 0;
                background: #000;
                color: #fff;
                cursor: pointer;
            }

            .corvus-wallet-button--apple-native {
                min-height: 44px;
                height: 44px !important;
            }

            .corvus-wallet-button--apple-fallback {
                min-height: 3.5rem;
                height: 3.5rem;
            }

            .corvus-wallet-button--apple-native:hover,
            .corvus-wallet-button--apple-native:focus,
            .corvus-wallet-button--apple-fallback:hover,
            .corvus-wallet-button--apple-fallback:focus {
                background: #000;
                color: #fff;
            }

            .corvus-wallet-button--apple-native {
                -webkit-appearance: -apple-pay-button;
                -apple-pay-button-type: buy;
                -apple-pay-button-style: black;
            }

            .corvus-wallet-button__apple-fallback {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: .35rem;
                font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
                font-size: .96rem;
                font-weight: 700;
                line-height: 1;
            }

            .corvus-wallet-button__apple-logo {
                font-size: 1.18rem;
                line-height: .8;
            }

            .corvus-wallet-button--google {
                min-height: 3.5rem;
                height: 3.5rem;
                border: 0;
                background: #000;
                color: #fff;
                box-shadow: none;
                font-family: Arial, Helvetica, sans-serif;
            }

            .corvus-wallet-button--google:hover,
            .corvus-wallet-button--google:focus {
                background: #3c4043;
                color: #fff;
            }

            .corvus-wallet-button__google-mark {
                display: inline-flex;
                align-items: center;
                margin-right: .4rem;
            }

            .corvus-wallet-button__google-mark svg {
                display: block;
                width: 1.15rem;
                height: 1.15rem;
            }

            html.supports-corvus-applepay .corvus-wallet-shortcuts,
            html.supports-corvus-googlepay .corvus-wallet-shortcuts {
                display: block;
            }

            html.supports-corvus-applepay.supports-corvus-native-applepay .corvus-wallet-button--apple-native,
            html.supports-corvus-applepay:not(.supports-corvus-native-applepay) .corvus-wallet-button--apple-fallback,
            html.supports-corvus-googlepay .corvus-wallet-button--google {
                display: flex !important;
            }

            html.supports-corvus-applepay.supports-corvus-native-applepay .corvus-wallet-form--apple,
            html.supports-corvus-applepay:not(.supports-corvus-native-applepay) .corvus-wallet-form--apple {
                display: flex;
            }

            .corvus-wallet-shortcuts .corvus-wallet-button {
                border-radius: var(--cz-btn-border-radius, .3125rem);
            }
        </style>
    @endpush

    @push('js_after')
        <script>
            (function () {
                if (window.corvusWalletDetectionReady) {
                    return;
                }

                window.corvusWalletDetectionReady = true;

                function detectCorvusWallet() {
                    var nav = window.navigator || {};
                    var userAgent = nav.userAgent || '';
                    var platform = nav.platform || '';
                    var maxTouchPoints = nav.maxTouchPoints || 0;
                    var isIpadOS = platform === 'MacIntel' && maxTouchPoints > 1;
                    var isAppleDevice = /iPhone|iPad|iPod|Macintosh|Mac OS X/i.test(userAgent) || /^Mac/i.test(platform) || isIpadOS;
                    var isApplePayPreferred = isAppleDevice;
                    var isGooglePayPreferred = !isApplePayPreferred && (/Android/i.test(userAgent) || /Win/i.test(platform) || /Windows/i.test(userAgent));
                    var supportsNativeApplePayButton = isAppleDevice && window.ApplePaySession && window.CSS && CSS.supports && CSS.supports('-webkit-appearance', '-apple-pay-button');
                    var root = document.documentElement;

                    root.classList.toggle('supports-corvus-applepay', isApplePayPreferred);
                    root.classList.toggle('supports-corvus-googlepay', isGooglePayPreferred);
                    root.classList.toggle('supports-corvus-native-applepay', !!supportsNativeApplePayButton);
                }

                detectCorvusWallet();
                document.addEventListener('DOMContentLoaded', detectCorvusWallet);
            })();
        </script>
    @endpush
@endonce

<div class="corvus-wallet-shortcuts {{ $wrapperClass }}">
    <div class="corvus-wallet-shortcuts__grid">
        @if($mode === 'livewire')
            <div class="corvus-wallet-form corvus-wallet-form--apple">
                <button type="button" class="corvus-wallet-button corvus-wallet-button--apple-native {{ $buttonClass }}" wire:click="selectWalletPayment('applepay')" aria-label="Apple Pay" lang="hr">
                </button>
                <button type="button" class="corvus-wallet-button corvus-wallet-button--apple-fallback {{ $buttonClass }}" wire:click="selectWalletPayment('applepay')" aria-label="Platite uz Apple Pay" lang="hr">
                    <span class="corvus-wallet-button__apple-fallback" aria-hidden="true">
                        Platite uz <span class="corvus-wallet-button__apple-logo">&#63743;</span>Pay
                    </span>
                </button>
            </div>
            <button type="button" class="btn corvus-wallet-button corvus-wallet-button--google {{ $buttonClass }}" wire:click="selectWalletPayment('googlepay')" aria-label="Google Pay">
                <span class="corvus-wallet-button__google-mark" aria-hidden="true">
                    <svg viewBox="0 0 18 18" focusable="false" aria-hidden="true">
                        <path fill="#4285f4" d="M17.64 9.2c0-.63-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/>
                        <path fill="#34a853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.33-1.58-5.04-3.7H.94v2.33A9 9 0 0 0 9 18z"/>
                        <path fill="#fbbc05" d="M3.96 10.72A5.41 5.41 0 0 1 3.68 9c0-.6.1-1.18.28-1.72V4.95H.94A9 9 0 0 0 0 9c0 1.45.34 2.82.94 4.05l3.02-2.33z"/>
                        <path fill="#ea4335" d="M9 3.58c1.32 0 2.5.45 3.43 1.35l2.59-2.59C13.46.89 11.43 0 9 0A9 9 0 0 0 .94 4.95l3.02 2.33C4.67 5.16 6.66 3.58 9 3.58z"/>
                    </svg>
                </span>
                Google Pay
            </button>
        @else
            <form class="corvus-wallet-form corvus-wallet-form--apple" action="{{ route('checkout.wallet', ['wallet' => 'applepay']) }}" method="GET">
                <button type="submit" class="corvus-wallet-button corvus-wallet-button--apple-native {{ $buttonClass }}" aria-label="Apple Pay" lang="hr">
                </button>
                <button type="submit" class="corvus-wallet-button corvus-wallet-button--apple-fallback {{ $buttonClass }}" aria-label="Platite uz Apple Pay" lang="hr">
                    <span class="corvus-wallet-button__apple-fallback" aria-hidden="true">
                        Platite uz <span class="corvus-wallet-button__apple-logo">&#63743;</span>Pay
                    </span>
                </button>
            </form>
            <form class="corvus-wallet-form" action="{{ route('checkout.wallet', ['wallet' => 'googlepay']) }}" method="GET">
                <button type="submit" class="btn corvus-wallet-button corvus-wallet-button--google {{ $buttonClass }}" aria-label="Google Pay">
                    <span class="corvus-wallet-button__google-mark" aria-hidden="true">
                        <svg viewBox="0 0 18 18" focusable="false" aria-hidden="true">
                            <path fill="#4285f4" d="M17.64 9.2c0-.63-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/>
                            <path fill="#34a853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.33-1.58-5.04-3.7H.94v2.33A9 9 0 0 0 9 18z"/>
                            <path fill="#fbbc05" d="M3.96 10.72A5.41 5.41 0 0 1 3.68 9c0-.6.1-1.18.28-1.72V4.95H.94A9 9 0 0 0 0 9c0 1.45.34 2.82.94 4.05l3.02-2.33z"/>
                            <path fill="#ea4335" d="M9 3.58c1.32 0 2.5.45 3.43 1.35l2.59-2.59C13.46.89 11.43 0 9 0A9 9 0 0 0 .94 4.95l3.02 2.33C4.67 5.16 6.66 3.58 9 3.58z"/>
                        </svg>
                    </span>
                    Google Pay
                </button>
            </form>
        @endif
    </div>
</div>
