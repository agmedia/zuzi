@extends('front.layouts.app')

@section('title', \App\Models\Seo::appendBrand('Podijelite dojam'))
@section('description', \App\Models\Seo::description(null, 'Napišite dojam jednom i podijelite ga na Googleu ili Facebooku.'))
@section('robots', 'noindex,follow')

@push('css_after')
    <style>
        .review-share-page {
            max-width: 920px;
            margin: 0 auto;
            padding: 1rem 0 3.5rem;
        }

        .review-share-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .review-share-title {
            margin-bottom: .45rem;
            color: #2b3445;
            letter-spacing: 0;
        }

        .review-share-lead {
            max-width: 42rem;
            margin: 0;
            color: #6b7280;
            font-size: 1rem;
            line-height: 1.65;
        }

        .review-share-panel {
            border: 1px solid #e5e7eb;
            border-radius: .5rem;
            background: #fff;
            box-shadow: 0 1rem 2.5rem rgba(43, 52, 69, .08);
            overflow: hidden;
        }

        .review-share-panel__body {
            padding: 1.25rem;
        }

        .review-share-book {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            margin-bottom: 1rem;
            padding: .55rem .75rem;
            border-radius: .35rem;
            background: #f3f4f6;
            color: #4b5563;
            font-size: .92rem;
            line-height: 1.35;
        }

        .review-share-book i {
            color: #e50077;
        }

        .review-share-textarea {
            min-height: 220px;
            resize: vertical;
            line-height: 1.65;
        }

        .review-share-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            margin-top: 1rem;
        }

        .review-share-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 3rem;
            border-radius: .4rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .review-share-actions .btn i {
            margin-right: .45rem;
        }

        .review-share-note {
            margin: .9rem 0 0;
            color: #6b7280;
            font-size: .88rem;
            line-height: 1.55;
        }

        .review-share-status {
            min-height: 1.4rem;
            margin-top: .65rem;
            color: #1f7a4d;
            font-size: .9rem;
            font-weight: 700;
        }

        @media (max-width: 767.98px) {
            .review-share-header {
                display: block;
            }

            .review-share-panel__body {
                padding: 1rem;
            }

            .review-share-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="review-share-page">
        <nav class="mb-4" aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap">
                <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                <li class="breadcrumb-item text-nowrap active" aria-current="page">Podijelite dojam</li>
            </ol>
        </nav>

        <section class="review-share-header">
            <div>
                <h1 class="h2 review-share-title">Podijelite dojam</h1>
                <p class="review-share-lead">
                    Napišite svoj dojam jednom, kopirajte ga i zatim otvorite Google ili Facebook.
                </p>
            </div>
        </section>

        <section class="review-share-panel" aria-labelledby="review-share-heading">
            <div class="review-share-panel__body">
                <h2 class="h5 mb-3" id="review-share-heading">Vaš review</h2>

                @if (filled($book))
                    <div class="review-share-book">
                        <i class="ci-book me-2" aria-hidden="true"></i>
                        <span>{{ $book }}</span>
                    </div>
                @endif

                <label class="form-label" for="review-share-text">Tekst dojma</label>
                <textarea
                    class="form-control review-share-textarea"
                    id="review-share-text"
                    data-review-text
                    maxlength="1600"
                    placeholder="Upišite ovdje svoj iskren dojam o kupnji, dostavi ili knjizi."
                >{{ $reviewText }}</textarea>

                <div class="review-share-actions">
                    <button class="btn btn-dark" type="button" data-copy-review>
                        <i class="ci-document-alt" aria-hidden="true"></i>
                        <span data-copy-label>Kopiraj review</span>
                    </button>
                    <a class="btn btn-outline-primary" href="{{ $googleReviewUrl }}" target="_blank" rel="noopener">
                        <i class="ci-google" aria-hidden="true"></i>
                        Google
                    </a>
                    <a class="btn btn-outline-primary" href="{{ $facebookReviewUrl }}" target="_blank" rel="noopener">
                        <i class="ci-facebook" aria-hidden="true"></i>
                        Facebook
                    </a>
                </div>

                <p class="review-share-note">
                    Nakon klika na Google ili Facebook zalijepite kopirani tekst u njihovo polje za recenziju/preporuku.
                </p>
                <div class="review-share-status" data-copy-status role="status" aria-live="polite"></div>
            </div>
        </section>
    </div>
@endsection

@push('js_after')
    <script>
        (function() {
            function bindReviewShareCopy() {
                const textInput = document.querySelector('[data-review-text]');
                const copyButton = document.querySelector('[data-copy-review]');
                const copyLabel = document.querySelector('[data-copy-label]');
                const status = document.querySelector('[data-copy-status]');

                if (!textInput || !copyButton || !copyLabel || !status) {
                    return;
                }

                function setStatus(message, isError) {
                    status.textContent = message;
                    status.style.color = isError ? '#b42318' : '#1f7a4d';
                }

                function fallbackCopy(text) {
                    textInput.focus();
                    textInput.select();
                    textInput.setSelectionRange(0, text.length);

                    return document.execCommand('copy');
                }

                copyButton.addEventListener('click', function() {
                    const text = textInput.value.trim();

                    if (!text) {
                        setStatus('Prvo upišite tekst reviewa.', true);
                        textInput.focus();
                        return;
                    }

                    const copied = navigator.clipboard && window.isSecureContext
                        ? navigator.clipboard.writeText(text)
                        : new Promise(function(resolve, reject) {
                            fallbackCopy(text) ? resolve() : reject();
                        });

                    copied
                        .then(function() {
                            copyLabel.textContent = 'Kopirano';
                            copyButton.classList.remove('btn-dark');
                            copyButton.classList.add('btn-success');
                            setStatus('Review je kopiran. Sada otvorite Google ili Facebook.', false);

                            setTimeout(function() {
                                copyLabel.textContent = 'Kopiraj review';
                                copyButton.classList.remove('btn-success');
                                copyButton.classList.add('btn-dark');
                            }, 2200);
                        })
                        .catch(function() {
                            setStatus('Kopiranje nije uspjelo. Označite tekst i kopirajte ga ručno.', true);
                        });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindReviewShareCopy, { once: true });
            } else {
                bindReviewShareCopy();
            }
        })();
    </script>
@endpush
