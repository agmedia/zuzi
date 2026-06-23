@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Promo mailovi</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">Marketing</li>
                        <li class="breadcrumb-item active" aria-current="page">Promo mailovi</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        @php($estimatedSeconds = max((int) $candidateCount - 1, 0) * (int) $filters['delay'])

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Završene narudžbe bez promo maila</h3>
                <div class="block-options">
                    <button
                        type="button"
                        class="btn btn-sm btn-alt-primary"
                        id="completed-promo-send-button"
                        onclick="sendCompletedPromoRange()"
                        {{ $candidateCount > 0 ? '' : 'disabled' }}
                    >
                        <i class="fa fa-paper-plane mr-1"></i>
                        Pošalji svima -{{ (int) $discount }}%
                    </button>
                </div>
            </div>
            <div class="block-content bg-body-dark">
                <form action="{{ route('marketing.completed-promo') }}" method="GET" class="row align-items-end">
                    <div class="form-group col-md-2">
                        <label for="completed-promo-from">Od</label>
                        <input type="date" class="form-control" id="completed-promo-from" name="from" value="{{ $filters['from'] }}">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="completed-promo-to">Do</label>
                        <input type="date" class="form-control" id="completed-promo-to" name="to" value="{{ $filters['to'] }}">
                    </div>
                    <div class="form-group col-md-2">
                        <label for="completed-promo-delay">Razmak slanja</label>
                        <select class="form-control" id="completed-promo-delay" name="delay">
                            @foreach ([3, 5, 8, 10, 15, 30] as $delayOption)
                                <option value="{{ $delayOption }}" {{ (int) $filters['delay'] === $delayOption ? 'selected' : '' }}>{{ $delayOption }} sekundi</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="completed-promo-search">Pretraga</label>
                        <input type="text" class="form-control" id="completed-promo-search" name="search" value="{{ $filters['search'] }}" placeholder="Broj narudžbe, ime, prezime, email ili artikl">
                    </div>
                    <div class="form-group col-md-2 d-flex">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fa fa-search mr-1"></i> Filtriraj
                        </button>
                        <a href="{{ route('marketing.completed-promo') }}" class="btn btn-alt-secondary">Očisti</a>
                    </div>
                </form>
            </div>
            <div class="block-content">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="p-3 bg-body-light rounded">
                            <div class="font-size-sm text-muted text-uppercase">Za slanje</div>
                            <div class="font-size-h2 font-w600 mb-0">{{ number_format($candidateCount, 0, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="p-3 bg-body-light rounded">
                            <div class="font-size-sm text-muted text-uppercase">Popust</div>
                            <div class="font-size-h2 font-w600 mb-0">-{{ (int) $discount }}%</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="p-3 bg-body-light rounded">
                            <div class="font-size-sm text-muted text-uppercase">Procjena trajanja</div>
                            <div class="font-size-h2 font-w600 mb-0" id="completed-promo-duration-label">
                                {{ $estimatedSeconds >= 3600 ? floor($estimatedSeconds / 3600) . 'h ' . floor(($estimatedSeconds % 3600) / 60) . 'm' : floor($estimatedSeconds / 60) . 'm ' . ($estimatedSeconds % 60) . 's' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div id="completedPromoProgress" class="alert alert-info d-none" role="alert">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span id="completedPromoProgressText" class="font-w600">Slanje mailova...</span>
                        <span id="completedPromoProgressCount" class="font-size-sm"></span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div id="completedPromoProgressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-borderless table-vcenter font-size-sm">
                        <thead class="thead-light">
                        <tr>
                            <th style="width: 90px;">Narudžba</th>
                            <th style="width: 120px;">Datum</th>
                            <th>Kupac</th>
                            <th>Email</th>
                            <th class="text-right" style="width: 120px;">Vrijednost</th>
                            <th class="text-center" style="width: 110px;">Mail</th>
                            <th class="text-right" style="width: 90px;">Detalji</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($orders as $order)
                            <tr data-completed-promo-row="{{ $order->id }}">
                                <td>
                                    <a class="font-w600" href="{{ route('orders.show', ['order' => $order]) }}">#{{ $order->id }}</a>
                                </td>
                                <td>{{ \Illuminate\Support\Carbon::make($order->created_at)->format('d.m.Y') }}</td>
                                <td>{{ trim($order->payment_fname . ' ' . $order->payment_lname) }}</td>
                                <td>{{ $order->payment_email }}</td>
                                <td class="text-right">€ {{ number_format($order->total, 2, ',', '.') }}</td>
                                <td class="text-center">
                                    <span class="badge badge-warning" data-completed-promo-status="{{ $order->id }}">Spremno</span>
                                </td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-alt-secondary" href="{{ route('orders.show', ['order' => $order]) }}">
                                        <i class="fa fa-fw fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">Nema narudžbi za odabrani raspon.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $orders->links() }}
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script>
        let completedPromoSending = false;
        const completedPromoDiscount = @json((int) $discount);

        function completedPromoPayload() {
            return {
                from: document.getElementById('completed-promo-from').value,
                to: document.getElementById('completed-promo-to').value,
                delay: document.getElementById('completed-promo-delay').value,
                search: document.getElementById('completed-promo-search').value
            };
        }

        async function sendCompletedPromoRange() {
            if (completedPromoSending) {
                return;
            }

            setCompletedPromoControls(false);
            setCompletedPromoProgress('Provjera odabranog raspona...', 0, 0);

            let payload = completedPromoPayload();
            let response;

            try {
                response = await axios.post("{{ route('marketing.completed-promo.candidates') }}", payload);
            } catch (error) {
                setCompletedPromoControls(true);
                errorToast.fire(error?.response?.data?.error || 'Nije moguće dohvatiti narudžbe za slanje.');
                return;
            }

            const orderIds = Array.isArray(response.data.order_ids) ? response.data.order_ids : [];
            const delaySeconds = Number(response.data.delay_seconds || payload.delay || 8);

            if (!orderIds.length) {
                setCompletedPromoControls(true);
                errorToast.fire('Nema narudžbi za slanje u odabranom rasponu.');
                return;
            }

            const estimatedSeconds = Math.max(orderIds.length - 1, 0) * delaySeconds;
            const confirmation = await Swal.fire({
                title: 'Poslati promo mailove?',
                html: [
                    `Narudžbi: <strong>${orderIds.length}</strong>`,
                    `Popust: <strong>-${completedPromoDiscount}%</strong>`,
                    `Razmak: <strong>${delaySeconds} sekundi</strong>`,
                    `Raspon: <strong>${escapeHtml(response.data.from)} - ${escapeHtml(response.data.to)}</strong>`,
                    `Procjena trajanja: <strong>${formatDuration(estimatedSeconds)}</strong>`
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
                setCompletedPromoControls(true);
                setCompletedPromoProgress('', 0, 0, true);
                return;
            }

            completedPromoSending = true;
            setCompletedPromoProgress('Priprema slanja...', 0, orderIds.length);

            let sentCount = 0;
            const failures = [];

            for (let index = 0; index < orderIds.length; index++) {
                const orderId = Number(orderIds[index]);

                setCompletedPromoProgress(`Slanje narudžbe #${orderId}`, index, orderIds.length);

                try {
                    const sendResponse = await axios.post("{{ route('api.order.send.unfinished-promo') }}", {
                        order_id: orderId,
                        discount: completedPromoDiscount
                    });

                    if (sendResponse.data.message) {
                        sentCount++;
                        markCompletedPromoOrderSent(orderId);
                    } else {
                        failures.push(`#${orderId}: ${sendResponse.data.error || 'Greška prilikom slanja promo maila.'}`);
                    }
                } catch (error) {
                    failures.push(`#${orderId}: ${error?.response?.data?.error || 'Greška prilikom slanja promo maila.'}`);
                }

                setCompletedPromoProgress(`Poslano ${sentCount} od ${orderIds.length}`, index + 1, orderIds.length);

                if (index < orderIds.length - 1) {
                    await wait(delaySeconds * 1000);
                }
            }

            completedPromoSending = false;
            setCompletedPromoControls(true);

            const failuresText = failures.length
                ? `<br><br><strong>Greške:</strong><br>${failures.slice(0, 10).map(escapeHtml).join('<br>')}`
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

        function setCompletedPromoControls(isEnabled) {
            [
                document.getElementById('completed-promo-send-button'),
                document.getElementById('completed-promo-from'),
                document.getElementById('completed-promo-to'),
                document.getElementById('completed-promo-delay'),
                document.getElementById('completed-promo-search')
            ].filter(Boolean).forEach((control) => {
                control.disabled = !isEnabled;
            });
        }

        function setCompletedPromoProgress(text, completed, total, hide = false) {
            const progress = document.getElementById('completedPromoProgress');
            const progressText = document.getElementById('completedPromoProgressText');
            const progressCount = document.getElementById('completedPromoProgressCount');
            const progressBar = document.getElementById('completedPromoProgressBar');

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

        function markCompletedPromoOrderSent(orderId) {
            const row = document.querySelector(`[data-completed-promo-row="${orderId}"]`);
            const badge = document.querySelector(`[data-completed-promo-status="${orderId}"]`);

            if (row) {
                row.classList.add('table-success');
            }

            if (badge) {
                badge.className = 'badge badge-success';
                badge.textContent = 'Poslano';
            }
        }

        function formatDuration(seconds) {
            seconds = Math.max(Number(seconds) || 0, 0);
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const rest = seconds % 60;

            if (hours > 0) {
                return `${hours}h ${minutes}m`;
            }

            return `${minutes}m ${rest}s`;
        }

        function wait(ms) {
            return new Promise((resolve) => setTimeout(resolve, ms));
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
@endpush
