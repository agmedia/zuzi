@extends('back.layouts.backend')

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Porezi</h1>
                <button class="btn btn-hero-success my-2" onclick="event.preventDefault(); openModal();">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> Dodaj novi</span>
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
                        <th style="width: 60%;">Naziv</th>
                        <th class="text-center">Stopa</th>
                        <th class="text-center">Poredak</th>
                        <th class="text-right">Uredi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($taxes as $tax)
                        <tr>
                            <td>{{ $tax->id }}</td>
                            <td>{{ $tax->title }}</td>
                            <td class="text-center">{{ $tax->rate }}</td>
                            <td class="text-center">{{ $tax->sort_order }}</td>
                            <td class="text-right font-size-sm">
                                <button class="btn btn-sm btn-alt-secondary" onclick="event.preventDefault(); openModal({{ json_encode($tax) }});">
                                    <i class="fa fa-fw fa-pencil-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-alt-danger" onclick="event.preventDefault(); deleteTax({{ $tax->id }});">
                                    <i class="fa fa-fw fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr class="text-center">
                            <td colspan="4">Nema poreza...</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="tax-modal" tabindex="-1" role="dialog" aria-labelledby="tax--modal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-popout" role="document">
            <div class="modal-content rounded">
                <div class="block block-themed block-transparent mb-0">
                    <div class="block-header bg-primary">
                        <h3 class="block-title">Tax narudžbe</h3>
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
                                    <label for="tax-title">Naslov</label>
                                    <input type="text" class="form-control" id="tax-title" name="title">
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tax-rate">Stopa</label>
                                            <input type="text" class="form-control" id="tax-rate" name="rate">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="tax-sort-order">Poredak</label>
                                            <input type="text" class="form-control" id="tax-sort-order" name="sort_order">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="css-control css-control-sm css-control-success css-switch res">
                                        <input type="checkbox" class="css-control-input" id="tax-status" name="status">
                                        <span class="css-control-indicator"></span> Status poreza
                                    </label>
                                </div>

                                <input type="hidden" id="tax-id" name="id" value="0">
                                <input type="hidden" id="tax-geo-zone" name="geo_zone" value="1">
                            </div>
                        </div>
                    </div>
                    <div class="block-content block-content-full text-right bg-light">
                        <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                            Odustani <i class="fa fa-times ml-2"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" onclick="event.preventDefault(); createTax();">
                            Snimi <i class="fa fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="delete-tax-modal" tabindex="-1" role="dialog" aria-labelledby="tax--modal" aria-hidden="true">
        <div class="modal-dialog modal-dialog-popout" role="document">
            <div class="modal-content rounded">
                <div class="block block-themed block-transparent mb-0">
                    <div class="block-header bg-primary">
                        <h3 class="block-title">Obriši porez</h3>
                        <div class="block-options">
                            <a class="text-muted font-size-h3" href="#" data-dismiss="modal" aria-label="Close">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>
                    <div class="block-content">
                        <div class="row justify-content-center mb-3">
                            <div class="col-md-10">
                                <h4>Jeste li sigurni da želite obrisati porez?</h4>
                                <input type="hidden" id="delete-tax-id" value="0">
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
    <script>
        /**
         *
         * @param item
         * @param type
         */
        function openModal(item = {}) {
            console.log(item);

            $('#tax-modal').modal('show');
            editTax(item);
        }

        /**
         *
         */
        function createTax() {
            let item = {
                id: $('#tax-id').val(),
                geo_zone: $('#tax-geo-zone').val(),
                title: $('#tax-title').val(),
                rate: $('#tax-rate').val(),
                sort_order: $('#tax-sort-order').val(),
                status: $('#tax-status')[0].checked,
            };

            axios.post("{{ route('api.taxes.store') }}", {data: item})
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
        function deleteTax(id) {
            $('#delete-tax-modal').modal('show');
            $('#delete-tax-id').val(id);
        }

        /**
         *
         */
        function confirmDelete() {
            let item = {
                id: $('#delete-tax-id').val()
            };

            axios.post("{{ route('api.taxes.destroy') }}", {data: item})
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
         * @param item
         */
        function editTax(item) {
            $('#tax-id').val(item.id);
            $('#tax-geo-zone').val(item.geo_zone);
            $('#tax-title').val(item.title);
            $('#tax-rate').val(item.rate);
            $('#tax-sort-order').val(item.sort_order);

            if (item.status) {
                $('#tax-status')[0].checked = item.status ? true : false;
            }
        }
    </script>
@endpush
