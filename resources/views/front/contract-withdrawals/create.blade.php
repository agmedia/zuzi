@extends('front.layouts.app')

@section('title', \App\Models\Seo::appendBrand('Obrazac za jednostrani raskid ugovora'))
@section('description', 'Elektronički obrazac za jednostrani raskid ugovora sklopljenog na daljinu u Zuzi Shopu.')

@push('css_after')
    @include('front.contract-withdrawals.partials.styles')
@endpush

@section('content')
    @php
        $captchaEnabled = trim((string) config('services.recaptcha.sitekey')) !== ''
            && trim((string) config('services.recaptcha.secret')) !== '';
    @endphp

    <div class="withdrawal-page">
        <nav class="mb-4" aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap">
                <li class="breadcrumb-item">
                    <a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a>
                </li>
                <li class="breadcrumb-item text-nowrap active" aria-current="page">Jednostrani raskid ugovora</li>
            </ol>
        </nav>

        <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">
            <div>
                <h1 class="h2 mb-2">Obrazac za jednostrani raskid ugovora</h1>
                <p class="withdrawal-page__intro">
                    Ovim obrascem možete jednostavno i nedvosmisleno raskinuti ugovor sklopljen na daljinu.
                    Razlog nije potrebno navesti, a potvrdu primitka odmah šaljemo na vaš e-mail.
                </p>
            </div>
        </section>

        @if (session('success'))
            <div class="alert alert-success mb-4" role="status">
                <i class="ci-check-circle me-2"></i>{{ session('success') }}
            </div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning mb-4" role="alert">
                <i class="ci-security-announcement me-2"></i>{{ session('warning') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger mb-4" role="alert">
                <strong>Provjerite unesene podatke:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="withdrawal-grid">
            <div class="withdrawal-card">
                <div class="withdrawal-card__body">
                    <div class="withdrawal-scope-note">
                        Ovaj obrazac služi za jednostrani raskid ugovora. Za reklamaciju neispravnog ili
                        neusklađenog proizvoda javite se na
                        <a href="mailto:info@zuzi.hr">info@zuzi.hr</a>.
                    </div>

                    <form
                        method="POST"
                        action="{{ route('contract-withdrawal.review') }}"
                        novalidate
                        data-withdrawal-form
                        data-recaptcha-enabled="{{ $captchaEnabled ? '1' : '0' }}"
                    >
                        @csrf
                        <input type="hidden" name="recaptcha" value="" data-withdrawal-recaptcha>

                        <section class="withdrawal-section" aria-labelledby="withdrawal-consumer-title">
                            <h2 class="withdrawal-section__title" id="withdrawal-consumer-title">
                                <span class="withdrawal-section__number">1</span>
                                Podaci potrošača
                            </h2>

                            <div class="withdrawal-form-grid">
                                <div class="withdrawal-form-grid__full">
                                    <label class="form-label" for="withdrawal-full-name">Ime i prezime *</label>
                                    <input
                                        class="form-control @error('full_name') is-invalid @enderror"
                                        id="withdrawal-full-name"
                                        type="text"
                                        name="full_name"
                                        value="{{ old('full_name', $prefill['full_name'] ?? '') }}"
                                        autocomplete="name"
                                        required
                                        maxlength="191"
                                    >
                                    @error('full_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <label class="form-label" for="withdrawal-email">E-mail za potvrdu *</label>
                                    <input
                                        class="form-control @error('email') is-invalid @enderror"
                                        id="withdrawal-email"
                                        type="email"
                                        name="email"
                                        value="{{ old('email', $prefill['email'] ?? '') }}"
                                        autocomplete="email"
                                        required
                                        maxlength="191"
                                    >
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="withdrawal-field-help">Na ovu adresu šaljemo dokazivu potvrdu primitka.</div>
                                </div>

                                <div>
                                    <label class="form-label" for="withdrawal-phone">Telefon <span class="text-muted">(neobavezno)</span></label>
                                    <input
                                        class="form-control @error('phone') is-invalid @enderror"
                                        id="withdrawal-phone"
                                        type="text"
                                        name="phone"
                                        value="{{ old('phone', $prefill['phone'] ?? '') }}"
                                        autocomplete="tel"
                                        maxlength="80"
                                    >
                                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="withdrawal-form-grid__full">
                                    <label class="form-label" for="withdrawal-address">Ulica i kućni broj *</label>
                                    <input
                                        class="form-control @error('address_line') is-invalid @enderror"
                                        id="withdrawal-address"
                                        type="text"
                                        name="address_line"
                                        value="{{ old('address_line', $prefill['address_line'] ?? '') }}"
                                        autocomplete="street-address"
                                        required
                                        maxlength="255"
                                    >
                                    @error('address_line') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <label class="form-label" for="withdrawal-postal-code">Poštanski broj *</label>
                                    <input
                                        class="form-control @error('postal_code') is-invalid @enderror"
                                        id="withdrawal-postal-code"
                                        type="text"
                                        name="postal_code"
                                        value="{{ old('postal_code', $prefill['postal_code'] ?? '') }}"
                                        autocomplete="postal-code"
                                        required
                                        maxlength="32"
                                    >
                                    @error('postal_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <label class="form-label" for="withdrawal-city">Mjesto *</label>
                                    <input
                                        class="form-control @error('city') is-invalid @enderror"
                                        id="withdrawal-city"
                                        type="text"
                                        name="city"
                                        value="{{ old('city', $prefill['city'] ?? '') }}"
                                        autocomplete="address-level2"
                                        required
                                        maxlength="120"
                                    >
                                    @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <label class="form-label" for="withdrawal-country">Oznaka države *</label>
                                    <input
                                        class="form-control text-uppercase @error('country_code') is-invalid @enderror"
                                        id="withdrawal-country"
                                        type="text"
                                        name="country_code"
                                        value="{{ old('country_code', $prefill['country_code'] ?? 'HR') }}"
                                        autocomplete="country"
                                        required
                                        minlength="2"
                                        maxlength="2"
                                        pattern="[A-Za-z]{2}"
                                    >
                                    @error('country_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </section>

                        <section class="withdrawal-section" aria-labelledby="withdrawal-contract-title">
                            <h2 class="withdrawal-section__title" id="withdrawal-contract-title">
                                <span class="withdrawal-section__number">2</span>
                                Podaci ugovora i robe
                            </h2>

                            <div class="withdrawal-form-grid">
                                <div class="withdrawal-form-grid__full">
                                    <label class="form-label" for="withdrawal-order-number">Broj narudžbe / ugovora *</label>
                                    <input
                                        class="form-control @error('order_number') is-invalid @enderror"
                                        id="withdrawal-order-number"
                                        type="text"
                                        name="order_number"
                                        value="{{ old('order_number') }}"
                                        required
                                        maxlength="80"
                                    >
                                    @error('order_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <label class="form-label" for="withdrawal-contract-date">Datum narudžbe <span class="text-muted">(neobavezno)</span></label>
                                    <input
                                        class="form-control @error('contract_date') is-invalid @enderror"
                                        id="withdrawal-contract-date"
                                        type="date"
                                        name="contract_date"
                                        value="{{ old('contract_date') }}"
                                        max="{{ now()->toDateString() }}"
                                    >
                                    @error('contract_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <label class="form-label" for="withdrawal-received-date">Datum primitka robe <span class="text-muted">(neobavezno)</span></label>
                                    <input
                                        class="form-control @error('received_date') is-invalid @enderror"
                                        id="withdrawal-received-date"
                                        type="date"
                                        name="received_date"
                                        value="{{ old('received_date') }}"
                                        max="{{ now()->toDateString() }}"
                                    >
                                    @error('received_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="withdrawal-form-grid__full">
                                    <label class="form-label" for="withdrawal-items">Proizvodi / dio ugovora koji raskidate *</label>
                                    <textarea
                                        class="form-control @error('items') is-invalid @enderror"
                                        id="withdrawal-items"
                                        name="items"
                                        rows="6"
                                        placeholder="Navedite naziv, šifru i količinu proizvoda ili napišite da raskidate cijelu narudžbu."
                                        required
                                        maxlength="5000"
                                    >{{ old('items') }}</textarea>
                                    @error('items') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="withdrawal-form-grid__full">
                                    <label class="form-label" for="withdrawal-note">Dodatna napomena <span class="text-muted">(neobavezno)</span></label>
                                    <textarea
                                        class="form-control @error('note') is-invalid @enderror"
                                        id="withdrawal-note"
                                        name="note"
                                        rows="4"
                                        placeholder="Razlog raskida nije potrebno navesti."
                                        maxlength="5000"
                                    >{{ old('note') }}</textarea>
                                    @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </section>

                        <p class="withdrawal-field-help mt-4">
                            Podaci se obrađuju radi izvršenja zakonskih obveza trgovca i evidencije vaše izjave o raskidu.
                            U sljedećem koraku pregledat ćete nedvosmislenu izjavu prije konačnog slanja.
                        </p>
                        @error('recaptcha') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

                        <button class="withdrawal-submit mt-3" type="submit" data-withdrawal-submit>
                            Raskid ugovora
                            <i class="ci-arrow-right ms-2"></i>
                        </button>
                    </form>
                </div>
            </div>

            <aside class="withdrawal-card withdrawal-aside" aria-labelledby="withdrawal-important-title">
                <div class="withdrawal-card__body">
                    <h2 id="withdrawal-important-title">Važno prije slanja</h2>
                    <ul class="withdrawal-aside__list">
                        <li>Ugovor sklopljen na daljinu u pravilu možete raskinuti u roku od 14 dana bez navođenja razloga.</li>
                        <li>Za robu rok u pravilu počinje kada ste vi ili osoba koju ste odredili primili robu.</li>
                        <li>Robu je potrebno vratiti bez nepotrebnog odgađanja, najkasnije 14 dana od slanja izjave.</li>
                        <li>Povrat plaćenog iznosa, uključujući trošak najjeftinije standardne dostave, izvršava se najkasnije 14 dana od primitka izjave, istim sredstvom plaćanja i bez dodatnih naknada, osim ako je izričito dogovoreno drukčije.</li>
                        <li>Povrat novca može se zadržati dok roba ne bude vraćena ili dok ne dostavite dokaz da ste je poslali.</li>
                        <li>{{ $returnCostText }}</li>
                        <li>Odgovorni ste samo za umanjenje vrijednosti nastalo rukovanjem iznad onoga potrebnog za provjeru prirode, obilježja i funkcionalnosti robe.</li>
                        <li>Potvrda sa sadržajem izjave te datumom i vremenom podnošenja stiže na vaš e-mail.</li>
                    </ul>

                    @if (($withdrawalSettings['return_address'] ?? '') !== '')
                        <div class="withdrawal-address"><strong>Adresa za povrat robe</strong><br>{{ $withdrawalSettings['return_address'] }}</div>
                    @endif
                </div>
            </aside>
        </div>
    </div>
@endsection

@if ($captchaEnabled)
    @push('js_after')
        <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.sitekey') }}"></script>
    @endpush
@endif

@push('js_after')
    <script>
        (function () {
            var form = document.querySelector('[data-withdrawal-form]');

            if (!form) {
                return;
            }

            form.addEventListener('submit', function (event) {
                var captchaEnabled = form.dataset.recaptchaEnabled === '1';
                var tokenInput = form.querySelector('[data-withdrawal-recaptcha]');
                var button = form.querySelector('[data-withdrawal-submit]');

                if (!captchaEnabled || !tokenInput || tokenInput.value) {
                    if (button) {
                        button.disabled = true;
                    }

                    return;
                }

                event.preventDefault();

                if (!window.grecaptcha) {
                    return;
                }

                if (button) {
                    button.disabled = true;
                }

                window.grecaptcha.ready(function () {
                    window.grecaptcha
                        .execute(@json(config('services.recaptcha.sitekey')), { action: 'contract_withdrawal' })
                        .then(function (token) {
                            tokenInput.value = token || '';
                            form.submit();
                        })
                        .catch(function () {
                            if (button) {
                                button.disabled = false;
                            }
                        });
                });
            });
        })();
    </script>
@endpush
