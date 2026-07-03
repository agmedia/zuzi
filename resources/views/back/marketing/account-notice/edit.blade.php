@extends('back.layouts.backend')

@php
    $hasOldInput = old('_token') !== null;
    $active = $hasOldInput ? old('active') : $notice['active'];
    $preview = [
        'title' => old('title', $notice['title']),
        'intro' => old('intro', $notice['intro']),
        'coupon_label' => old('coupon_label', $notice['coupon_label']),
        'coupon_code' => old('coupon_code', $notice['coupon_code']),
        'discount_text' => old('discount_text', $notice['discount_text']),
        'outro' => old('outro', $notice['outro']),
        'button_text' => old('button_text', $notice['button_text']),
        'valid_until' => old('valid_until', $notice['valid_until']),
    ];

    try {
        $previewValidUntil = $preview['valid_until'] ? \Illuminate\Support\Carbon::parse($preview['valid_until'])->format('d.m.Y.') : null;
    } catch (\Throwable $e) {
        $previewValidUntil = null;
    }
@endphp

@push('css_after')
    <style>
        .account-notice-preview {
            border: 1px solid #edf0f5;
            border-radius: 6px;
            background: #fff;
            color: #1f2933;
            padding: 2rem;
            text-align: center;
        }

        .account-notice-preview__title,
        .account-notice-preview__code {
            color: #e50077;
            font-weight: 700;
        }

        .account-notice-preview__title {
            font-size: 1.9rem;
            line-height: 1.2;
        }

        .account-notice-preview__coupon {
            border: 2px dashed #e50077;
            margin: 1.75rem 0;
            padding: 1.5rem;
        }

        .account-notice-preview__code {
            font-size: 2.25rem;
            letter-spacing: 0;
        }

        .account-notice-preview__button {
            display: inline-block;
            border-radius: 6px;
            background: #e50077;
            color: #fff;
            font-weight: 700;
            margin-top: 1.25rem;
            padding: .85rem 2.5rem;
        }

        .account-notice-mail-stat {
            min-height: 104px;
        }
    </style>
