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
                <div class="block-options d-flex align-items-center">
                    <a class="btn {{ request()->boolean('gift_wrap') ? 'btn-primary' : 'btn-light' }} mr-2"
                       href="javascript:setPageURL('gift_wrap', '{{ request()->boolean('gift_wrap') ? '' : '1' }}')">
                        <i class="fa fa-gift mr-1"></i>
                        Poklon zamatanje
                    </a>
                    <div class="dropdown">
                        <button type="button" class="btn btn-light" id="dropdown-ecom-filters" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Filtriraj
                            <i class="fa fa-angle-down ml-1"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdown-ecom-filters">
                            <a class="dropdown-item d-flex align-items-center justify-content-between" href="javascript:setPageURL('status', '')">
                                Sve narudžbe
                            </a>
                            @foreach ($statuses as $status)
                                <a class="dropdown-item d-flex align-items-center justify-content-between" href="javascript:setPageURL('status', {{ $status->id }})">
                                    <span class="badge badge-pill badge-{{ $status->color }}">{{ $status->title }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="block-content bg-body-dark">
                <!-- Search Form -->
                <form action="{{ route('orders') }}" method="GET">
                    @if (request()->filled('status'))
                        <input type="hidden" name="status" value="{{ request()->input('status') }}">
                    @endif
                    @if (request()->boolean('gift_wrap'))
                        <input type="hidden" name="gift_wrap" value="1">
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
                            <tr>
                                <td class="text-center">
                                    <div class="form-group">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="{{ $order->id }}" id="status[{{ $order->id }}]" name="status">
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
                                <td class="text-lwft">{{ $order->shipping_method }}</td>
                                <td>
                                    <a class="font-w600" href="{{ route('orders.show', ['order' => $order]) }}">{{ $order->shipping_fname }} {{ $order->shipping_lname }}</a>
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
                                    @php($sentPromoAction = $sentPromoActions->get($order->id))
                                    @php($canSendUnfinishedPromo = ! $sentPromoAction && filled($order->payment_email))

                                    @if ($sentPromoAction)
                                        <div class="d-inline-flex flex-column align-items-end mr-1">
                                            <span class="badge badge-success">Poslano -{{ (int) $sentPromoAction->discount }}%</span>
                                            <span class="text-muted small mt-1">{{ \Illuminate\Support\Carbon::make(data_get($sentPromoAction->data, 'sent_at', $sentPromoAction->created_at))->format('d.m.Y H:i') }}</span>
                                        </div>
                                    @elseif ($canSendUnfinishedPromo)
                                        <div class="btn-group mr-1">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-alt-primary dropdown-toggle"
                                                data-toggle="dropdown"
                                                aria-haspopup="true"
                                                aria-expanded="false"
                                                data-unfinished-promo-btn="{{ $order->id }}"
                                                title="Pošalji promo mail"
                                            >
                                                <i class="fa fa-fw fa-envelope mr-1"></i><span class="d-none d-xl-inline">Mail</span>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="sendUnfinishedPromo({{ $order->id }}, 10)">Pošalji -10%</a>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="sendUnfinishedPromo({{ $order->id }}, 15)">Pošalji -15%</a>
                                                <a class="dropdown-item" href="javascript:void(0)" onclick="sendUnfinishedPromo({{ $order->id }}, 20)">Pošalji -20%</a>
                                            </div>
                                        </div>
                                    @endif
                                    <a class="btn btn-sm btn-alt-secondary mr-1" href="{{ route('orders.show', ['order' => $order]) }}">
                                        <i class="fa fa-fw fa-eye"></i>
                                    </a>
                                    <a class="btn btn-sm btn-alt-info" href="{{ route('orders.edit', ['order' => $order]) }}">
                                        <i class="fa fa-fw fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center font-size-sm" colspan="8">
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

        function sendUnfinishedPromo(order_id, discount) {
            setUnfinishedPromoBtnLoading(order_id, true);
            axios.post("{{ route('api.order.send.unfinished-promo') }}", { order_id, discount })
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

        function setUnfinishedPromoBtnLoading(orderId, isLoading) {
            const btn = document.querySelector(`[data-unfinished-promo-btn="${orderId}"]`);
            if (!btn) return;

            btn.disabled = isLoading;
            btn.innerHTML = isLoading
                ? '<i class="fa fa-spinner fa-spin mr-1"></i><span class="d-none d-xl-inline">Slanje...</span>'
                : '<i class="fa fa-fw fa-envelope mr-1"></i><span class="d-none d-xl-inline">Mail</span>';
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
            $('input:checkbox').not(this).prop('checked', this.checked);
        });
    </script>

@endpush
