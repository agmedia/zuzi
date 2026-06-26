@php
    $timezone = (string) config('match_prediction.timezone', config('app.timezone', 'Europe/Zagreb'));
    $deadline = \Carbon\Carbon::parse((string) config('match_prediction.deadline'), $timezone);
    $isMatchPredictionOpen = (bool) config('match_prediction.enabled') && \Carbon\Carbon::now($timezone)->lt($deadline);
    $matchName = (string) config('match_prediction.match_name', 'Hrvatska – Gana');
    $prizeName = (string) config('match_prediction.prize_name', '30 EUR poklon bon');
    $matchPredictionErrors = $errors->matchPrediction;
    $croatiaFlag = asset('media/match-prediction/flag-croatia.png');
    $ghanaFlag = asset('media/match-prediction/flag-ghana.png');
@endphp

@once
    @push('css_after')
        <style>
            .match-prediction-widget {
                scroll-margin-top: 1.25rem;
            }

            .match-prediction-layout {
                display: grid;
                grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
                gap: 1.25rem;
                align-items: stretch;
            }

            .match-prediction-hero,
            .match-prediction-form-panel {
                border: 1px solid rgba(229, 0, 119, .11);
                border-radius: .75rem;
                overflow: hidden;
                box-shadow: 0 .6rem 1.75rem rgba(43, 52, 69, .08);
            }

            .match-prediction-hero {
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                min-height: 100%;
                padding: 2rem;
                background: #fff7fb;
                color: #373f50;
            }

            .match-prediction-form-panel {
                padding: 2rem;
                background: #fff;
            }

            .match-prediction-kicker {
                display: inline-flex;
                align-items: center;
                gap: .35rem;
                padding: .35rem .7rem;
                border-radius: 999px;
                background: #e50077;
                color: #fff;
                font-size: .75rem;
                font-weight: 700;
                letter-spacing: 0;
                box-shadow: 0 .35rem .9rem rgba(229, 0, 119, .18);
            }

            .match-prediction-title {
                color: #2f3747;
                font-size: clamp(2rem, 3vw, 3.1rem);
                line-height: 1.08;
            }

            .match-prediction-hero-copy {
                color: #596275;
            }

            .match-prediction-flags {
                position: relative;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: .8rem;
                margin: 1.5rem 0;
            }

            .match-prediction-flag {
                overflow: hidden;
                border-radius: .6rem;
                background: #fff;
                border: 1px solid rgba(43, 52, 69, .08);
                box-shadow: 0 .7rem 1.35rem rgba(43, 52, 69, .13);
            }

            .match-prediction-flag img {
                display: block;
                width: 100%;
                aspect-ratio: 5 / 3;
                object-fit: cover;
            }

            .match-prediction-versus {
                position: absolute;
                left: 50%;
                top: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 3.1rem;
                height: 3.1rem;
                border-radius: 50%;
                border: 3px solid rgba(255, 255, 255, .85);
                background: #e50077;
                color: #fff;
                font-weight: 800;
                transform: translate(-50%, -50%);
                box-shadow: 0 .6rem 1.2rem rgba(229, 0, 119, .24);
            }

            .match-prediction-meta {
                display: flex;
                flex-wrap: wrap;
                gap: .5rem;
                margin-top: 1.25rem;
            }

            .match-prediction-meta span {
                display: inline-flex;
                align-items: center;
                border-radius: 999px;
                padding: .45rem .7rem;
                background: rgba(255, 255, 255, .76);
                border: 1px solid rgba(191, 159, 76, .22);
                color: #4b566b;
                font-size: .82rem;
                line-height: 1.2;
            }

            .match-prediction-prize-note {
                display: inline-flex;
                max-width: 28rem;
                padding: .75rem .9rem;
                border-radius: .6rem;
                border: 1px solid rgba(229, 0, 119, .18);
                background: #fff;
                color: #4b5568;
                font-size: .92rem;
                line-height: 1.45;
            }

            .match-prediction-form-panel .form-control {
                border-color: #dfe5ee;
                background-color: #fff;
            }

            .match-prediction-form-panel .form-control:focus {
                border-color: rgba(229, 0, 119, .45);
                box-shadow: 0 0 0 .18rem rgba(229, 0, 119, .08);
            }

            .match-prediction-form-panel .form-check-input:checked {
                border-color: #e50077;
                background-color: #e50077;
            }

            .match-prediction-score-input {
                max-width: 8rem;
            }

            .match-prediction-honeypot {
                position: absolute;
                left: -9999px;
                width: 1px;
                height: 1px;
                overflow: hidden;
            }

            .match-prediction-widget .form-check-label {
                line-height: 1.35;
            }

            @media (max-width: 991.98px) {
                .match-prediction-layout {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 767.98px) {
                .match-prediction-widget {
                    padding-top: 1rem;
                }

                .match-prediction-hero,
                .match-prediction-form-panel {
                    border-radius: .6rem;
                }

                .match-prediction-hero,
                .match-prediction-form-panel {
                    padding: 1.25rem;
                }

                .match-prediction-score-input {
                    max-width: none;
                }

                .match-prediction-versus {
                    width: 2.6rem;
                    height: 2.6rem;
                    font-size: .82rem;
                }
            }
        </style>
    @endpush
@endonce

<section id="pogodi-rezultat" class="match-prediction-widget container-fluid py-4 py-lg-5">
    <div class="match-prediction-layout">
        <aside class="match-prediction-hero">
            <div>
                <span class="match-prediction-kicker mb-3">
                    <i class="ci-gift" aria-hidden="true"></i>
                    Promotivno natjecanje
                </span>

                <h1 class="match-prediction-title font-title mb-3">Pogodi rezultat: {{ $matchName }}</h1>
                <p class="match-prediction-hero-copy fs-lg mb-0">
                    Osvoji {{ $prizeName }}.
                </p>

                <div class="match-prediction-flags" aria-label="{{ $matchName }}">
                    <div class="match-prediction-flag">
                        <img src="{{ $croatiaFlag }}" alt="Zastava Hrvatske" width="640" height="320" loading="eager">
                    </div>
                    <div class="match-prediction-flag">
                        <img src="{{ $ghanaFlag }}" alt="Zastava Gane" width="640" height="427" loading="eager">
                    </div>
                    <span class="match-prediction-versus">VS</span>
                </div>

                <p class="match-prediction-prize-note mb-0">
                    Dobitnik može izabrati knjigu ili knjige po želji u vrijednosti do 30 EUR.
                </p>
            </div>

            <div class="match-prediction-meta">
                <span>Besplatno sudjelovanje</span>
                <span>Kupnja nije uvjet</span>
                <span>Do {{ $deadline->format('d.m.Y. H:i') }}</span>
            </div>
        </aside>

        <div class="match-prediction-form-panel">
                    @if (session('match_prediction_success'))
                        <div class="alert alert-success d-flex" role="alert">
                            <div class="alert-icon">
                                <i class="ci-check-circle"></i>
                            </div>
                            <div>{{ session('match_prediction_success') }}</div>
                        </div>
                    @endif

                    @if ($matchPredictionErrors->has('match_prediction'))
                        <div class="alert alert-danger" role="alert">
                            {{ $matchPredictionErrors->first('match_prediction') }}
                        </div>
                    @endif

                    @if (! $isMatchPredictionOpen)
                        <div class="alert alert-info mb-0" role="alert">
                            Prijave za promotivno natjecanje su završile.
                        </div>
                    @else
                        <form action="{{ route('match-predictions.store') }}" method="POST" class="mb-0">
                            @csrf

                            <div class="match-prediction-honeypot" aria-hidden="true">
                                <label for="match-prediction-website">Website</label>
                                <input type="text" id="match-prediction-website" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <input type="hidden" name="recaptcha" id="match-prediction-recaptcha">

                            @if ($matchPredictionErrors->has('recaptcha'))
                                <div class="alert alert-danger" role="alert">
                                    {{ $matchPredictionErrors->first('recaptcha') }}
                                </div>
                            @endif

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="match-prediction-first-name">Ime <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        class="form-control {{ $matchPredictionErrors->has('first_name') ? 'is-invalid' : '' }}"
                                        id="match-prediction-first-name"
                                        name="first_name"
                                        value="{{ old('first_name') }}"
                                        maxlength="100"
                                        autocomplete="given-name"
                                    >
                                    @if ($matchPredictionErrors->has('first_name'))
                                        <div class="invalid-feedback">{{ $matchPredictionErrors->first('first_name') }}</div>
                                    @endif
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="match-prediction-last-name">Prezime <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        class="form-control {{ $matchPredictionErrors->has('last_name') ? 'is-invalid' : '' }}"
                                        id="match-prediction-last-name"
                                        name="last_name"
                                        value="{{ old('last_name') }}"
                                        maxlength="100"
                                        autocomplete="family-name"
                                    >
                                    @if ($matchPredictionErrors->has('last_name'))
                                        <div class="invalid-feedback">{{ $matchPredictionErrors->first('last_name') }}</div>
                                    @endif
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="match-prediction-email">Email <span class="text-danger">*</span></label>
                                    <input
                                        type="email"
                                        class="form-control {{ $matchPredictionErrors->has('email') ? 'is-invalid' : '' }}"
                                        id="match-prediction-email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        maxlength="255"
                                        autocomplete="email"
                                    >
                                    @if ($matchPredictionErrors->has('email'))
                                        <div class="invalid-feedback">{{ $matchPredictionErrors->first('email') }}</div>
                                    @endif
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label d-block">Rezultat <span class="text-danger">*</span></label>
                                    <div class="d-flex align-items-start gap-2">
                                        <div class="flex-fill">
                                            <input
                                                type="number"
                                                class="form-control match-prediction-score-input {{ $matchPredictionErrors->has('croatia_goals') ? 'is-invalid' : '' }}"
                                                id="match-prediction-croatia-goals"
                                                name="croatia_goals"
                                                value="{{ old('croatia_goals') }}"
                                                min="0"
                                                max="20"
                                                step="1"
                                                placeholder="Hrvatska"
                                            >
                                            @if ($matchPredictionErrors->has('croatia_goals'))
                                                <div class="invalid-feedback">{{ $matchPredictionErrors->first('croatia_goals') }}</div>
                                            @endif
                                        </div>
                                        <span class="pt-2 fw-bold">:</span>
                                        <div class="flex-fill">
                                            <input
                                                type="number"
                                                class="form-control match-prediction-score-input {{ $matchPredictionErrors->has('england_goals') ? 'is-invalid' : '' }}"
                                                id="match-prediction-england-goals"
                                                name="england_goals"
                                                value="{{ old('england_goals') }}"
                                                min="0"
                                                max="20"
                                                step="1"
                                                placeholder="Gana"
                                            >
                                            @if ($matchPredictionErrors->has('england_goals'))
                                                <div class="invalid-feedback">{{ $matchPredictionErrors->first('england_goals') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="match-prediction-first-goal-minute">
                                        Minuta prvog gola <span class="text-muted">(preporučeno)</span>
                                    </label>
                                    <input
                                        type="number"
                                        class="form-control {{ $matchPredictionErrors->has('first_goal_minute') ? 'is-invalid' : '' }}"
                                        id="match-prediction-first-goal-minute"
                                        name="first_goal_minute"
                                        value="{{ old('first_goal_minute') }}"
                                        min="1"
                                        max="120"
                                        step="1"
                                    >
                                    @if ($matchPredictionErrors->has('first_goal_minute'))
                                        <div class="invalid-feedback">{{ $matchPredictionErrors->first('first_goal_minute') }}</div>
                                    @endif
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="match-prediction-yellow-cards-total">
                                        Ukupan broj žutih kartona <span class="text-muted">(preporučeno)</span>
                                    </label>
                                    <input
                                        type="number"
                                        class="form-control {{ $matchPredictionErrors->has('yellow_cards_total') ? 'is-invalid' : '' }}"
                                        id="match-prediction-yellow-cards-total"
                                        name="yellow_cards_total"
                                        value="{{ old('yellow_cards_total') }}"
                                        min="0"
                                        max="30"
                                        step="1"
                                    >
                                    @if ($matchPredictionErrors->has('yellow_cards_total'))
                                        <div class="invalid-feedback">{{ $matchPredictionErrors->first('yellow_cards_total') }}</div>
                                    @endif
                                </div>

                                <div class="col-12">
                                    <div class="form-check mb-2">
                                        <input
                                            class="form-check-input {{ $matchPredictionErrors->has('accepted_rules') ? 'is-invalid' : '' }}"
                                            type="checkbox"
                                            value="1"
                                            id="match-prediction-accepted-rules"
                                            name="accepted_rules"
                                            {{ old('accepted_rules') ? 'checked' : '' }}
                                        >
                                        <label class="form-check-label" for="match-prediction-accepted-rules">
                                            Prihvaćam pravila promotivnog natjecanja.
                                            <a href="{{ route('match-predictions.rules') }}" target="_blank" rel="noopener">Pročitaj pravila</a>
                                        </label>
                                        @if ($matchPredictionErrors->has('accepted_rules'))
                                            <div class="invalid-feedback d-block">{{ $matchPredictionErrors->first('accepted_rules') }}</div>
                                        @endif
                                    </div>

                                    <div class="form-check mb-2">
                                        <input
                                            class="form-check-input {{ $matchPredictionErrors->has('accepted_privacy') ? 'is-invalid' : '' }}"
                                            type="checkbox"
                                            value="1"
                                            id="match-prediction-accepted-privacy"
                                            name="accepted_privacy"
                                            {{ old('accepted_privacy') ? 'checked' : '' }}
                                        >
                                        <label class="form-check-label" for="match-prediction-accepted-privacy">
                                            Upoznat/a sam s obradom osobnih podataka za potrebe provedbe ovog promotivnog natjecanja.
                                        </label>
                                        @if ($matchPredictionErrors->has('accepted_privacy'))
                                            <div class="invalid-feedback d-block">{{ $matchPredictionErrors->first('accepted_privacy') }}</div>
                                        @endif
                                    </div>

                                    <div class="form-check">
                                        <input
                                            class="form-check-input {{ $matchPredictionErrors->has('newsletter_consent') ? 'is-invalid' : '' }}"
                                            type="checkbox"
                                            value="1"
                                            id="match-prediction-newsletter-consent"
                                            name="newsletter_consent"
                                            {{ old('newsletter_consent') ? 'checked' : '' }}
                                        >
                                        <label class="form-check-label" for="match-prediction-newsletter-consent">
                                            Želim primati Zuzi newsletter.
                                        </label>
                                        @if ($matchPredictionErrors->has('newsletter_consent'))
                                            <div class="invalid-feedback d-block">{{ $matchPredictionErrors->first('newsletter_consent') }}</div>
                                        @endif
                                    </div>
                                </div>

                                <div class="col-12 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 pt-2">
                                    <small class="text-muted">
                                        Prijave se zatvaraju {{ $deadline->format('d.m.Y. H:i') }}.
                                    </small>
                                    <button class="btn btn-primary" type="submit">
                                        Pošalji prognozu
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
        </div>
    </div>
</section>
