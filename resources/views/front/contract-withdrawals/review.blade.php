@extends('front.layouts.app')

@section('title', \App\Models\Seo::appendBrand('Pregled jednostranog raskida ugovora'))
@section('description', 'Pregled i konačna potvrda elektroničke izjave o jednostranom raskidu ugovora.')

@push('css_after')
    @include('front.contract-withdrawals.partials.styles')
@endpush

@section('content')
    <div class="withdrawal-page">
        <nav class="mb-4" aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap">
                <li class="breadcrumb-item">
                    <a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a>
                </li>
                <li class="breadcrumb-item">
                    <a class="text-nowrap" href="{{ route('contract-withdrawal.create') }}">Jednostrani raskid</a>
                </li>
                <li class="breadcrumb-item text-nowrap active" aria-current="page">Pregled</li>
            </ol>
        </nav>

        <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">
            <div>
                <h1 class="h2 mb-2">Pregledajte izjavu o raskidu</h1>
                <p class="withdrawal-page__intro">Provjerite podatke prije konačnog podnošenja. Izjava još nije poslana.</p>
            </div>
        </section>

        <div class="withdrawal-card mx-auto" style="max-width: 900px;">
            <div class="withdrawal-card__body">
                <div class="withdrawal-review-statement">
                    <small class="d-block text-uppercase text-muted mb-2" style="letter-spacing: .08em;">Vaša nedvosmislena izjava</small>
                    {{ $declaration }}
                </div>

                <dl class="withdrawal-review-list">
                    @foreach ([
                        'Ime i prezime' => $withdrawal['full_name'],
                        'E-mail' => $withdrawal['email'],
                        'Telefon' => $withdrawal['phone'],
                        'Adresa' => $withdrawal['address_line'].', '.$withdrawal['postal_code'].' '.$withdrawal['city'].', '.$withdrawal['country_code'],
                        'Broj narudžbe / ugovora' => $withdrawal['order_number'],
                        'Datum narudžbe' => $withdrawal['contract_date'],
                        'Datum primitka robe' => $withdrawal['received_date'],
                        'Proizvodi / dio ugovora' => $withdrawal['items'],
                        'Dodatna napomena' => $withdrawal['note'],
                    ] as $label => $value)
                        <div class="withdrawal-review-list__row">
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value !== '' ? $value : 'Nije navedeno' }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="alert alert-warning mt-4 mb-0" role="note">
                    Klikom na gumb <strong>Potvrditi raskid ugovora</strong> podnosite izjavu.
                    Bez odgađanja ćemo na navedeni e-mail poslati potvrdu sa sadržajem izjave te datumom i vremenom podnošenja.
                </div>

                <div class="withdrawal-review-actions">
                    <a class="withdrawal-edit-link" href="{{ route('contract-withdrawal.create') }}">
                        <i class="ci-arrow-left me-2"></i>Vrati se i izmijeni podatke
                    </a>

                    <form method="POST" action="{{ route('contract-withdrawal.store') }}" data-confirm-withdrawal-form>
                        @csrf
                        <input type="hidden" name="draft_token" value="{{ $draftToken }}">
                        <button class="withdrawal-submit" type="submit" data-confirm-withdrawal>
                            Potvrditi raskid ugovora
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script>
        (function () {
            var form = document.querySelector('[data-confirm-withdrawal-form]');

            if (!form) {
                return;
            }

            form.addEventListener('submit', function () {
                var button = form.querySelector('[data-confirm-withdrawal]');

                if (button) {
                    button.disabled = true;
                    button.textContent = 'Slanje izjave...';
                }
            });
        })();
    </script>
@endpush
