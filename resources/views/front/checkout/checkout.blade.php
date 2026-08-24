@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand('Naplata'))
@section('description', \App\Models\Seo::description(null, 'Korak naplate i unosa podataka za narudzbu na ' . \App\Models\Seo::brand() . '.'))

@push('css_after')
    @livewireStyles
    <style>
        .checkout-save-toast {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: .55rem;
            max-width: calc(100vw - 2rem);
            padding: .7rem 1rem;
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: .65rem;
            background: #2a6248;
            box-shadow: 0 .45rem 1.35rem rgba(21, 55, 40, .24);
            color: #fff;
            font-size: .9rem;
            font-weight: 600;
            opacity: 0;
            pointer-events: none;
            transform: translate(-50%, .75rem);
            transition: opacity .18s ease, transform .18s ease;
        }

        .checkout-save-toast.is-visible {
            opacity: 1;
            transform: translate(-50%, 0);
        }
    </style>
@endpush

@section('content')




    <!-- Page title + breadcrumb-->
    <nav class="mb-4" id="checkout-page-start" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            <li class="breadcrumb-item text-nowrap active" aria-current="page">Naplata</li>
        </ol>
    </nav>
    <!-- Content-->
    <!-- Sorting-->
    <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">
        <h1 class="h2 mb-3 mb-md-0 me-3">Naplata</h1>

    </section>

    <div class=" pb-5 mb-2 mb-md-4">
        <div class="row">
            <section class="col-lg-8">
                @livewire('front.checkout', ['step' => $step, 'is_free_shipping' => $is_free_shipping])
            </section>
            <!-- Sidebar-->
            <aside class="col-lg-4 pt-4 pt-lg-0 ps-xl-5 d-none d-lg-block">
                <cart-view-aside route="naplata" continueurl="{{ \Illuminate\Support\Facades\URL::previous() }}" checkouturl="{{ route('naplata') }}"></cart-view-aside>
            </aside>
        </div>
    </div>

    <div class="checkout-save-toast" id="checkout-save-toast" role="status" aria-live="polite" aria-atomic="true">
        <i class="ci-check-circle" aria-hidden="true"></i>
        <span data-checkout-toast-message></span>
    </div>

@endsection

@push('js_after')
    @livewireScripts
    <script>
        (function () {
            var hideToastTimer;

            function initCheckoutSaveToast() {
                var toast = document.getElementById('checkout-save-toast');
                var message = toast ? toast.querySelector('[data-checkout-toast-message]') : null;

                if (!toast || !message || toast.dataset.listenerAttached === '1') {
                    return;
                }

                toast.dataset.listenerAttached = '1';
                window.addEventListener('checkout-option-saved', function (event) {
                    message.textContent = event.detail && event.detail.message ? event.detail.message : '';
                    toast.classList.add('is-visible');

                    window.clearTimeout(hideToastTimer);
                    hideToastTimer = window.setTimeout(function () {
                        toast.classList.remove('is-visible');
                    }, event.detail && event.detail.duration ? event.detail.duration : 1800);
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initCheckoutSaveToast, { once: true });
            } else {
                initCheckoutSaveToast();
            }
        }());
    </script>
@endpush
