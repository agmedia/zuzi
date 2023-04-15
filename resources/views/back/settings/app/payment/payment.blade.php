@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Načini plaćanja</h1>
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <div class="block">
            <div class="block-header block-header-default">
                <h3 class="block-title">Lista</h3>
            </div>
            <div class="block-content">
                <table class="table table-striped table-borderless table-vcenter">
                    <thead class="thead-light">
                    <tr>
                        <th>Naziv</th>
                        <th style="width: 10%;">Code</th>
                        <th class="text-center" style="width: 15%;">Poredak</th>
                        <th class="text-center" style="width: 15%;">Status</th>
                        <th style="width: 10%;" class="text-right">Uredi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ $payment->title }}</td>
                            <td class="small">{{ $payment->code }}</td>
                            <td class="text-center">{{ $payment->sort_order }}</td>
                            <td class="text-center">
                                @include('back.layouts.partials.status', ['status' => $payment->status])
                            </td>
                            <td class="text-right font-size-sm">
                                <button type="button" class="btn btn-sm btn-alt-secondary" onclick="event.preventDefault(); edit({{ json_encode($payment) }}, '{{ $payment->code }}');">
                                    <i class="fa fa-fw fa-pencil-alt"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr class="text-center">
                            <td colspan="4">Nema načina plaćanja...</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <!-- Pop Out Block Modal -->
    @foreach($payments as $payment)
        @include('back.settings.app.payment.modals.' . $payment->code)
    @endforeach
@endpush

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>

    <script>
        /**
         *
         * @param item
         * @param type
         */
        function edit(item, type) {
            $('#payment-modal-' + type).modal('show');
            // Call to individual edit function.
            // As. edit_flat (item) {}
            window["edit_" + type](item);
        }
    </script>

    @stack('payment-modal-js')
@endpush
