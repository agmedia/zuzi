<div class="modal fade" id="payment-modal-wspay" tabindex="-1" role="dialog" aria-labelledby="modal-payment-modal" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-popout" role="document">
        <div class="modal-content rounded">
            <div class="block block-themed block-transparent mb-0">
                <div class="block-header bg-primary">
                    <h3 class="block-title">WSPay Payment Gateway</h3>
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
                                        <label for="wspay-title">Naslov</label>
                                        <input type="text" class="form-control" id="wspay-title" name="title">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="wspay-min">Min. iznos narudžbe</label>
                                        <input type="text" class="form-control" id="wspay-min" name="min">
                                    </div>
                                </div>
                                <div class="col-md-8"></div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="wspay-price">Iznos naknade</label>
                                        <input type="text" class="form-control" id="wspay-price" name="data['price']">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label for="wspay-short-description">Kratki opis <span class="small text-gray">(Prikazuje se prilikom odabira plaćanja.)</span></label>
                                <textarea class="js-maxlength form-control" id="wspay-short-description" name="data['short_description']" rows="2" maxlength="160" data-always-show="true" data-placement="top"></textarea>
                                <small class="form-text text-muted">
                                    160 znakova max
                                </small>
                            </div>

                            <div class="form-group mb-4">
                                <label for="wspay-description">Detaljni opis <span class="small text-gray">(Ako je potreban. Prikazuje se ako je plaćanje odabrano prilikom kupnje.)</span></label>
                                <textarea class="form-control" id="wspay-description" name="data['description']" rows="4"></textarea>
                            </div>

                            <div class="block block-themed block-transparent mb-4">
                                <div class="block-content bg-body pb-3">
                                    <div class="row justify-content-center">
                                        <div class="col-md-11">
                                            <div class="form-group">
                                                <label for="wspay-shop-id">ID prodajnog mjesta (ShopID):</label>
                                                <input type="text" class="form-control" id="wspay-shop-id" name="data['shop_id']">
                                            </div>
                                            <div class="form-group">
                                                <label for="wspay-secret-key">Tajni ključ (SecretKey):</label>
                                                <input type="text" class="form-control" id="wspay-secret-key" name="data['secret_key']">
                                            </div>
                                            <div class="form-group">
                                                <label for="wspay-type">Tip autorizacije</label>
                                                <select class="js-select2 form-control" id="wspay-type" name="data['type']" style="width: 100%;" data-placeholder="Odaberite tip autorizacije">
                                                    <option value="1">Autorizacija u jednom koraku (automatska autorizacija)</option>
                                                    <option value="0">Authtorizacija u dva koraka (predautorizacija)</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="wspay-callback">URL za slanje odgovora: <span class="small text-gray">Ovo također mora biti upisano u WSPay control panelu.</span></label>
                                                <input type="text" class="form-control" id="wspay-callback" name="data['callback']" value="{{ url('/') }}">
                                            </div>

                                            <div class="form-group">
                                                <div class="row">
                                                    <div class="col-md-3">
                                                        <label class="d-block">Test mod.</label>
                                                    </div>
                                                    <div class="col-md-9">
                                                        <div class="custom-control custom-radio custom-control-inline custom-control-success custom-control-lg">
                                                            <input type="radio" class="custom-control-input" id="wspay-test-on" name="test" checked="" value="1">
                                                            <label class="custom-control-label" for="wspay-test-on">Da</label>
                                                        </div>
                                                        <div class="custom-control custom-radio custom-control-inline custom-control-danger custom-control-lg">
                                                            <input type="radio" class="custom-control-input" id="wspay-test-off" name="test" value="0">
                                                            <label class="custom-control-label" for="wspay-test-off">Ne</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label for="wspay-geo-zone">Geo zona <span class="small text-gray">(Ostaviti prazno ako se odnosi na sve..)</span></label>
                                                <select class="js-select2 form-control" id="wspay-geo-zone" name="geo_zone" style="width: 100%;" data-placeholder="Odaberite geo zonu">
                                                    <option></option>
                                                    @foreach ($geo_zones as $geo_zone)
                                                        <option value="{{ $geo_zone->id }}" {{ ((isset($shipping)) and ($shipping->geo_zone == $geo_zone->id)) ? 'selected' : '' }}>{{ $geo_zone->title }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="wspay-price">Poredak</label>
                                        <input type="text" class="form-control" id="wspay-sort-order" name="sort_order">
                                    </div>
                                </div>
                                <div class="col-md-6 text-right" style="padding-top: 37px;">
                                    <div class="form-group">
                                        <label class="css-control css-control-sm css-control-success css-switch res">
                                            <input type="checkbox" class="css-control-input" id="wspay-status" name="status">
                                            <span class="css-control-indicator"></span> Status načina plaćanja
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" id="wspay-code" name="code" value="wspay">
                        </div>
                    </div>
                </div>
                <div class="block-content block-content-full text-right bg-light">
                    <a class="btn btn-sm btn-light" data-dismiss="modal" aria-label="Close">
                        Odustani <i class="fa fa-times ml-2"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" onclick="event.preventDefault(); create_wspay();">
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
            $('#wspay-type').select2({
                minimumResultsForSearch: Infinity
            });

            $('#wspay-geo-zone').select2({
                minimumResultsForSearch: Infinity,
                allowClear: true
            });
        });
        /**
         *
         */
        function create_wspay() {
            let item = {
                title: $('#wspay-title').val(),
                code: $('#wspay-code').val(),
                min: $('#wspay-min').val(),
                data: {
                    price: $('#wspay-price').val(),
                    short_description: $('#wspay-short-description').val(),
                    description: $('#wspay-description').val(),
                    shop_id: $('#wspay-shop-id').val(),
                    secret_key: $('#wspay-secret-key').val(),
                    type: $('#wspay-type').val(),
                    callback: $('#wspay-callback').val(),
                    test: $("input[name='test']:checked").val(),
                },
                geo_zone: $('#wspay-geo-zone').val(),
                status: $('#wspay-status')[0].checked,
                sort_order: $('#wspay-sort-order').val()
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
        function edit_wspay(item) {
            $('#wspay-title').val(item.title);
            $('#wspay-min').val(item.min);
            $('#wspay-price').val(item.data.price);
            $('#wspay-short-description').val(item.data.short_description);
            $('#wspay-description').val(item.data.description);

            $('#wspay-shop-id').val(item.data.shop_id);
            $('#wspay-secret-key').val(item.data.secret_key);
            $('#wspay-callback').val(item.data.callback);

            $("input[name=test][value='" + item.data.test + "']").prop("checked",true);

            $('#wspay-type').val(item.data.type);
            $('#wspay-type').trigger('change');
            $('#wspay-geo-zone').val(item.geo_zone);
            $('#wspay-geo-zone').trigger('change');

            $('#wspay-sort-order').val(item.sort_order);
            $('#wspay-code').val(item.code);

            if (item.status) {
                $('#wspay-status')[0].checked = item.status ? true : false;
            }
        }
    </script>
@endpush