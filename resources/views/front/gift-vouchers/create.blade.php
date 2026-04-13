@extends('front.layouts.app')
@section('title', \App\Models\Seo::appendBrand('Poklon bon'))
@section('description', \App\Models\Seo::description(null, 'Odaberite digitalni poklon bon i pošaljite ga e-mailom primatelju nakon uspješnog kartičnog plaćanja.'))

@push('css_after')
    <style>
        .gift-voucher-amount {
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 700;
            line-height: 1;
            color: #e50077;
        }

        .gift-voucher-scale {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: .5rem;
            font-size: .75rem;
            color: #7d879c;
        }
    </style>
@endpush

@section('content')
    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            <li class="breadcrumb-item text-nowrap active" aria-current="page">Poklon bon</li>
        </ol>
    </nav>

    <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">
        <div>
            <h1 class="h2 mb-2">Poklon bon</h1>
            <p class="text-muted mb-0">Digitalni poklon bon šaljemo e-mailom s vašom porukom i jedinstvenim kodom za popust.</p>
        </div>
    </section>

    @include('front.layouts.partials.session')

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <form action="{{ route('poklon.bon.store') }}" method="POST">
                        @csrf

                        <div class="mb-4 pb-2 border-bottom">
                            <label class="form-label fw-bold d-block">Vrijednost poklon bona</label>
                            @php($selectedAmount = old('amount', 50))
                            <div class="gift-voucher-amount mb-3"><span id="gift-voucher-amount">{{ (int) $selectedAmount }}</span> €</div>
                            <input
                                class="form-range"
                                id="gift-voucher-range"
                                type="range"
                                min="10"
                                max="300"
                                step="10"
                                value="{{ $selectedAmount }}"
                                oninput="document.getElementById('gift-voucher-amount').textContent = this.value; document.getElementById('gift-voucher-hidden').value = this.value;"
                            >
                            <input id="gift-voucher-hidden" type="hidden" name="amount" value="{{ $selectedAmount }}">
                            <div class="gift-voucher-scale mt-2">
                                <span>10 €</span>
                                <span>50 €</span>
                                <span>100 €</span>
                                <span>150 €</span>
                                <span>200 €</span>
                                <span>250 €</span>
                                <span class="text-end">300 €</span>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-6">
                                <div class="mb-3">
                                    <label class="form-label" for="recipient-name">Ime primatelja</label>
                                    <input class="form-control" id="recipient-name" type="text" name="recipient_name" value="{{ old('recipient_name') }}" placeholder="Npr. Ana">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="mb-3">
                                    <label class="form-label" for="recipient-email">E-mail primatelja <span class="text-danger">*</span></label>
                                    <input class="form-control" id="recipient-email" type="email" name="recipient_email" value="{{ old('recipient_email') }}" placeholder="primatelj@email.com" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="sender-name">Od koga je poklon</label>
                            <input class="form-control" id="sender-name" type="text" name="sender_name" value="{{ old('sender_name') }}" placeholder="Npr. Marko">
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="gift-message">Poruka <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="gift-message" name="message" rows="6" maxlength="1000" placeholder="Napišite kratku poruku za primatelja..." required>{{ old('message', 'Sretan poklon! Nadam se da ćeš pronaći nešto baš po svom guštu.') }}</textarea>
                            <div class="form-text">Kod i poruka šalju se tek nakon uspješnog kartičnog plaćanja.</div>
                        </div>

                        <button class="btn btn-primary btn-shadow" type="submit">
                            Dalje u košaricu <i class="ci-arrow-right ms-1"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100 border-0 bg-dark text-white shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="h4 text-white mb-3">Kako radi</h2>
                    <div class="mb-3">
                        <strong>1.</strong> Odaberite iznos od 10 € do 300 €.
                    </div>
                    <div class="mb-3">
                        <strong>2.</strong> Upišite e-mail primatelja i osobnu poruku.
                    </div>
                    <div class="mb-3">
                        <strong>3.</strong> Poklon bon dodaje se u košaricu kao zasebna narudžba.
                    </div>
                    <div class="mb-0">
                        <strong>4.</strong> Nakon uspješnog kartičnog plaćanja primatelj dobiva e-mail s kodom za popust.
                    </div>

                    <hr class="border-light opacity-25 my-4">

                    <p class="small text-white-50 mb-0">
                        Preporuka: poklon bonovi se kupuju zasebno, bez drugih artikala u istoj košarici.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