@endpush

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Obavijest u računu</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">Marketing</li>
                        <li class="breadcrumb-item active" aria-current="page">Obavijest u računu</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <form action="{{ route('account.notice.update') }}" method="POST">
            @csrf
            {{ method_field('PATCH') }}

            <div class="row">
                <div class="col-lg-7">
                    <div class="block">
                        <div class="block-header block-header-default">
                            <h3 class="block-title">Tekst obavijesti</h3>
                            <div class="block-options">
                                <div class="custom-control custom-switch custom-control-success block-options-item">
                                    <input type="checkbox" class="custom-control-input" id="active-switch" name="active" value="1" @if($active) checked @endif>
                                    <label class="custom-control-label pt-1" for="active-switch">Prikaži</label>
                                </div>
                            </div>
                        </div>
                        <div class="block-content">
                            <div class="form-group">
                                <label for="title-input">Naslov</label>
                                <input type="text" class="form-control @error('title') is-invalid @enderror" id="title-input" name="title" value="{{ old('title', $notice['title']) }}">
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="intro-input">Uvodni tekst</label>
                                <textarea class="form-control @error('intro') is-invalid @enderror" id="intro-input" name="intro" rows="3">{{ old('intro', $notice['intro']) }}</textarea>
                                @error('intro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group row">
                                <div class="col-md-6">
                                    <label for="coupon-label-input">Labela kupona</label>
                                    <input type="text" class="form-control @error('coupon_label') is-invalid @enderror" id="coupon-label-input" name="coupon_label" value="{{ old('coupon_label', $notice['coupon_label']) }}">
                                    @error('coupon_label') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="coupon-code-input">Kupon kod</label>
                                    <input type="text" class="form-control @error('coupon_code') is-invalid @enderror" id="coupon-code-input" name="coupon_code" value="{{ old('coupon_code', $notice['coupon_code']) }}">
                                    @error('coupon_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="discount-text-input">Tekst popusta</label>
                                <input type="text" class="form-control @error('discount_text') is-invalid @enderror" id="discount-text-input" name="discount_text" value="{{ old('discount_text', $notice['discount_text']) }}">
                                @error('discount_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="outro-input">Završni tekst</label>
                                <textarea class="form-control @error('outro') is-invalid @enderror" id="outro-input" name="outro" rows="3">{{ old('outro', $notice['outro']) }}</textarea>
                                @error('outro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group row">
                                <div class="col-md-6">
                                    <label for="button-text-input">Tekst gumba</label>
                                    <input type="text" class="form-control @error('button_text') is-invalid @enderror" id="button-text-input" name="button_text" value="{{ old('button_text', $notice['button_text']) }}">
                                    @error('button_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="button-url-input">Link gumba</label>
                                    <input type="text" class="form-control @error('button_url') is-invalid @enderror" id="button-url-input" name="button_url" value="{{ old('button_url', $notice['button_url']) }}">
                                    @error('button_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="valid-until-input">Kupon vrijedi do</label>
                                <input type="date" class="form-control @error('valid_until') is-invalid @enderror" id="valid-until-input" name="valid_until" value="{{ old('valid_until', $notice['valid_until']) }}">
                                @error('valid_until') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="block-content bg-body-light">
                            <button type="submit" class="btn btn-hero-success mb-3">
                                <i class="fas fa-save mr-1"></i> Snimi
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="block">
                        <div class="block-header block-header-default">
                            <h3 class="block-title">Pregled</h3>
                        </div>
                        <div class="block-content">
                            <div class="account-notice-preview mb-4">
                                <h2 class="account-notice-preview__title">{{ $preview['title'] }}</h2>
                                <p class="font-size-lg mt-3 mb-0">{{ $preview['intro'] }}</p>
                                <div class="account-notice-preview__coupon">
                                    <div class="font-size-lg">{{ $preview['coupon_label'] }}</div>
                                    <div class="account-notice-preview__code">{{ $preview['coupon_code'] }}</div>
                                    <div class="font-w700 font-size-lg">{{ $preview['discount_text'] }}</div>
                                </div>
                                <p class="font-size-lg mb-0">{{ $preview['outro'] }}</p>
                                @if($preview['button_text'])
                                    <span class="account-notice-preview__button">{{ $preview['button_text'] }}</span>
                                @endif
                                @if($previewValidUntil)
                                    <div class="text-muted mt-4">Kupon vrijedi do: <strong>{{ $previewValidUntil }}</strong></div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @php($estimatedSeconds = max((int) $mailDefaultLimit - 1, 0) * (int) $mailDefaultDelay)

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Slanje maila korisnicima</h3>
                <div class="block-options">
                    <button type="button" class="btn btn-sm btn-alt-secondary mr-2" id="account-notice-test-mail-button" onclick="sendAccountNoticeTestMail()">
                        <i class="fa fa-envelope-open-text mr-1"></i>
                        Test na {{ $mailTestEmail }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-alt-primary"
                        id="account-notice-send-mail-button"
                        onclick="sendAccountNoticeMailBatch()"
                        {{ (int) $mailStats['remaining'] > 0 ? '' : 'disabled' }}
                    >
                        <i class="fa fa-paper-plane mr-1"></i>
                        Pošalji batch
                    </button>
                </div>
            </div>
            <div class="block-content bg-body-dark">
                <div class="row align-items-end">
                    <div class="form-group col-md-3">
                        <label for="account-notice-mail-limit">Broj korisnika</label>
                        <input type="number" class="form-control" id="account-notice-mail-limit" min="1" max="{{ \App\Services\AccountNoticeMailService::MAX_BATCH_LIMIT }}" value="{{ $mailDefaultLimit }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="account-notice-mail-delay">Razmak slanja</label>
                        <select class="form-control" id="account-notice-mail-delay">
                            @foreach ($mailDelayOptions as $delayOption)
                                <option value="{{ $delayOption }}" {{ (int) $mailDefaultDelay === (int) $delayOption ? 'selected' : '' }}>{{ $delayOption }} sekundi</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Procjena za uneseni batch</label>
                        <div class="form-control-plaintext font-w600" id="account-notice-mail-duration-label">
                            {{ $estimatedSeconds >= 3600 ? floor($estimatedSeconds / 3600) . 'h ' . floor(($estimatedSeconds % 3600) / 60) . 'm' : floor($estimatedSeconds / 60) . 'm ' . ($estimatedSeconds % 60) . 's' }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="block-content">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="account-notice-mail-stat p-3 bg-body-light rounded">
                            <div class="font-size-sm text-muted text-uppercase">Customer korisnici</div>
                            <div class="font-size-h2 font-w600 mb-0">{{ number_format((int) $mailStats['total'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="account-notice-mail-stat p-3 bg-body-light rounded">
                            <div class="font-size-sm text-muted text-uppercase">Poslano za ovaj sadržaj</div>
                            <div class="font-size-h2 font-w600 mb-0">{{ number_format((int) $mailStats['sent'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="account-notice-mail-stat p-3 bg-body-light rounded">
                            <div class="font-size-sm text-muted text-uppercase">Preostalo</div>
                            <div class="font-size-h2 font-w600 mb-0">{{ number_format((int) $mailStats['remaining'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>

                <div id="accountNoticeMailProgress" class="alert alert-info d-none" role="alert">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span id="accountNoticeMailProgressText" class="font-w600">Slanje mailova...</span>
                        <span id="accountNoticeMailProgressCount" class="font-size-sm"></span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div id="accountNoticeMailProgressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script>
        let accountNoticeMailSending = false;
        const accountNoticeMailStats = @json($mailStats);

        function accountNoticeMailPayload() {
            return {
                limit: document.getElementById('account-notice-mail-limit').value,
                delay: document.getElementById('account-notice-mail-delay').value
            };
        }

        async function sendAccountNoticeTestMail() {
            const button = document.getElementById('account-notice-test-mail-button');

            if (button) {
                button.disabled = true;
            }

            try {
                const response = await axios.post("{{ route('account.notice.mail.test') }}");

                successToast.fire({
                    text: response.data.message || 'Testni mail je poslan.'
                });
            } catch (error) {
                errorToast.fire(error?.response?.data?.error || 'Testni mail nije poslan.');
            }

            if (button) {
                button.disabled = false;
            }
        }

        async function sendAccountNoticeMailBatch() {
            if (accountNoticeMailSending) {
                return;
            }

            setAccountNoticeMailControls(false);
            setAccountNoticeMailProgress('Provjera primatelja...', 0, 0);

            const payload = accountNoticeMailPayload();
            let response;

            try {
                response = await axios.post("{{ route('account.notice.recipients') }}", payload);
            } catch (error) {
                setAccountNoticeMailControls(true);
                errorToast.fire(error?.response?.data?.error || 'Nije moguće dohvatiti korisnike za slanje.');
                return;
            }

            const userIds = Array.isArray(response.data.user_ids) ? response.data.user_ids : [];
            const delaySeconds = Number(response.data.delay_seconds || payload.delay || 8);

            if (!userIds.length) {
                setAccountNoticeMailControls(true);
                setAccountNoticeMailProgress('', 0, 0, true);
                errorToast.fire('Nema preostalih korisnika za ovaj sadržaj.');
                return;
            }

            const estimatedSeconds = Math.max(userIds.length - 1, 0) * delaySeconds;
            const confirmation = await Swal.fire({
                title: 'Poslati obavijest?',
                html: [
                    `Naslov: <strong>${escapeAccountNoticeMailHtml(response.data.notice_title || '')}</strong>`,
                    `Ovaj batch: <strong>${userIds.length}</strong> korisnika`,
                    `Preostalo ukupno: <strong>${response.data.stats?.remaining || userIds.length}</strong>`,
                    `Razmak: <strong>${delaySeconds} sekundi</strong>`,
                    `Procjena trajanja: <strong>${formatAccountNoticeMailDuration(estimatedSeconds)}</strong>`
                ].join('<br>'),
                type: 'question',
                showCancelButton: true,
                confirmButtonText: 'Pošalji',
                cancelButtonText: 'Odustani',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary mr-2',
                    cancelButton: 'btn btn-alt-secondary'
                }
            });

            if (!confirmation.value) {
                setAccountNoticeMailControls(true);
                setAccountNoticeMailProgress('', 0, 0, true);
                return;
            }

            accountNoticeMailSending = true;
            setAccountNoticeMailProgress('Priprema slanja...', 0, userIds.length);

            let sentCount = 0;
            const failures = [];

            for (let index = 0; index < userIds.length; index++) {
                const userId = Number(userIds[index]);

                setAccountNoticeMailProgress(`Slanje korisniku #${userId}`, index, userIds.length);

                try {
                    const sendResponse = await axios.post("{{ route('account.notice.mail.send') }}", {
                        user_id: userId
                    });

                    if (sendResponse.data.message) {
                        sentCount++;
                    } else {
                        failures.push(`#${userId}: ${sendResponse.data.error || 'Greška prilikom slanja maila.'}`);
                    }
                } catch (error) {
                    failures.push(`#${userId}: ${error?.response?.data?.error || 'Greška prilikom slanja maila.'}`);
                }

                setAccountNoticeMailProgress(`Poslano ${sentCount} od ${userIds.length}`, index + 1, userIds.length);

                if (index < userIds.length - 1) {
                    await waitAccountNoticeMail(delaySeconds * 1000);
                }
            }

            accountNoticeMailSending = false;
            setAccountNoticeMailControls(true);

            const failuresText = failures.length
                ? `<br><br><strong>Greške:</strong><br>${failures.slice(0, 10).map(escapeAccountNoticeMailHtml).join('<br>')}`
                : '';

            Swal.fire({
                type: failures.length ? 'warning' : 'success',
                title: 'Slanje završeno',
                html: `Poslano: ${sentCount}${failuresText}`,
                confirmButtonText: 'U redu',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            }).then(() => location.reload());
        }

        function setAccountNoticeMailControls(isEnabled) {
            [
                document.getElementById('account-notice-send-mail-button'),
                document.getElementById('account-notice-test-mail-button'),
                document.getElementById('account-notice-mail-limit'),
                document.getElementById('account-notice-mail-delay')
            ].filter(Boolean).forEach((control) => {
                control.disabled = !isEnabled;
            });

            if (isEnabled && accountNoticeMailStats.remaining <= 0) {
                const sendButton = document.getElementById('account-notice-send-mail-button');

                if (sendButton) {
                    sendButton.disabled = true;
                }
            }
        }

        function setAccountNoticeMailProgress(text, completed, total, hide = false) {
            const progress = document.getElementById('accountNoticeMailProgress');
            const progressText = document.getElementById('accountNoticeMailProgressText');
            const progressCount = document.getElementById('accountNoticeMailProgressCount');
            const progressBar = document.getElementById('accountNoticeMailProgressBar');

            if (!progress || !progressText || !progressCount || !progressBar) {
                return;
            }

            if (hide) {
                progress.classList.add('d-none');
                return;
            }

            const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;

            progress.classList.remove('d-none');
            progressText.textContent = text;
            progressCount.textContent = total > 0 ? `${completed}/${total}` : '';
            progressBar.style.width = `${percentage}%`;
            progressBar.setAttribute('aria-valuenow', percentage);
        }

        function updateAccountNoticeMailEstimate() {
            const limit = Math.max(Number(document.getElementById('account-notice-mail-limit')?.value) || 0, 1);
            const delay = Math.max(Number(document.getElementById('account-notice-mail-delay')?.value) || 0, 1);
            const batchSize = Math.min(limit, Number(accountNoticeMailStats.remaining) || limit);
            const estimatedSeconds = Math.max(batchSize - 1, 0) * delay;
            const label = document.getElementById('account-notice-mail-duration-label');

            if (label) {
                label.textContent = formatAccountNoticeMailDuration(estimatedSeconds);
            }
        }

        function formatAccountNoticeMailDuration(seconds) {
            seconds = Math.max(Number(seconds) || 0, 0);
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const rest = seconds % 60;

            if (hours > 0) {
                return `${hours}h ${minutes}m`;
            }

            return `${minutes}m ${rest}s`;
        }

        function waitAccountNoticeMail(ms) {
            return new Promise((resolve) => setTimeout(resolve, ms));
        }

        function escapeAccountNoticeMailHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        document.getElementById('account-notice-mail-limit')?.addEventListener('input', updateAccountNoticeMailEstimate);
        document.getElementById('account-notice-mail-delay')?.addEventListener('change', updateAccountNoticeMailEstimate);
    </script>
@endpush
