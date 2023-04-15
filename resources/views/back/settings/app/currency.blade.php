@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Valute</h1>
                <button class="btn btn-hero-secondary my-2 mr-2" onclick="event.preventDefault(); openMainModal();">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> Odaberi glavnu valutu</span>
                </button>
                <button class="btn btn-hero-success my-2" onclick="event.preventDefault(); openModal();">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> Dodaj novu</span>
                </button>
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
                        <th style="width: 5%;">Br.</th>
                        <th style="width: 60%;">Naziv Valute</th>
                        <th class="text-center">Kod</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Uredi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->title }}
                                @if (isset($item->main) && $item->main)
                                    <strong><small>&nbsp;(Glavna)</small></strong>
                                @endif
                            </td>
                            <td class="text-center">{{ $item->code }}</td>
                            <td class="text-center">@include('back.layouts.partials.status', ['status' => $item->status])</td>
                            <td class="text-right font-size-sm">
                                <button class="btn btn-sm btn-alt-secondary" onclick="event.preventDefault(); openModal({{ json_encode($item) }});">
                                    <i class="fa fa-fw fa-pencil-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-alt-danger" onclick="event.preventDefault(); deleteCurrency({{ $item->id }});">
                                    <i class="fa fa-fw fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr class="text-center">
                            <td colspan="5">Nema upisanih valuta...</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="currency-modal" tabindex="-1" role="dialog" aria-labelledby="currency-modal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-popout" role="document">
            <div class="modal-content rounded">
                <div class="block block-themed block-transparent mb-0">
                    <div class="block-header bg-primary">
                        <h3 class="block-title">Valuta Edit</h3>
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
                                    <label for="currency-title">Naslov</label>
                                    <input type="text" class="form-control" id="currency-title" name="title">
                                </div>

                                <div class="form-group mb-4">
                                    <label for="currency-code">Kod</label>
                                    <input type="text" class="form-control" id="currency-code" name="code">
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="currency-symbol-left">Simbol lijevo</label>
                                            <input type="text" class="form-control" id="currency-symbol-left" name="symbol_left">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="currency-symbol-right">Simbol desno</label>
                                            <input type="text" class="form-control" id="currency-symbol-right" name="symbol_right">
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="currency-value">Vrijednost</label>
                                            <input type="text" class="form-control" id="currency-value" name="value">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="currency-decimal-places">Decimalna mjesta</label>
                                            <input type="text" class="form-control" id="currency-decimal-places" name="decimal_places">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="css-control css-control-sm css-control-success css-switch res">
                                        <input type="checkbox" class="css-control-input" id="currency-status" name="status">
                                        <span class="css-control-indicator"></span> Status valute
                                    </label>
                                </div>

                                <div class="form-group">
                                    <label class="css-control css-control-sm css-control-success css-switch res">
                                        <input type="checkbox" class="css-control-input" id="currency-main" name="main">
                                        <span class="css-control-indicator"></span> Glavna valuta
                                    </label>
                                </div>

                                <input type="hidden" id="currency-id" name="id" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                            Odustani <i class="fa fa-times ml-2"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" onclick="event.preventDefault(); createCurrency();">
                            Snimi <i class="fa fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="main-currency-modal" tabindex="-1" role="dialog" aria-labelledby="main-currency-modal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-popout" role="document">
            <div class="modal-content rounded">
                <div class="block block-themed block-transparent mb-0">
                    <div class="block-header bg-primary">
                        <h3 class="block-title">Odaberite glavnu valutu</h3>
                        <div class="block-options">
                            <a class="text-muted font-size-h3" href="#" data-dismiss="modal" aria-label="Close">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>
                    <div class="block-content">
                        <div class="row justify-content-center mb-3">
                            <div class="col-md-10 mt-3">
                                <div class="form-group">
                                    <select class="js-select2 form-control" id="currency-main-select" name="currency_main_select" style="width: 100%;" data-placeholder="Odaberite glavnu valutu">
                                        <option></option>
                                        @foreach ($items as $item)
                                            <option value="{{ $item->id }}" {{ ((isset($main)) and ($main->id == $item->id)) ? 'selected' : '' }}>{{ $item->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
<!--                                <div class="form-group">
                                    <div class="custom-control custom-switch custom-control-info">
                                        <input type="checkbox" class="custom-control-input" id="change-prices-switch" name="change_prices">
                                        <label class="custom-control-label" for="change-prices-switch">Preračunaj Cijene</label>
                                    </div>
                                </div>-->
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                            Odustani <i class="fa fa-times ml-2"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" onclick="event.preventDefault(); storeMainCurrency();">
                            Snimi <i class="fa fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="delete-currency-modal" tabindex="-1" role="dialog" aria-labelledby="currency-modal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-popout" role="document">
            <div class="modal-content rounded">
                <div class="block block-themed block-transparent mb-0">
                    <div class="block-header bg-primary">
                        <h3 class="block-title">Obriši valutu</h3>
                        <div class="block-options">
                            <a class="text-muted font-size-h3" href="#" data-dismiss="modal" aria-label="Close">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>
                    <div class="block-content">
                        <div class="row justify-content-center mb-3">
                            <div class="col-md-10">
                                <h4>Jeste li sigurni da želite obrisati valutu?</h4>
                                <input type="hidden" id="delete-currency-id" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                            Odustani <i class="fa fa-times ml-2"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-danger" onclick="event.preventDefault(); confirmDelete();">
                            Obriši <i class="fa fa-trash-alt ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(() => {
            $('#currency-main-select').select2({
                minimumResultsForSearch: Infinity
            });
        });
        /**
         *
         * @param item
         * @param type
         */
        function openModal(item = {}) {
            $('#currency-modal').modal('show');
            editCurrency(item);
        }

        /**
         *
         * @param item
         * @param type
         */
        function openMainModal(item = {}) {
            $('#main-currency-modal').modal('show');
        }

        /**
         *
         */
        function createCurrency() {
            let item = {
                id: $('#currency-id').val(),
                title: $('#currency-title').val(),
                code: $('#currency-code').val(),
                symbol_left: $('#currency-symbol-left').val(),
                symbol_right: $('#currency-symbol-right').val(),
                value: $('#currency-value').val(),
                decimal_places: $('#currency-decimal-places').val(),
                status: $('#currency-status')[0].checked,
                main: $('#currency-main')[0].checked,
            };

            axios.post("{{ route('api.currencies.store') }}", { data: item })
            .then(response => {
                if (response.data.success) {
                    location.reload();
                } else {
                    return errorToast.fire(response.data.message);
                }
            });
        }

        /**
         *
         */
        function storeMainCurrency() {
            let item = {
                main: $('#currency-main-select').val()
            };

            axios.post("{{ route('api.currencies.store.main') }}", { data: item })
            .then(response => {
                console.log(response.data)
                if (response.data.success) {
                    location.reload();
                } else {
                    return errorToast.fire(response.data.message);
                }
            });
        }

        /**
         *
         */
        function deleteCurrency(id) {
            $('#delete-currency-modal').modal('show');
            $('#delete-currency-id').val(id);
        }

        /**
         *
         */
        function confirmDelete() {
            let item = { id: $('#delete-currency-id').val() };

            axios.post("{{ route('api.taxes.destroy') }}", { data: item })
            .then(response => {
                if (response.data.success) {
                    location.reload();
                } else {
                    return errorToast.fire(response.data.message);
                }
            });
        }

        /**
         *
         * @param item
         */
        function editCurrency(item) {
            $('#currency-id').val(item.id);
            $('#currency-title').val(item.title);
            $('#currency-code').val(item.code);
            $('#currency-symbol-left').val(item.symbol_left);
            $('#currency-symbol-right').val(item.symbol_right);
            $('#currency-value').val(item.value);
            $('#currency-decimal-places').val(item.decimal_places);

            if (item.status) {
                $('#currency-status')[0].checked = item.status ? true : false;
            }

            if (item.main) {
                $('#currency-main')[0].checked = item.main ? true : false;
            }
        }
    </script>
@endpush
