<div class="modal fade" id="payment-modal-cod" tabindex="-1" role="dialog" aria-labelledby="modal-payment-modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-popout" role="document">
        <div class="modal-content rounded">
            <div class="block block-themed block-transparent mb-0">
                <div class="block-header bg-primary">
                    <h3 class="block-title">Isporuka pouzećem</h3>
                    <div class="block-options">
                        <a class="text-muted font-size-h3" href="#" data-dismiss="modal" aria-label="Close">
                            <i class="fa fa-times"></i>
                        </a>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center">
                        <div class="col-md-10">

                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="cod-title">Naslov</label>
                                        <input type="text" class="form-control" id="cod-title" name="title">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="cod-min">Min. iznos narudžbe</label>
                                        <input type="text" class="form-control" id="cod-min" name="min">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="cod-price">Iznos naknade</label>
                                        <input type="text" class="form-control" id="cod-price" name="data['price']">
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <label for="cod-geo-zone">Geo zona <span class="small text-gray">(Geo zona na koju se odnosi dostava..)</span></label>
                                    <select class="js-select2 form-control" id="cod-geo-zone" name="geo_zone" style="width: 100%;" data-placeholder="Odaberite geo zonu">
                                        <option></option>
                                        @foreach ($geo_zones as $geo_zone)
                                            <option value="{{ $geo_zone->id }}" {{ ((isset($payment)) and ($payment->geo_zone == $geo_zone->id)) ? 'selected' : '' }}>{{ $geo_zone->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="cod-short-description">Kratki opis <span class="small text-gray">(Prikazuje se prilikom odabira plaćanja.)</span></label>
                                <textarea class="js-maxlength form-control" id="cod-short-description" name="data['short_description']" rows="2" maxlength="160" data-always-show="true" data-placement="top"></textarea>
                                <small class="form-text text-muted">
                                    160 znakova max
                                </small>
                            </div>

                            <div class="form-group mb-4">
                                <label for="cod-description">Detaljni opis <span class="small text-gray">(Ako je potreban. Prikazuje se ako je plaćanje odabrano prilikom kupnje.)</span></label>
                                <textarea class="form-control" id="cod-description" name="data['description']" rows="4"></textarea>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="cod-price">Poredak</label>
                                        <input type="text" class="form-control" id="cod-sort-order" name="sort_order">
                                    </div>
                                </div>
                                <div class="col-md-6 text-right" style="padding-top: 37px;">
                                    <div class="form-group">
                                        <label class="css-control css-control-sm css-control-success css-switch res">
                                            <input type="checkbox" class="css-control-input" id="cod-status" name="status">
                                            <span class="css-control-indicator"></span> Status načina plaćanja
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="cod-code" name="code" value="cod">
                        </div>
                    </div>
                </div>
                <div class="block-content block-content-full text-right bg-light">
                    <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                        Odustani <i class="fa fa-times ml-2"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" onclick="event.preventDefault(); create_cod();">
                        Snimi <i class="fa fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('payment-modal-js')
    <script>
        $(() => {
            $('#cod-geo-zone').select2({
                minimumResultsForSearch: Infinity,
                allowClear: true
            });
        });
        /**
         *
         */
        function create_cod() {
            let item = {
                title: $('#cod-title').val(),
                code: $('#cod-code').val(),
                min: $('#cod-min').val(),
                data: {
                    price: $('#cod-price').val(),
                    short_description: $('#cod-short-description').val(),
                    description: $('#cod-description').val(),
                },
                geo_zone: $('#cod-geo-zone').val(),
                status: $('#cod-status')[0].checked,
                sort_order: $('#cod-sort-order').val()
            };

            axios.post("{{ route('api.payment.store') }}", {data: item})
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
        function edit_cod(item) {
            $('#cod-title').val(item.title);
            $('#cod-min').val(item.min);
            $('#cod-price').val(item.data.price);
            $('#cod-short-description').val(item.data.short_description);
            $('#cod-description').val(item.data.description);
            $('#cod-sort-order').val(item.sort_order);
            $('#cod-code').val(item.code);

            if (item.status) {
                $('#cod-status')[0].checked = item.status ? true : false;
            }
        }
    </script>
@endpush