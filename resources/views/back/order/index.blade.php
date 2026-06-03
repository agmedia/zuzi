@extends('back.layouts.backend')
@push('css_before')

    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">


@endpush

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Narudžbe</h1>
            </div>
        </div>
    </div>


    <!-- Page Content -->
    <div class="content">
        @include('back.layouts.partials.session')
        <!-- All Orders -->
        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Lista narudžbi <small class="font-weight-light">{{ $orders->total() }}</small></h3>
              {{--   <div class="block-options d-none d-xl-block">
                    <div class="form-group mb-0 mr-2">
                        <select class="js-select2 form-control" id="status-select" name="status" style="width: 100%;" data-placeholder="Promjeni status narudžbe">
                            <option></option><!-- Required for data-placeholder attribute to work with Select2 plugin -->
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}">{{ $status->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>--}}
                <div class="block-options d-flex align-items-center flex-nowrap">
                    <div class="btn-group mr-2">
                        <button
                            type="button"
                            class="btn btn-alt-primary d-inline-flex align-items-center justify-content-center px-2 px-sm-3"
                            id="bulk-promo-mail-button"
                            data-toggle="dropdown"
                            aria-haspopup="true"
                            aria-expanded="false"
                            title="Masovni promo mail"
                        >
                            <i class="fa fa-envelope mr-sm-1"></i>
                            <span class="d-none d-sm-inline">Masovni mail</span>
                            <i class="fa fa-angle-down ml-1"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right p-3" style="min-width: 260px;">
                            <div class="form-group mb-3">
                                <label class="font-size-sm mb-1" for="bulkPromoDelay">Razmak slanja</label>
                                <select class="form-control form-control-sm" id="bulkPromoDelay">
                                    <option value="3">3 sekunde</option>
                                    <option value="5" selected>5 sekundi</option>
                                    <option value="8">8 sekundi</option>
                                    <option value="10">10 sekundi</option>
                                </select>
                            </div>
                            <div class="dropdown-divider"></div>
                            @foreach (\App\Services\UnfinishedOrderPromoService::ALLOWED_DISCOUNTS as $discount)
                                <a class="dropdown-item d-flex align-items-center justify-content-between" href="javascript:void(0)" onclick="sendBulkUnfinishedPromo({{ $discount }})">
                                    <span>Pošalji promo mail</span>
                                    <span class="badge badge-primary">-{{ $discount }}%</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <div class="d-flex align-items-center flex-nowrap mr-2">
                        <a class="btn {{ request()->boolean('gift_wrap') ? 'btn-primary' : 'btn-light' }} d-inline-flex align-items-center justify-content-center mr-2 px-2 px-sm-3"
                           title="Poklon zamatanje"
                           aria-label="Poklon zamatanje"
                           href="javascript:setPageURL('gift_wrap', '{{ request()->boolean('gift_wrap') ? '' : '1' }}')">
                            <i class="fa fa-gift mr-sm-1"></i>
                            <span class="d-none d-sm-inline">Poklon zamatanje</span>
                        </a>
                        <a class="btn {{ request()->boolean('gift_code') ? 'btn-primary' : 'btn-light' }} d-inline-flex align-items-center justify-content-center px-2 px-sm-3"
                           title="Iskorišten poklon kod"
                           aria-label="Iskorišten poklon kod"
                           href="javascript:setPageURL('gift_code', '{{ request()->boolean('gift_code') ? '' : '1' }}')">
                            <i class="fa fa-tag mr-sm-1"></i>
                            <span class="d-none d-sm-inline">Iskorišten poklon kod</span>
                        </a>
                    </div>
                    <div class="dropdown">
                        @php($orderFilterQuery = request()->except(['page', 'status', 'completed_without_promo_mail']))
                        <button type="button" class="btn btn-light d-inline-flex align-items-center justify-content-center px-2 px-sm-3" id="dropdown-ecom-filters" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Filtriraj">
                            <i class="fa fa-filter mr-sm-1"></i>
                            <span class="d-none d-sm-inline">Filtriraj</span>
                            <i class="fa fa-angle-down ml-1"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdown-ecom-filters">
                            <a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ route('orders', $orderFilterQuery) }}">
                                Sve narudžbe
                            </a>
                            <a class="dropdown-item d-flex align-items-center justify-content-between {{ request()->boolean('completed_without_promo_mail') ? 'active' : '' }}" href="{{ route('orders', array_merge($orderFilterQuery, ['completed_without_promo_mail' => 1])) }}">
                                <span class="badge badge-pill badge-warning">
                                    <i class="fa fa-envelope-open mr-1"></i>Završeno bez maila
                                </span>
                            </a>
                            @foreach ($statuses as $status)
                                <a class="dropdown-item d-flex align-items-center justify-content-between" href="{{ route('orders', array_merge($orderFilterQuery, ['status' => $status->id])) }}">
                                    <span class="badge badge-pill badge-{{ $status->color ?? 'secondary' }}">{{ $status->title }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="block-content bg-body-dark">
                <!-- Search Form -->
                <form action="{{ route('orders') }}" method="GET">
                    @if (request()->boolean('completed_without_promo_mail'))
                        <input type="hidden" name="completed_without_promo_mail" value="1">
                    @endif
                    @if (request()->filled('status') && ! request()->boolean('completed_without_promo_mail'))
                        <input type="hidden" name="status" value="{{ request()->input('status') }}">
                    @endif
                    @if (request()->boolean('gift_wrap'))
                        <input type="hidden" name="gift_wrap" value="1">
                    @endif
                    @if (request()->boolean('gift_code'))
                        <input type="hidden" name="gift_code" value="1">
                    @endif
                    <div class="form-group">
                        <div class="form-group">
                            <div class="input-group flex-nowrap">
                                <input type="text" class="form-control py-3 text-center" name="search" id="search-input" value="{{ request()->input('search') }}" placeholder="Pretraži po broju narudžbe, imenu, prezimenu ili emailu kupca...">
                                <button type="submit" class="btn btn-primary fs-base"><i class="fa fa-search"></i> </button>
                            </div>
                        </div>
                    </div>
                </form>
                <!-- END Search Form -->
            </div>
            <div class="block-content">
                <div id="bulkPromoProgress" class="alert alert-info d-none" role="alert">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span id="bulkPromoProgressText" class="font-w600">Slanje mailova...</span>
                        <span id="bulkPromoProgressCount" class="font-size-sm"></span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div id="bulkPromoProgressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <!-- All Orders Table -->
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter font-size-sm">
                        <thead>
                        <tr>
                            <th class="text-center" style="width: 30px;">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="checkAll" name="status">
                                    </div>
                                </div>
                            </th>
                            <th class="text-center" style="width: 36px;">Br.</th>
                            <th class="text-center">Datum</th>
                            <th>Status</th>
                            <th>Plaćanje</th>
                            <th>Dostava</th>
                            <th>Kupac</th>
                            <th class="text-center">Artikli</th>
                            <th class="text-right">Vrijednost</th>
                            <th class="text-center font-size-sm" style="width: 80px;">Pošalji</th>
                            <th class="text-right font-size-sm" style="width: 240px;">Detalji</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($orders->sortByDesc('id') as $order)
                            @php($sentPromoAction = $sentPromoActions->get($order->id))
                            @php($sentReminderHistory = $sentReminderHistories->get($order->id))
                            @php($hasAppliedCoupon = $appliedCouponOrderIds->contains((int) $order->id))
                            @php($canSendUnfinishedPromo = ! $sentPromoAction && ! $hasAppliedCoupon && filled($order->payment_email))
                            @php($canSendUnfinishedReminder = ! $sentReminderHistory && (int) $order->order_status_id === (int) config('settings.order.status.unfinished') && filled($order->payment_email))
                            @php($canShowUnfinishedMailButton = $canSendUnfinishedPromo || $canSendUnfinishedReminder)
                            <tr>
                                <td class="text-center">
                                    <div class="form-group">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="{{ $order->id }}" id="status[{{ $order->id }}]" name="status" data-bulk-promo-checkbox data-can-unfinished-promo="{{ $canSendUnfinishedPromo ? '1' : '0' }}">
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <a class="font-w600" href="{{ route('orders.show', ['order' => $order]) }}">
                                        <strong>{{ $order->id }}</strong>
                                    </a>
                                </td>
                                <td class="text-center">{{ \Illuminate\Support\Carbon::make($order->created_at)->format('d.m.Y') }}</td>
                                <td class="font-size-base">
                                    @php($status = $order->status)
                                    <span class="badge badge-pill badge-{{ $status->color ?? 'secondary' }}">
                                        {{ $status->title ?? ('Nepoznat status (#' . $order->order_status_id . ')') }}
                                    </span>
                                </td>
                                <td class="text-lwft">{{ $order->payment_method }}</td>
                                <td class="text-lwft">
                                    {{ $order->shipping_method }}
                                    @if ($order->shipping_tracking_status || $order->tracking_code)
                                        <div class="mt-1">
                                            @if ($order->shipping_carrier)
                                                <span class="badge badge-light">{{ $order->shipping_carrier === 'boxnow' ? 'Box Now' : strtoupper($order->shipping_carrier) }}</span>
                                            @endif
                                            @if ($order->tracking_code)
                                                <small class="text-muted">#{{ $order->tracking_code }}</small>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($order->shipping_tracking_status)
                                        <div class="text-muted small mt-1">{{ $order->shipping_tracking_status }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if (auth()->user()->can('*') && $order->user)
                                        <form action="{{ route('users.impersonate', ['user' => $order->user]) }}" method="POST" class="d-inline m-0">
                                            @csrf
                                            <button type="submit"
                                                    class="btn btn-link p-0 border-0 font-w600 text-left align-baseline"
                                                    title="Otvori front profil kupca"
                                                    onclick="return confirm('Prijaviti se kao ovaj kupac i otvoriti njegov front račun?')">
                                                {{ $order->shipping_fname }} {{ $order->shipping_lname }}
                                                <i class="fa fa-user-check ml-1 text-success"></i>
                                            </button>
                                        </form>
                                    @else
                                        <a class="font-w600" href="{{ route('orders.show', ['order' => $order]) }}">{{ $order->shipping_fname }} {{ $order->shipping_lname }}</a>
                                    @endif
                                </td>
                                <td class="text-center">{{ $order->products->count() }}</td>
                                <td class="text-right">
                                    <strong>€ {{ number_format($order->total, 2, ',', '.') }}</strong>
                                </td>
                                <td class="text-center">
                                    @if($order->printed)
                                        <i class="fa fa-fw fa-check text-success"></i>
                                    @else
                                        @if ($order->shipping_code == \App\Services\GiftVoucherService::SHIPPING_CODE)
                                            <span class="badge badge-success">E-mail</span>
                                        @elseif ($order->shipping_method == 'BoxNow')
                                            <button type="button" class="btn btn-light btn-sm" onclick="sendGLS({{ $order->id }})"><i class="fa fa-shipping-fast ml-1"></i></button>
                                        @elseif ($order->shipping_code == 'hp_paketomat')
                                            <button type="button" class="btn btn-light btn-sm" onclick="sendHPPak({{ $order->id }})"><i class="fa fa-shipping-fast ml-1"></i></button>
                                        @elseif ($order->shipping_code == 'wolt_drive')
                                            <button type="button" class="btn btn-primary btn-sm" data-wolt-btn="{{ $order->id }}" onclick="sendWolt({{ $order->id }})">
                                                <i class="fa fa-motorcycle ml-1"></i> Wolt
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-light btn-sm" onclick="sendGLSstari({{ $order->id }})"><i class="fa fa-shipping-fast ml-1"></i></button>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($sentPromoAction)
                                        <div class="d-inline-flex flex-column align-items-end mr-1">
                                            <span class="badge badge-success">Poslano -{{ (int) $sentPromoAction->discount }}%</span>
                                            <span class="text-muted small mt-1">{{ \Illuminate\Support\Carbon::make(data_get($sentPromoAction->data, 'sent_at', $sentPromoAction->created_at))->format('d.m.Y H:i') }}</span>
                                        </div>
                                    @endif
                                    @if ($sentReminderHistory)
                                        <div class="d-inline-flex flex-column align-items-end mr-1">
                                            <span class="badge badge-info">Podsjetnik poslan</span>
                                            <span class="text-muted small mt-1">{{ \Illuminate\Support\Carbon::make($sentReminderHistory->created_at)->format('d.m.Y H:i') }}</span>
                                        </div>
                                    @endif
                                    @if ($canShowUnfinishedMailButton)
                                        <div class="btn-group mr-1">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-alt-primary dropdown-toggle"
                                                data-toggle="dropdown"
                                                aria-haspopup="true"
                                                aria-expanded="false"
                                                @if ($canSendUnfinishedPromo)
                                                    data-unfinished-promo-btn="{{ $order->id }}"
                                                @endif
                                                @if ($canSendUnfinishedReminder)
                                                    data-unfinished-reminder-btn="{{ $order->id }}"
                                                @endif
                                                title="Pošalji mail"
                                            >
                                                <i class="fa fa-fw fa-envelope mr-1"></i><span class="d-none d-xl-inline">Mail</span>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                @if ($canSendUnfinishedReminder)
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="sendUnfinishedReminder({{ $order->id }})">Pošalji podsjetnik</a>
                                                @endif
                                                @if ($canSendUnfinishedReminder && $canSendUnfinishedPromo)
                                                    <div class="dropdown-divider"></div>
                                                @endif
                                                @if ($canSendUnfinishedPromo)
                                                    @foreach (\App\Services\UnfinishedOrderPromoService::ALLOWED_DISCOUNTS as $discount)
                                                        <a class="dropdown-item" href="javascript:void(0)" onclick="sendUnfinishedPromo({{ $order->id }}, {{ $discount }})">Pošalji -{{ $discount }}%</a>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                    <a class="btn btn-sm btn-alt-secondary mr-1" href="{{ route('orders.show', ['order' => $order]) }}">
                                        <i class="fa fa-fw fa-eye"></i>
                                    </a>
                                    @if ($order->shipping_carrier || $order->tracking_code || $order->shipping_method == 'BoxNow')
                                        <button type="button" class="btn btn-sm btn-alt-primary mr-1" data-tracking-btn="{{ $order->id }}" onclick="refreshTracking({{ $order->id }})" title="Osvježi tracking">
                                            <i class="fa fa-fw fa-sync-alt"></i>
                                        </button>
                                    @endif
                                    <a class="btn btn-sm btn-alt-info" href="{{ route('orders.edit', ['order' => $order]) }}">
                                        <i class="fa fa-fw fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center font-size-sm" colspan="11">
                                    <label>Nema narudžbi...</label>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                {{ $orders->links() }}
            </div>
        </div>
        <!-- END All Orders -->
    </div>

@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        let bulkPromoSending = false;

        $(() => {
            $('#status-select').select2({
                placeholder: 'Promjenite status'
            });

            $('#status-select').on('change', (e) => {
                let selected = e.currentTarget.selectedOptions[0].value;
                let orders = Array.from(document.querySelectorAll('input[name=status]:checked'))
                    .map((checkbox) => checkbox.value);

                if (! orders.length) {
                    return;
                }

                axios.post('{{ route('api.order.status.change') }}', {
                    selected: selected,
                    orders: orders
                })
                    .then((r) => {
                        location.reload();
                    })
                    .catch((e) => {
                        return errorToast.fire();
                    })
            });
        });

        /**
         *
         * @param order_id
         */
        function sendGLS(order_id) {
            axios.post("{{ route('api.order.send.gls') }}", {order_id: order_id})
                .then(response => {
                    if (response.data.message) {
                        successToast.fire({
                            timer: 1500,
                            text: response.data.message,
                        }).then(() => {
                            location.reload();
                        })

                    } else {
                        return errorToast.fire(response.data.error);
                    }
                });
        }

        /**
         *
         * @param order_id
         */
        function sendHPPak(order_id) {
            axios.post("{{ route('api.order.send.hp_pak') }}", {order_id: order_id})
                .then(response => {
                    if (response.data.message) {
                        successToast.fire({
                            timer: 1500,
                            text: response.data.message,
                        }).then(() => {
                            location.reload();
                        })

                    } else {
                        return errorToast.fire(response.data.error);
                    }
                });
        }

        /**
         *
         * @param order_id
         */
        function sendWolt(order_id) {
            setWoltBtnLoading(order_id, true);
            axios.post("{{ route('api.order.send.wolt') }}", { order_id })
                .then(response => {
                    if (response.data.message) {
                        successToast.fire({ timer: 1500, text: response.data.message })
                            .then(() => location.reload());
                    } else {
                        errorToast.fire(response.data.error || 'Greška pri slanju na Wolt Drive.');
                    }
                })
                .catch(() => errorToast.fire("Greška prilikom slanja na Wolt Drive."))
                .finally(() => setWoltBtnLoading(order_id, false));
        }

        function setWoltBtnLoading(orderId, isLoading) {
            const btn = document.querySelector(`[data-wolt-btn="${orderId}"]`);
            if (!btn) return;
            btn.disabled = isLoading;
            btn.innerHTML = isLoading
                ? '<i class="fa fa-spinner fa-spin"></i> Slanje...'
                : '<i class="fa fa-motorcycle ml-1"></i> Wolt';
        }

        function refreshTracking(order_id) {
            setTrackingBtnLoading(order_id, true);
            axios.post("{{ route('api.order.tracking.refresh') }}", { order_id })
                .then(response => {
                    if (response.data.message) {
                        successToast.fire({
                            timer: 1500,
                            text: response.data.message,
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        errorToast.fire(response.data.error || 'Tracking nije osvježen.');
                    }
                })
                .catch(error => {
                    errorToast.fire(error?.response?.data?.error || 'Tracking nije osvježen.');
                })
                .finally(() => setTrackingBtnLoading(order_id, false));
        }

        function setTrackingBtnLoading(orderId, isLoading) {
            const btn = document.querySelector(`[data-tracking-btn="${orderId}"]`);
            if (!btn) return;

            btn.disabled = isLoading;
            btn.innerHTML = isLoading
                ? '<i class="fa fa-spinner fa-spin"></i>'
                : '<i class="fa fa-fw fa-sync-alt"></i>';
        }

        function sendUnfinishedPromo(order_id, discount) {
            if (bulkPromoSending) {
                return;
            }

            setUnfinishedPromoBtnLoading(order_id, true);
            sendUnfinishedPromoRequest(order_id, discount)
                .then(response => {
                    if (response.data.message) {
                        successToast.fire({
                            timer: 1500,
                            text: response.data.message,
                        }).then(() => {
                            location.reload();
                        });

                    } else {
                        errorToast.fire(response.data.error || 'Greška prilikom slanja promo maila.');
                    }
                })
                .catch(error => {
                    errorToast.fire(error?.response?.data?.error || 'Greška prilikom slanja promo maila.');
                })
                .finally(() => setUnfinishedPromoBtnLoading(order_id, false));
        }

        function sendUnfinishedPromoRequest(order_id, discount) {
            return axios.post("{{ route('api.order.send.unfinished-promo') }}", { order_id, discount });
        }

        function sendUnfinishedReminder(order_id) {
            if (bulkPromoSending) {
                return;
            }

            setUnfinishedReminderBtnLoading(order_id, true);
            axios.post("{{ route('api.order.send.unfinished-reminder') }}", { order_id })
                .then(response => {
                    if (response.data.message) {
                        successToast.fire({
                            timer: 1500,
                            text: response.data.message,
                        }).then(() => {
                            location.reload();
                        });

                    } else {
                        errorToast.fire(response.data.error || 'Greška prilikom slanja podsjetnika.');
                    }
                })
                .catch(error => {
                    errorToast.fire(error?.response?.data?.error || 'Greška prilikom slanja podsjetnika.');
                })
                .finally(() => setUnfinishedReminderBtnLoading(order_id, false));
        }

        async function sendBulkUnfinishedPromo(discount) {
            if (bulkPromoSending) {
                return;
            }

            const selected = getSelectedBulkPromoCheckboxes();
            const eligible = selected.filter((checkbox) => checkbox.dataset.canUnfinishedPromo === '1');
            const orderIds = eligible.map((checkbox) => Number(checkbox.value)).filter(Boolean);
            const skippedCount = selected.length - eligible.length;

            if (!selected.length) {
                errorToast.fire('Odaberite barem jednu narudžbu.');
                return;
            }

            if (!orderIds.length) {
                errorToast.fire('Nijedna odabrana narudžba nema dostupan promo mail.');
                return;
            }

            const delaySeconds = getBulkPromoDelaySeconds();
            const confirmation = await confirmPopUp.fire({
                title: 'Poslati promo mailove?',
                text: `Slanje: ${orderIds.length}. Razmak: ${delaySeconds} sekundi. Popust: -${discount}%.`,
                type: 'question',
                showCancelButton: true,
                confirmButtonText: 'Pošalji',
                cancelButtonText: 'Odustani'
            });

            if (!confirmation.value) {
                return;
            }

            bulkPromoSending = true;
            setBulkPromoControls(false);
            setBulkPromoProgress('Priprema slanja...', 0, orderIds.length);

            let sentCount = 0;
            const failures = [];

            for (let index = 0; index < orderIds.length; index++) {
                const orderId = orderIds[index];

                setBulkPromoProgress(`Slanje narudžbe #${orderId}`, index, orderIds.length);
                setUnfinishedPromoBtnLoading(orderId, true);

                try {
                    const response = await sendUnfinishedPromoRequest(orderId, discount);

                    if (response.data.message) {
                        sentCount++;
                        markBulkPromoOrderSent(orderId);
                    } else {
                        failures.push(`#${orderId}: ${response.data.error || 'Greška prilikom slanja promo maila.'}`);
                    }
                } catch (error) {
                    failures.push(`#${orderId}: ${error?.response?.data?.error || 'Greška prilikom slanja promo maila.'}`);
                } finally {
                    setUnfinishedPromoBtnLoading(orderId, false);
                }

                setBulkPromoProgress(`Poslano ${sentCount} od ${orderIds.length}`, index + 1, orderIds.length);

                if (index < orderIds.length - 1) {
                    await wait(delaySeconds * 1000);
                }
            }

            bulkPromoSending = false;
            setBulkPromoControls(true);

            const skippedText = skippedCount ? `<br>Preskočeno: ${skippedCount}` : '';
            const failuresText = failures.length
                ? `<br><br><strong>Greške:</strong><br>${failures.slice(0, 8).map(escapeHtml).join('<br>')}`
                : '';

            Swal.fire({
                type: failures.length ? 'warning' : 'success',
                title: 'Slanje završeno',
                html: `Poslano: ${sentCount}${skippedText}${failuresText}`,
                confirmButtonText: 'U redu',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            }).then(() => location.reload());
        }

        function setUnfinishedPromoBtnLoading(orderId, isLoading) {
            const btn = document.querySelector(`[data-unfinished-promo-btn="${orderId}"]`);
            if (!btn) return;

            if (!isLoading && btn.dataset.bulkPromoSent === '1') {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-fw fa-check mr-1"></i><span class="d-none d-xl-inline">Poslano</span>';
                return;
            }

            btn.disabled = isLoading || bulkPromoSending;
            btn.innerHTML = isLoading
                ? '<i class="fa fa-spinner fa-spin mr-1"></i><span class="d-none d-xl-inline">Slanje...</span>'
                : '<i class="fa fa-fw fa-envelope mr-1"></i><span class="d-none d-xl-inline">Mail</span>';
        }

        function setUnfinishedReminderBtnLoading(orderId, isLoading) {
            const btn = document.querySelector(`[data-unfinished-reminder-btn="${orderId}"]`);
            if (!btn) return;

            btn.disabled = isLoading || bulkPromoSending;
            btn.innerHTML = isLoading
                ? '<i class="fa fa-spinner fa-spin mr-1"></i><span class="d-none d-xl-inline">Slanje...</span>'
                : '<i class="fa fa-fw fa-envelope mr-1"></i><span class="d-none d-xl-inline">Mail</span>';
        }

        function getSelectedBulkPromoCheckboxes() {
            return Array.from(document.querySelectorAll('input[data-bulk-promo-checkbox]:checked'));
        }

        function getBulkPromoDelaySeconds() {
            const delayInput = document.getElementById('bulkPromoDelay');
            const delaySeconds = Number(delayInput ? delayInput.value : 5);

            return Number.isFinite(delaySeconds) && delaySeconds > 0 ? delaySeconds : 5;
        }

        function setBulkPromoControls(isEnabled) {
            const controls = [
                document.getElementById('bulk-promo-mail-button'),
                document.getElementById('bulkPromoDelay'),
                document.getElementById('checkAll'),
                ...document.querySelectorAll('input[data-bulk-promo-checkbox]'),
                ...document.querySelectorAll('[data-unfinished-promo-btn]'),
                ...document.querySelectorAll('[data-unfinished-reminder-btn]')
            ].filter(Boolean);

            controls.forEach((control) => {
                if (control.dataset.bulkPromoSent === '1') {
                    control.disabled = true;
                    return;
                }

                control.disabled = !isEnabled;
            });
        }

        function setBulkPromoProgress(text, completed, total) {
            const progress = document.getElementById('bulkPromoProgress');
            const progressText = document.getElementById('bulkPromoProgressText');
            const progressCount = document.getElementById('bulkPromoProgressCount');
            const progressBar = document.getElementById('bulkPromoProgressBar');

            if (!progress || !progressText || !progressCount || !progressBar) {
                return;
            }

            const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;

            progress.classList.remove('d-none');
            progressText.textContent = text;
            progressCount.textContent = total > 0 ? `${completed}/${total}` : '';
            progressBar.style.width = `${percentage}%`;
            progressBar.setAttribute('aria-valuenow', percentage);
        }

        function markBulkPromoOrderSent(orderId) {
            const checkbox = document.querySelector(`input[data-bulk-promo-checkbox][value="${orderId}"]`);

            if (!checkbox) {
                return;
            }

            checkbox.dataset.canUnfinishedPromo = '0';
            checkbox.checked = false;

            const btn = document.querySelector(`[data-unfinished-promo-btn="${orderId}"]`);

            if (btn) {
                btn.dataset.bulkPromoSent = '1';
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-fw fa-check mr-1"></i><span class="d-none d-xl-inline">Poslano</span>';
            }
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

        /**
         *
         * @param order_id
         */
        function sendGLSstari(order_id) {
            axios.post("{{ route('api.order.send.glsstari') }}", {order_id: order_id})
                .then(response => {
                    if (response.data.message) {
                        successToast.fire({
                            timer: 1500,
                            text: response.data.message,
                        }).then(() => {
                            location.reload();
                        })

                    } else {
                        return errorToast.fire(response.data.error);
                    }
                });
        }
    </script>
    <script>
        $("#checkAll").click(function () {
            $('input[data-bulk-promo-checkbox]').prop('checked', this.checked);
        });
    </script>

@endpush
