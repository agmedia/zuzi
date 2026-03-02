@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Pelion API Testiranje</h1>
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <div class="row">
            <div class="col-lg-5">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Parametri</h3>
                    </div>
                    <div class="block-content block-content-full">
                        <div class="form-group">
                            <label for="pelion-api-key">Pelion API ključ (opcionalno)</label>
                            <input type="text" class="form-control" id="pelion-api-key" placeholder="Ako je prazno koristi se PELION_API_KEY iz .env">
                        </div>

                        <div class="form-group">
                            <label for="pelion-item-id">ItemId</label>
                            <input type="number" min="1" class="form-control" id="pelion-item-id" value="1">
                        </div>

                        <div class="form-group">
                            <label for="pelion-group-id">ItemGroupId</label>
                            <input type="number" min="1" class="form-control" id="pelion-group-id" value="23">
                        </div>

                        <div class="form-group">
                            <label for="pelion-item-type">ItemType</label>
                            <select class="form-control" id="pelion-item-type">
                                <option value="T">T - trgovačka roba</option>
                                <option value="K">K - komisija</option>
                                <option value="U">U - usluga</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Test pozivi</h3>
                    </div>
                    <div class="block-content block-content-full">
                        <div class="row">
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-primary btn-block" onclick="runPelionTest('item-list')">/itemList</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-primary btn-block" onclick="runPelionTest('item-list-attrs')">/itemList?ItemAttributes=D</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-primary btn-block" onclick="runPelionTest('item-by-id')">/itemList?ItemId={ItemId}</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-primary btn-block" onclick="runPelionTest('group-items')">/itemList?ItemGroupId={ItemGroupId}</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-primary btn-block" onclick="runPelionTest('group-active')">/itemList?ItemGroupId={ItemGroupId}&ItemActive=D</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-primary btn-block" onclick="runPelionTest('item-type')">/itemList?ItemType={T|K|U}</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-primary btn-block" onclick="runPelionTest('item-groups')">/itemGroupList</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-primary btn-block" onclick="runPelionTest('stock-list')">/stockList</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-primary btn-block" onclick="runPelionTest('stock-by-item')">/stockList?ItemId={ItemId}</button></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="block block-rounded" id="pelion-result-block">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Rezultat</h3>
                    </div>
                    <div class="block-content block-content-full">
                        <div class="mb-2">
                            <strong>Status:</strong> <span id="pelion-status">-</span><br>
                            <strong>URL:</strong> <span id="pelion-url">-</span>
                        </div>
                        <pre id="pelion-result" class="mb-0" style="max-height: 70vh; overflow: auto; background: #f8f9fc; border: 1px solid #e9ecef; padding: 12px; border-radius: 4px;">Odaberi test poziv za prikaz rezultata.</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script>
        function runPelionTest(action) {
            let block = $('#pelion-result-block');

            const payload = {
                action: action,
                item_id: $('#pelion-item-id').val() || null,
                item_group_id: $('#pelion-group-id').val() || null,
                item_type: $('#pelion-item-type').val() || null,
                api_key: $('#pelion-api-key').val() || null
            };

            block.addClass('block-mode-loading');

            axios.post('{{ route('api.api.pelion.test') }}', payload)
                .then(response => {
                    const data = response.data || {};

                    $('#pelion-status').text(data.status || response.status);
                    $('#pelion-url').text(data.url || '-');
                    $('#pelion-result').text(JSON.stringify(data.body ?? data, null, 2));

                    successToast.fire();
                })
                .catch(error => {
                    const data = error.response ? error.response.data : {error: error.message};

                    $('#pelion-status').text(error.response ? error.response.status : '500');
                    $('#pelion-url').text('-');
                    $('#pelion-result').text(JSON.stringify(data, null, 2));

                    errorToast.fire(data.error || 'Greška kod Pelion API poziva.');
                })
                .finally(() => {
                    block.removeClass('block-mode-loading');
                });
        }
    </script>
@endpush
