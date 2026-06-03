@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
    <!-- Page JS Plugins CSS -->
    <link rel="stylesheet" href="{{ asset('js/plugins/magnific-popup/magnific-popup.css') }}">
@endpush

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Narudžba pregled <small class="font-weight-light">#_</small><strong>{{ $order->id }}</strong></h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('orders') }}">Sve narudžbe</a></li>

                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="content">
    @include('back.layouts.partials.session')
        <!-- Products -->
        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Artikli</h3>
            </div>
            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter font-size-sm">
                        <thead>
                        <tr>
                            <th class="text-center" style="width: 100px;">Slika</th>
                            <th>Naziv</th>
                            <th>Polica</th>
                            <th class="text-center">Kol</th>
                            <th class="text-right" style="width: 10%;">Cijena</th>
                            <th class="text-right" style="width: 10%;">Rabat %</th>
                            <th class="text-right" style="width: 10%;">Ukupno</th>
                        </tr>
                        </thead>
                        <tbody class="js-gallery">
                        @foreach ($order->products as $product)
                            <tr>
                                <td class="text-center"> <a class="img-link img-link-zoom-in img-lightbox" href="{{ ($product->product && $product->product->image) ? asset($product->product->image) : asset('media/avatars/avatar0.jpg') }}">
                                        <img src="{{ ($product->product && $product->product->image) ? asset($product->product->image) : asset('media/avatars/avatar0.jpg') }}" height="80px"/>
                                    </a>
                                </td>
                                <td><strong>{{ $product->name }} -  {{ $product->product ? $product->product->sku : '' }}</strong></td>
                                <td>{{ $product->product ? $product->product->polica : '' }}</td>
                                <td class="text-center"><strong>{{ $product->quantity }}</strong></td>
                                <td class="text-right">{{ number_format($product->org_price, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format($product->discount) }}</td>
                                <td class="text-right">{{ number_format($product->total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach

                        @foreach ($order->totals as $total)
                            <tr>
                                <td colspan="6" class="text-right"><strong>{{ $total->title }}:</strong></td>
                                <td class="text-right">{{ number_format($total->value, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- END Products -->

        <!-- Customer -->
        <div class="row">
            <div class="col-sm-6">
                <!-- Billing Address -->
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Adresa dostave</h3>
                    </div>
                    <div class="block-content">
                        <div class="font-size-h4 mb-1">{{ $order->shipping_fname }} {{ $order->shipping_lname }}</div>
                        <address class="font-size-sm">
                            {{ $order->shipping_address }}<br>
                            {{ $order->shipping_zip }} {{ $order->shipping_city }}<br>
                            {{ $order->shipping_state }}<br><br> {{ $order->company }}<br>{{ $order->oib }}<br><br>
                            <i class="fa fa-phone"></i> {{ $order->shipping_phone }}<br>
                            <i class="fa fa-envelope"></i> <a href="javascript:void(0)">{{ $order->shipping_email }}</a>
                        </address>
                    </div>
                </div>
                <!-- END Billing Address -->
            </div>
            <div class="col-sm-6">
                <!-- Shipping Address -->
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Napomene</h3>
                    </div>
                    <div class="block-content">
                        <p>{{ $order->comment }}</p>
                    </div>
                </div>
                <!-- END Shipping Address -->
            </div>
        </div>

        @php
            $trackingCarrier = $order->shipping_carrier;
            if (! $trackingCarrier && \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower($order->shipping_method), ['boxnow', 'box now'])) {
                $trackingCarrier = 'boxnow';
            }
            if (! $trackingCarrier && ($order->tracking_code || \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower($order->shipping_method . ' ' . $order->shipping_code), 'gls'))) {
                $trackingCarrier = 'gls';
            }
        @endphp

        @if($trackingCarrier || $order->tracking_code || $order->shipping_tracking_status)
            <div class="block block-rounded">
                <div class="block-header block-header-default">
                    <h3 class="block-title">Praćenje dostave</h3>
                    <div class="block-options">
                        <button type="button" class="btn btn-sm btn-alt-primary" data-tracking-btn="{{ $order->id }}" onclick="refreshTracking({{ $order->id }})">
                            Osvježi <i class="fa fa-sync-alt ml-1"></i>
                        </button>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="font-size-sm text-muted">Dostavna služba</div>
                            <div class="font-w600">{{ $trackingCarrier === 'boxnow' ? 'Box Now' : strtoupper($trackingCarrier ?: 'Dostava') }}</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="font-size-sm text-muted">Tracking broj</div>
                            <div class="font-w600">
                                @if($order->shipping_tracking_url && $order->tracking_code)
                                    <a href="{{ $order->shipping_tracking_url }}" target="_blank" rel="noopener">{{ $order->tracking_code }}</a>
                                @else
                                    {{ $order->tracking_code ?: $order->shipping_parcel_id ?: 'Nije upisan' }}
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="font-size-sm text-muted">Zadnji status</div>
                            <div class="font-w600">{{ $order->shipping_tracking_status ?: 'Još nije osvježeno' }}</div>
                            @if($order->shipping_tracking_status_code)
                                <div class="font-size-sm text-muted">Kod: {{ $order->shipping_tracking_status_code }}</div>
                            @endif
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="font-size-sm text-muted">Osvježeno</div>
                            <div class="font-w600">
                                {{ $order->shipping_tracking_updated_at ? \Illuminate\Support\Carbon::make($order->shipping_tracking_updated_at)->format('d.m.Y H:i') : 'Nikad' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($order->giftVouchers->count())
            <div class="block block-rounded">
                <div class="block-header block-header-default">
                    <h3 class="block-title">Poklon bonovi</h3>
                </div>
                <div class="block-content">
                    <div class="table-responsive">
                        <table class="table table-borderless table-striped table-vcenter font-size-sm">
                            <thead>
                            <tr>
                                <th>Iznos</th>
                                <th>Primatelj</th>
                                <th>Pošiljatelj</th>
                                <th>Kod</th>
                                <th>Status</th>
                                <th>Poruka</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($order->giftVouchers as $giftVoucher)
                                <tr>
                                    <td><strong>€ {{ number_format($giftVoucher->amount, 2, ',', '.') }}</strong></td>
                                    <td>{{ $giftVoucher->recipient_name ?: '---' }}<br><small>{{ $giftVoucher->recipient_email }}</small></td>
                                    <td>{{ $giftVoucher->sender_name ?: ($giftVoucher->buyer_name ?: '---') }}</td>
                                    <td><code>{{ $giftVoucher->code ?: 'Još nije generiran' }}</code></td>
                                    <td><span class="badge badge-{{ $giftVoucher->status_color }}">{{ $giftVoucher->display_status }}</span></td>
                                    <td>{{ $giftVoucher->message ?: '---' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
        <!-- END Customer -->

        <!-- Log Messages -->
        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Povijest narudžbe</h3>
                <div class="block-options">
                    <div class="dropdown">
                        <button type="button" class="btn btn-alt-secondary d-none d-xl-block" id="btn-add-comment">
                            Dodaj komentar
                        </button>
                        <button type="button" class="btn btn-light" id="dropdown-ecom-filters" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Promjeni status
                            <i class="fa fa-angle-down ml-1"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdown-ecom-filters">
                            @foreach ($statuses as $status)
                                <a class="dropdown-item d-flex align-items-center justify-content-between" href="javascript:setStatus({{ $status->id }});">
                                    <span class="badge badge-pill badge-{{ $status->color }}">{{ $status->title }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="block-content">
                <table class="table table-borderless table-striped table-vcenter font-size-sm">
                    <tbody>
                    @foreach ($order->history as $record)
                        <tr>
                            <td class="font-size-base d-none d-xl-block">
                                @if ($record->status)
                                    <span class="badge badge-pill badge-{{ $record->status->color }}">{{ $record->status->title }}</span>
                                @else
                                    <small>Komentar</small>
                                @endif
                            </td>
                            <td>
                                <span class="font-w600">{{ \Illuminate\Support\Carbon::make($record->created_at)->locale('hr_HR')->diffForHumans() }}</span> /
                                <span class="font-weight-light">{{ \Illuminate\Support\Carbon::make($record->created_at)->format('d.m.Y - h:i') }}</span>
                            </td>
                            <td>
                                <a href="javascript:void(0)">{{ $record->user ? $record->user->name : $record->order->shipping_fname . ' ' . $record->order->shipping_lname }}</a>
                            </td>
                            <td>{{ $record->comment }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <!-- END Log Messages -->
    </div>
    <!-- END Page Content -->

@endsection

@push('modals')
    <div class="modal fade" id="comment-modal" tabindex="-1" role="dialog" aria-labelledby="comment--modal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-popout" role="document">
            <div class="modal-content rounded">
                <div class="block block-themed block-transparent mb-0">
                    <div class="block-header bg-primary">
                        <h3 class="block-title">Dodaj komentar</h3>
                        <div class="block-options">
                            <a class="text-muted font-size-h3" href="#" data-dismiss="modal" aria-label="Close">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>
                    <div class="block-content">
                        <div class="row justify-content-center mb-3">
                            <div class="col-md-10">
                                <div class="form-group mb-4">
                                    <label for="status-select">Promjeni status</label>
                                    <select class="js-select2 form-control" id="status-select" name="status" style="width: 100%;" data-placeholder="Promjeni status narudžbe">
                                        <option value="0">Bez Promjene statusa...</option>
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->id }}">{{ $status->title }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="comment-input">Komentar</label>
                                    <textarea class="form-control" name="comment" id="comment-input" rows="7"></textarea>
                                </div>

                                <input type="hidden" name="order_id" value="{{ $order->id }}">
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                            Odustani <i class="fa fa-times ml-2"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" onclick="event.preventDefault(); changeStatus();">
                            Snimi <i class="fa fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('js_after')

    <!-- Page JS Plugins -->
    <script src="{{ asset('js/plugins/magnific-popup/jquery.magnific-popup.min.js') }}"></script>

    <!-- Page JS Helpers (Magnific Popup Plugin) -->
    <script>jQuery(function(){Dashmix.helpers('magnific-popup');});</script>

    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(() => {
            $('#status-select').select2({});

            $('#btn-add-comment').on('click', () => {
                $('#comment-modal').modal('show');
                $('#status-select').val(0);
                $('#status-select').trigger('change');
            });
        });

        /**
         *
         * @param status
         */
        function setStatus(status) {
            $('#comment-modal').modal('show');
            $('#status-select').val(status);
            $('#status-select').trigger('change');
        }

        /**
         *
         */
        function changeStatus() {
            let item = {
                order_id: {{ $order->id }},
                comment: $('#comment-input').val(),
                status: $('#status-select').val()
            };

            axios.post("{{ route('api.order.status.change') }}", item)
            .then(response => {
                console.log(response.data)
                if (response.data.message) {
                    $('#comment-modal').modal('hide');

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
                ? 'Osvježavam <i class="fa fa-spinner fa-spin ml-1"></i>'
                : 'Osvježi <i class="fa fa-sync-alt ml-1"></i>';
        }
    </script>

@endpush
