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
                            <label for="pelion-base-url">Pelion Base URL</label>
                            <input type="url" class="form-control" id="pelion-base-url" value="{{ $pelionBaseUrl }}" placeholder="https://zuzishop.pelionpro.com/api/v1">
                        </div>

                        <div class="form-group">
                            <label for="pelion-api-key">Pelion API ključ (opcionalno)</label>
                            <input type="text" class="form-control" id="pelion-api-key" placeholder="Ako je prazno koristi se PELION_API_KEY iz .env">
                        </div>

                        <div class="form-group">
                            <label for="pelion-item-id">ItemId</label>
                            <input type="number" min="1" class="form-control" id="pelion-item-id" value="1">
                        </div>

                        <div class="form-group">
                            <label for="pelion-isbn">ISBN / ITEMBARCODE</label>
                            <input type="text" class="form-control" id="pelion-isbn" value="9789533134574">
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
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-primary btn-block" onclick="runPelionTest('stock-by-isbn')">/stockList preko ISBN / ITEMBARCODE</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-secondary btn-block" onclick="runPelionTest('sync-item-index')">Osvježi Pelion barcode index</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-secondary btn-block" onclick="runPelionTest('sync-product-isbns')">Upiši Pelion ISBN i ItemID po SKU</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-success btn-block" onclick="runPelionTest('sync-product-quantities')">Updejtaj količine po ItemID</button></div>
                            <div class="col-12 mb-2"><button class="btn btn-sm btn-alt-success btn-block" onclick="runPelionTest('sync-product-shelves')">Upiši police po Pelion grupi</button></div>
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
                        <div id="pelion-summary" class="alert alert-info d-none mb-3"></div>
                        <div class="mb-2">
                            <strong>Status:</strong> <span id="pelion-status">-</span><br>
                            <strong>URL:</strong> <span id="pelion-url">-</span>
                        </div>
                        <pre id="pelion-result" class="mb-0" style="max-height: 70vh; overflow: auto; background: #f8f9fc; border: 1px solid #e9ecef; padding: 12px; border-radius: 4px;">Odaberi test poziv za prikaz rezultata.</pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="block block-rounded" id="pelion-name-review-block">
                    <div class="block-header block-header-default">
                        <h3 class="block-title">Sumnjivi artikli po nazivu</h3>
                        <div class="block-options">
                            <button type="button" class="btn btn-sm btn-alt-secondary" onclick="togglePelionNameCandidates(true)">Označi sve</button>
                            <button type="button" class="btn btn-sm btn-alt-secondary" onclick="togglePelionNameCandidates(false)">Poništi</button>
                            <button type="button" class="btn btn-sm btn-alt-primary" onclick="scanPelionNameMismatches()">Pronađi sumnjive</button>
                            <button type="button" class="btn btn-sm btn-alt-success" onclick="applyPelionNameMismatches()">Odobri odabrane</button>
                        </div>
                    </div>
                    <div class="block-content block-content-full">
                        <div class="row align-items-end mb-3">
                            <div class="col-md-2">
                                <label for="pelion-match-min-score">Minimalni score</label>
                                <input type="number" min="50" max="100" class="form-control" id="pelion-match-min-score" value="88">
                            </div>
                            <div class="col-md-2">
                                <label for="pelion-match-limit">Limit prijedloga</label>
                                <input type="number" min="1" max="500" class="form-control" id="pelion-match-limit" value="100">
                            </div>
                            <div class="col-md-4">
                                <div class="custom-control custom-checkbox mt-4">
                                    <input type="checkbox" class="custom-control-input" id="pelion-update-sku">
                                    <label class="custom-control-label" for="pelion-update-sku">Upiši i Pelion ITEMCODE u SKU gdje nema konflikta</label>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-right mt-3 mt-md-0">
                                <button type="button" class="btn btn-alt-primary" onclick="scanPelionNameMismatches()">Pronađi sumnjive artikle</button>
                                <button type="button" class="btn btn-alt-success" onclick="applyPelionNameMismatches()">Odobri odabrane</button>
                            </div>
                        </div>

                        <div id="pelion-name-summary" class="alert alert-info d-none mb-3"></div>

                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-vcenter font-size-sm mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 44px;"></th>
                                        <th style="width: 70px;">Score</th>
                                        <th>Lokalni artikl</th>
                                        <th>Trenutno</th>
                                        <th>Pelion prijedlog</th>
                                        <th>Promjene</th>
                                        <th>Napomena</th>
                                    </tr>
                                </thead>
                                <tbody id="pelion-name-candidates">
                                    <tr>
                                        <td colspan="7" class="text-muted">Pokreni pretragu za prikaz prijedloga.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script>
        let pelionNameCandidates = [];

        function runPelionTest(action) {
            if (action === 'sync-product-isbns' && !confirm('Upisati Pelion ITEMBARCODE u products.isbn i ITEMID u products.itemid prema products.sku?')) {
                return;
            }

            if (action === 'sync-product-quantities' && !confirm('Updejtati products.quantity prema Pelion stockList i products.itemid? Artikli kojih nema u Pelion stockList idu na 0, osim delivery_24h artikala koji ostaju kako jesu.')) {
                return;
            }

            if (action === 'sync-product-shelves' && !confirm('Upisati products.polica prema Pelion ITEMGROUPNAME i products.itemid?')) {
                return;
            }

            let block = $('#pelion-result-block');

            const payload = pelionBasePayload(action);

            block.addClass('block-mode-loading');
            $('#pelion-summary').addClass('d-none').empty();

            axios.post('{{ route('api.api.pelion.test') }}', payload)
                .then(response => {
                    const data = response.data || {};
                    const body = data.body || {};
                    const summary = data.summary || {};
                    const result = Object.keys(summary).length ? {summary: summary, body: body} : (data.body ?? data);

                    $('#pelion-status').text(data.status || response.status);
                    $('#pelion-url').text(data.url || '-');
                    $('#pelion-result').text(JSON.stringify(result, null, 2));

                    renderPelionSummary(action, body, summary);

                    successToast.fire();
                })
                .catch(error => {
                    const data = error.response ? error.response.data : {error: error.message};
                    const message = (data && data.error) ? data.error : (typeof data === 'string' && data ? data : 'Server je vratio praznu 500 grešku. Provjeri Laravel/PHP log.');

                    $('#pelion-status').text(error.response ? error.response.status : '500');
                    $('#pelion-url').text('-');
                    $('#pelion-result').text(typeof data === 'string' ? (data || message) : JSON.stringify(data, null, 2));

                    errorToast.fire(message);
                })
                .finally(() => {
                    block.removeClass('block-mode-loading');
                });
        }

        function scanPelionNameMismatches() {
            const block = $('#pelion-name-review-block');
            const payload = {
                ...pelionBasePayload('scan-name-mismatches'),
                min_score: $('#pelion-match-min-score').val() || 88,
                limit: $('#pelion-match-limit').val() || 100
            };

            block.addClass('block-mode-loading');
            $('#pelion-name-summary').addClass('d-none').empty();

            axios.post('{{ route('api.api.pelion.test') }}', payload)
                .then(response => {
                    const data = response.data || {};
                    const body = data.body || {};

                    pelionNameCandidates = body.candidates || [];
                    renderPelionNameCandidates(body);

                    $('#pelion-status').text(data.status || response.status);
                    $('#pelion-url').text(data.url || '-');
                    $('#pelion-result').text(JSON.stringify(body, null, 2));

                    successToast.fire();
                })
                .catch(error => {
                    const data = error.response ? error.response.data : {error: error.message};
                    const message = (data && data.error) ? data.error : 'Pretraga sumnjivih artikala nije uspjela.';

                    $('#pelion-name-candidates').html('<tr><td colspan="7" class="text-danger">' + escapeHtml(message) + '</td></tr>');
                    errorToast.fire(message);
                })
                .finally(() => {
                    block.removeClass('block-mode-loading');
                });
        }

        function applyPelionNameMismatches() {
            const matches = $('.pelion-name-candidate-checkbox:checked').map(function () {
                const candidate = pelionNameCandidates[Number($(this).data('index'))];

                return candidate ? {
                    product_id: candidate.product.id,
                    item_id: candidate.candidate.ITEMID
                } : null;
            }).get().filter(Boolean);

            if (!matches.length) {
                errorToast.fire('Odaberite barem jedan prijedlog.');
                return;
            }

            if (!confirm('Upisati odabrane Pelion ITEMID / ITEMBARCODE parove i odmah osvježiti stanje prema stockList?')) {
                return;
            }

            const block = $('#pelion-name-review-block');
            const payload = {
                ...pelionBasePayload('apply-name-mismatches'),
                update_sku: $('#pelion-update-sku').is(':checked'),
                matches: matches
            };

            block.addClass('block-mode-loading');

            axios.post('{{ route('api.api.pelion.test') }}', payload)
                .then(response => {
                    const data = response.data || {};
                    const body = data.body || {};

                    $('#pelion-name-summary')
                        .removeClass('d-none alert-danger')
                        .addClass('alert-info')
                        .html(
                            '<strong>' + escapeHtml(body.message || 'Pelion korekcije su primijenjene.') + '</strong><br>' +
                            'Primijenjeno: <strong>' + (body.applied || 0) + '</strong> | ' +
                            'Preskočeno: <strong>' + (body.skipped || 0) + '</strong> | ' +
                            'Količine osvježene: <strong>' + (body.quantity_updated || 0) + '</strong> | ' +
                            'SKU upisano: <strong>' + (body.sku_updated || 0) + '</strong>'
                        );

                    $('#pelion-status').text(data.status || response.status);
                    $('#pelion-url').text(data.url || '-');
                    $('#pelion-result').text(JSON.stringify(body, null, 2));

                    successToast.fire();
                })
                .catch(error => {
                    const data = error.response ? error.response.data : {error: error.message};
                    const message = (data && data.error) ? data.error : 'Primjena odabranih prijedloga nije uspjela.';

                    $('#pelion-name-summary')
                        .removeClass('d-none alert-info')
                        .addClass('alert-danger')
                        .text(message);

                    $('#pelion-result').text(JSON.stringify(data, null, 2));
                    errorToast.fire(message);
                })
                .finally(() => {
                    block.removeClass('block-mode-loading');
                });
        }

        function renderPelionNameCandidates(body) {
            const candidates = body.candidates || [];
            const rows = [];

            $('#pelion-name-summary')
                .removeClass('d-none alert-danger')
                .addClass('alert-info')
                .html(
                    '<strong>' + escapeHtml(body.message || 'Pretraga je završena.') + '</strong><br>' +
                    'Pronađeno: <strong>' + (body.candidates_found || 0) + '</strong> | ' +
                    'Skenirano lokalnih artikala: <strong>' + (body.products_scanned || 0) + '</strong> | ' +
                    'Pelion artikala: <strong>' + (body.valid_pelion_items || 0) + '</strong> | ' +
                    'Minimalni score: <strong>' + (body.min_score || '-') + '</strong>'
                );

            if (!candidates.length) {
                $('#pelion-name-candidates').html('<tr><td colspan="7" class="text-muted">Nema prijedloga za zadani prag.</td></tr>');
                return;
            }

            candidates.forEach((candidate, index) => {
                const product = candidate.product || {};
                const proposed = candidate.candidate || {};
                const currentItem = candidate.current_pelion_item || {};
                const conflict = candidate.conflict || null;
                const conflictText = conflict
                    ? (conflict.reasons || []).join(' ') + (conflict.existing_product ? ' Drugi artikl: #' + conflict.existing_product.id + ' ' + conflict.existing_product.name : '')
                    : '';

                rows.push(
                    '<tr class="' + (conflict ? 'table-warning' : '') + '">' +
                    '<td><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input pelion-name-candidate-checkbox" id="pelion-name-candidate-' + index + '" data-index="' + index + '"><label class="custom-control-label" for="pelion-name-candidate-' + index + '"></label></div></td>' +
                    '<td><span class="badge badge-' + (candidate.score >= 94 ? 'success' : 'warning') + '">' + escapeHtml(candidate.score) + '</span></td>' +
                    '<td><div class="font-w600">#' + escapeHtml(product.id) + ' ' + escapeHtml(product.name) + '</div><div class="text-muted">SKU: ' + emptyDash(product.sku) + ' | ISBN: ' + emptyDash(product.isbn || product.ean) + ' | ITEMID: ' + emptyDash(product.itemid) + ' | Qty: ' + emptyDash(product.quantity) + '</div></td>' +
                    '<td>' + (currentItem.ITEMID ? '<div class="font-w600">' + escapeHtml(currentItem.ITEMNAME || '-') + '</div><div class="text-muted">ITEMID: ' + emptyDash(currentItem.ITEMID) + '<br>BARCODE: ' + emptyDash(currentItem.ITEMBARCODE) + '<br>CODE: ' + emptyDash(currentItem.ITEMCODE) + '</div>' : '<span class="text-muted">Nema lokalnog Pelion para</span>') + '</td>' +
                    '<td><div class="font-w600">' + escapeHtml(proposed.ITEMNAME || '-') + '</div><div class="text-muted">ITEMID: ' + emptyDash(proposed.ITEMID) + '<br>BARCODE: ' + emptyDash(proposed.ITEMBARCODE) + '<br>CODE: ' + emptyDash(proposed.ITEMCODE) + '</div></td>' +
                    '<td>' + renderReasonList(candidate.reasons || []) + '</td>' +
                    '<td>' + (conflictText ? '<span class="text-warning">' + escapeHtml(conflictText) + '</span>' : '<span class="text-muted">-</span>') + '</td>' +
                    '</tr>'
                );
            });

            $('#pelion-name-candidates').html(rows.join(''));
        }

        function togglePelionNameCandidates(checked) {
            $('.pelion-name-candidate-checkbox').prop('checked', checked);
        }

        function pelionBasePayload(action) {
            return {
                action: action,
                item_id: $('#pelion-item-id').val() || null,
                isbn: $('#pelion-isbn').val() || null,
                item_group_id: $('#pelion-group-id').val() || null,
                item_type: $('#pelion-item-type').val() || null,
                base_url: $('#pelion-base-url').val() || null,
                api_key: $('#pelion-api-key').val() || null
            };
        }

        function renderReasonList(reasons) {
            if (!reasons.length) {
                return '<span class="text-muted">-</span>';
            }

            return '<ul class="mb-0 pl-3">' + reasons.map(reason => '<li>' + escapeHtml(reason) + '</li>').join('') + '</ul>';
        }

        function emptyDash(value) {
            return value === null || value === undefined || value === '' ? '<span class="text-muted">-</span>' : escapeHtml(value);
        }

        function escapeHtml(value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderPelionSummary(action, body, summary) {
            if (!body) {
                return;
            }

            if (action === 'stock-list' && summary && Object.keys(summary).length) {
                $('#pelion-summary')
                    .removeClass('d-none')
                    .html(
                        '<strong>Pelion stockList je dohvaćen.</strong><br>' +
                        'Artikala sa stanjem &gt; 0: <strong>' + (summary.stock_items_quantity_gt_0 || 0) + '</strong> | ' +
                        'ItemID-a ukupno: <strong>' + (summary.stock_itemids_received || 0) + '</strong> | ' +
                        'Redova ukupno: <strong>' + (summary.stock_rows_received || 0) + '</strong>'
                    );
            }

            if (action === 'sync-product-quantities') {
                $('#pelion-summary')
                    .removeClass('d-none')
                    .html(
                        '<strong>' + (body.message || 'Pelion količine su updejtane.') + '</strong><br>' +
                        'Pelion artikala sa stanjem &gt; 0: <strong>' + (body.pelion_stock_items_quantity_gt_0 || 0) + '</strong> | ' +
                        'Updejtano: <strong>' + (body.updated || 0) + '</strong> | ' +
                        'Delivery 24h preskočeno: <strong>' + (body.skipped_delivery_24h_products || 0) + '</strong> | ' +
                        'Količina &gt; 0: <strong>' + (body.quantity_gt_zero || 0) + '</strong>'
                    );
            }

            if (action === 'sync-product-shelves') {
                $('#pelion-summary')
                    .removeClass('d-none')
                    .html(
                        '<strong>' + (body.message || 'Pelion police su upisane.') + '</strong><br>' +
                        'Updejtano: <strong>' + (body.updated || 0) + '</strong> | ' +
                        'Bez promjene: <strong>' + (body.unchanged || 0) + '</strong> | ' +
                        'Matchano: <strong>' + (body.matched_products || 0) + '</strong> | ' +
                        'Nema lokalni artikl: <strong>' + (body.missing_products || 0) + '</strong>'
                    );
            }
        }
    </script>
@endpush
