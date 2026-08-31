@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('js/plugins/magnific-popup/magnific-popup.css') }}">
    <style>
        .laguna-category-dropdown .select2-search--dropdown {
            padding: .75rem;
            border-bottom: 1px solid #e4e7ed;
            background: #fff;
        }

        .laguna-category-dropdown .select2-search__field {
            padding: .55rem .75rem;
            border-radius: .25rem;
        }

        .laguna-category-dropdown .select2-results__options {
            max-height: 320px;
        }

        .laguna-category-dropdown .select2-results__option {
            padding: .5rem .75rem;
        }

        .laguna-category-option__title {
            font-weight: 600;
            line-height: 1.25;
        }

        .laguna-category-option__meta {
            margin-top: .15rem;
            font-size: .75rem;
            line-height: 1.2;
            opacity: .72;
        }

        .laguna-category-option--child {
            position: relative;
            padding-left: 1.5rem;
        }

        .laguna-category-option--child::before {
            position: absolute;
            top: .15rem;
            left: .25rem;
            content: "↳";
            font-size: 1rem;
            font-weight: 600;
            opacity: .65;
        }
    </style>
@endpush

@section('content')
    @php
        $importUi = array_merge([
            'name' => 'Laguna',
            'slug' => 'laguna',
            'route_prefix' => 'laguna-import',
            'route_parameter' => 'lagunaImportProduct',
            'config_key' => 'laguna_import',
            'source_site' => 'Laguna.rs',
            'subtitle' => 'Inkrementalni uvoz knjiga s provjerom ISBN-a i opcionalnim prijevodom opisa na hrvatski',
            'source_id_label' => 'Laguna šifra',
            'allowed_categories_label' => 'Samo kategorija Knjige',
            'required_mapping_label' => 'Nakladnici › Laguna',
            'publisher_category_label' => 'Laguna podkategorija',
            'default_publisher_label' => 'Laguna',
            'supports_source_mapping' => false,
            'supports_source_publisher_mapping' => null,
            'supports_source_taxonomy_mapping' => null,
            'supports_source_category_filter' => false,
            'supports_translation' => true,
            'uses_exchange_rate' => true,
            'source_price_field' => 'price_rsd',
            'source_sale_price_field' => 'sale_price_rsd',
            'source_currency' => 'RSD',
            'price_preview_source_amount' => 1500,
            'feed_link_label' => 'Otvori RSS',
            'feed_url_config_key' => 'feed_url',
            'source_category_label' => 'Izvorna kategorija',
            'source_subcategory_label' => 'Izvorna podkategorija',
            'source_filter_help' => 'Podkategorija filtrira provjerene i obogaćene knjige. Neprovjerene knjige još nemaju taj podatak.',
            'source_taxonomy_label' => 'izvornih',
            'source_taxonomy_item_label' => 'podkategorija',
            'source_taxonomy_items_label' => 'podkategorija',
            'secondary_source_id_label' => null,
            'source_id_field' => 'external_id',
            'inspection_workers' => 2,
            'inspection_delay_ms' => 250,
            'bulk_inspection_route' => null,
            'bulk_inspection_limit' => 100,
            'bulk_inspection_delay_ms' => 350,
            'supports_batched_refresh' => false,
            'refresh_start_route' => null,
            'refresh_step_route' => null,
        ], $importUi ?? []);
        $importUi['supports_source_publisher_mapping'] = $importUi['supports_source_publisher_mapping'] ?? $importUi['supports_source_mapping'];
        $importUi['supports_source_taxonomy_mapping'] = $importUi['supports_source_taxonomy_mapping'] ?? $importUi['supports_source_mapping'];
        $productsTabId = $importUi['slug'] . '-products';
        $settingsTabId = $importUi['slug'] . '-settings';
        $routePrefix = $importUi['route_prefix'];
        $feedRefreshStartEndpoint = $importUi['supports_batched_refresh'] && $importUi['refresh_start_route']
            ? route($importUi['refresh_start_route'])
            : null;
        $feedRefreshStepEndpoint = $importUi['supports_batched_refresh'] && $importUi['refresh_step_route']
            ? route($importUi['refresh_step_route'])
            : null;
        $sourceGenres = collect($sourceGenres ?? []);
        $sourceTaxonomy = (array) ($sourceTaxonomy ?? []);
        $genreCategoryMap = (array) ($settings['genre_category_map'] ?? []);
        $statusLabels = [
            'pending' => ['Nije provjeren', 'secondary'],
            'new' => ['Novi', 'info'],
            'existing' => ['Već postoji', 'warning'],
            'imported' => ['Uvezen', 'success'],
            'changed' => ['Promijenjen u feedu', 'primary'],
            'conflict' => ['Konflikt', 'danger'],
            'error' => ['Greška', 'danger'],
            'missing' => ['Nije u feedu', 'dark'],
        ];
        $settingsTabActive = request('tab') === 'settings' || $errors->any();
        $selectedStatus = trim((string) request('status', 'new')) ?: 'new';
        $selectedSourceCategory = trim((string) request('source_category'));
        $selectedSourceGenre = trim((string) request('source_genre'));
        $hasListFilters = request('search') || request('status') || request('source_category') || request('source_genre');
        $statusFilterQuery = request()->except(['page', 'status', 'tab', 'product_type', 'refresh_token']);
    @endphp

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <div>
                    <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">{{ $importUi['name'] }} import</h1>
                    <div class="text-muted">{{ $importUi['subtitle'] }}</div>
                </div>
                <form action="{{ route($routePrefix . '.refresh') }}" method="post" class="my-2 text-sm-right" data-refresh-form>
                    @csrf
                    <button class="btn btn-hero-primary" type="submit">
                        <i class="fa fa-sync-alt mr-1"></i> Osvježi feed
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="content">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fa fa-check-circle font-size-h4 mr-3"></i>
                    <div><strong>Gotovo.</strong> {{ session('success') }}</div>
                </div>
                <button type="button" class="close" data-dismiss="alert" aria-label="Zatvori"><span aria-hidden="true">&times;</span></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fa fa-exclamation-circle font-size-h4 mr-3"></i>
                    <div><strong>Feed nije osvježen.</strong> {{ session('error') }}</div>
                </div>
                <button type="button" class="close" data-dismiss="alert" aria-label="Zatvori"><span aria-hidden="true">&times;</span></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>Provjerite postavke:</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($importUi['supports_batched_refresh'])
            <div class="alert alert-info d-none" role="status" aria-live="polite" data-feed-refresh-state>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="font-w600" data-feed-refresh-message>Pripremam preuzimanje…</div>
                    <div class="small ml-3 text-nowrap" data-feed-refresh-pages></div>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0" data-feed-refresh-progress></div>
                </div>
                <div class="small mt-2 mb-0">Preuzimanje ide u kratkim koracima i može se sigurno nastaviti ako se stranica prekine.</div>
            </div>
        @endif

        <div class="block block-rounded mb-4">
            <ul class="nav nav-tabs nav-tabs-alt nav-justified" role="tablist">
                <li class="nav-item">
                    <a class="nav-link{{ ! $settingsTabActive ? ' active' : '' }}" data-toggle="tab" href="#{{ $productsTabId }}" role="tab">
                        <i class="fa fa-book mr-1"></i> Knjige
                        <span class="badge badge-pill badge-light ml-1">{{ number_format($statusCounts['all'], 0, ',', '.') }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link{{ $settingsTabActive ? ' active' : '' }}" data-toggle="tab" href="#{{ $settingsTabId }}" role="tab">
                        <i class="fa fa-sliders-h mr-1"></i> Postavke importa
                    </a>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade{{ ! $settingsTabActive ? ' show active' : '' }}" id="{{ $productsTabId }}" role="tabpanel">
                <div class="block block-rounded">
                    <div class="block-content block-content-full">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between">
                            <div class="d-flex align-items-center mb-3 mb-lg-0">
                                <div class="item item-rounded bg-{{ $feedMetadata['exists'] ? 'success-light' : 'warning-light' }} text-{{ $feedMetadata['exists'] ? 'success' : 'warning' }} mr-3">
                                    <i class="fa fa-{{ $feedMetadata['exists'] ? 'check' : 'exclamation-triangle' }}"></i>
                                </div>
                                <div>
                                    <div class="font-w600">{{ $feedMetadata['exists'] ? $importUi['name'] . ' feed je spreman' : 'Feed još nije preuzet' }}</div>
                                    @if($feedMetadata['exists'])
                                        <div class="text-muted small">
                                            {{ number_format($feedMetadata['count'], 0, ',', '.') }} knjiga · {{ number_format($feedMetadata['bytes'] / 1048576, 1, ',', '.') }} MB · osvježeno {{ $feedMetadata['modified_at'] }}
                                        </div>
                                    @else
                                        <div class="text-muted small">Kliknite „Osvježi feed” za učitavanje radne liste.</div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-lg-right">
                                <span class="badge badge-success p-2 mr-2"><i class="fa fa-filter mr-1"></i> {{ $importUi['allowed_categories_label'] }}</span>
                                <a class="btn btn-sm btn-alt-secondary" href="{{ config($importUi['config_key'] . '.' . $importUi['feed_url_config_key']) }}" target="_blank" rel="noopener noreferrer">
                                    <i class="fa fa-external-link-alt mr-1"></i> {{ $importUi['feed_link_label'] }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="block block-rounded">
                    <div class="block-header block-header-default d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center">
                        <h3 class="block-title">Filtriraj po statusu</h3>
                        <div class="block-options mt-2 mt-sm-0">
                            <button class="btn btn-sm btn-alt-primary" type="button" data-inspect-all title="Broj označava zapise koji još nisu provjereni; status Novi dodatno se uspoređuje s aktualnim Zuzi katalogom." {{ $inspectionPendingCount === 0 ? 'disabled' : '' }}>
                                <i class="fa fa-search mr-1" data-inspect-all-icon></i>
                                <span data-inspect-all-label>Provjeri sve neprovjerene</span>
                                <span class="badge badge-primary ml-1" data-inspect-all-count data-count="{{ $inspectionPendingCount }}">{{ number_format($inspectionPendingCount, 0, ',', '.') }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="block-content block-content-full pb-2">
                        <div class="mb-3 d-none" data-inspect-all-progress-wrap>
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between mb-2">
                                <div class="small" data-inspect-all-progress>Pripremam provjeru…</div>
                                <div class="small text-muted mt-1 mt-sm-0">Možete zaustaviti i nastaviti kasnije. Zapisi označeni „Novi” dodatno se uspoređuju s aktualnim Zuzi katalogom pri prikazu i prije importa.</div>
                            </div>
                            <div class="progress" style="height:8px">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0" data-inspect-all-progress-bar></div>
                            </div>
                        </div>
                        @foreach($statusCounts as $status => $count)
                            @php
                                $isActiveStatus = $selectedStatus === $status;
                                $statusColor = $status === 'all' ? 'secondary' : ($statusLabels[$status][1] ?? 'secondary');
                            @endphp
                            <a class="btn btn-sm {{ $isActiveStatus ? 'btn-' . $statusColor : 'btn-alt-' . $statusColor }} mr-2 mb-2"
                               href="{{ route($routePrefix . '.index', array_merge($statusFilterQuery, ['status' => $status])) }}">
                                {{ $status === 'all' ? 'Svi' : ($statusLabels[$status][0] ?? $status) }}
                                <span class="badge badge-light ml-1">{{ $count }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Artikli iz feeda <span class="text-muted">{{ number_format($products->total(), 0, ',', '.') }}</span></h3>
            </div>
            <div class="block-content bg-body-light">
                <form action="{{ route($routePrefix . '.index') }}" method="get">
                    <div class="form-row align-items-end">
                        <div class="form-group {{ $importUi['supports_source_category_filter'] ? 'col-md-6 col-xl-3' : 'col-lg-6' }}">
                            <label for="search">Naziv, {{ $importUi['source_id_label'] }} ili ISBN</label>
                            <input id="search" class="form-control" type="text" name="search" value="{{ request('search') }}">
                        </div>
                        @if($importUi['supports_source_category_filter'])
                            <div class="form-group col-md-6 col-xl-2">
                                <label for="source-category">{{ $importUi['source_category_label'] }}</label>
                                <select id="source-category" class="form-control" name="source_category">
                                    <option value="">Sve kategorije</option>
                                    @foreach($sourceTaxonomy as $sourceCategory => $sourceCategoryGenres)
                                        <option value="{{ $sourceCategory }}" {{ $selectedSourceCategory === $sourceCategory ? 'selected' : '' }}>{{ $sourceCategory }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6 col-xl-3">
                                <label for="source-genre">{{ $importUi['source_subcategory_label'] }}</label>
                                <select id="source-genre" class="form-control" name="source_genre">
                                    <option value="">Sve podkategorije</option>
                                    @foreach($sourceTaxonomy as $sourceCategory => $sourceCategoryGenres)
                                        @foreach($sourceCategoryGenres as $sourceGenre)
                                            <option value="{{ $sourceGenre }}"
                                                    data-source-category="{{ $sourceCategory }}"
                                                    {{ $selectedSourceGenre === $sourceGenre && ($selectedSourceCategory === '' || $selectedSourceCategory === $sourceCategory) ? 'selected' : '' }}>{{ $sourceCategory }} › {{ $sourceGenre }}</option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="form-group {{ $importUi['supports_source_category_filter'] ? 'col-md-6 col-xl-2' : 'col-lg-4' }}">
                            <label for="status">Status</label>
                            <select id="status" class="form-control" name="status">
                                <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>Svi statusi</option>
                                @foreach($statusLabels as $status => $label)
                                    <option value="{{ $status }}" {{ $selectedStatus === $status ? 'selected' : '' }}>{{ $label[0] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group {{ $importUi['supports_source_category_filter'] ? 'col-md-6 col-xl-2' : 'col-lg-2' }}">
                            <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-filter mr-1"></i> Filtriraj</button>
                        </div>
                    </div>
                    @if($importUi['supports_source_category_filter'] || $hasListFilters)
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mt-n2 mb-3">
                            @if($importUi['supports_source_category_filter'])
                                <small class="text-muted mr-md-3">{{ $importUi['source_filter_help'] }}</small>
                            @else
                                <span></span>
                            @endif
                            @if($hasListFilters)
                                <a class="btn btn-sm btn-link px-0 flex-shrink-0" href="{{ route($routePrefix . '.index') }}">Očisti filtre</a>
                            @endif
                        </div>
                    @endif
                </form>
            </div>

            <div class="block-content">
                <div class="bg-body-light rounded p-3 mb-3">
                    <div class="form-row align-items-start">
                        <div class="form-group col-lg-5 mb-2">
                            <label for="batch-category">Dodatna kategorija za označene <span class="text-muted">(po želji)</span></label>
                            <select id="batch-category" class="form-control" data-import-category style="width:100%">
                                <option value=""></option>
                                @foreach($categories as $group => $groupCategories)
                                    @foreach($groupCategories as $categoryId => $category)
                                        <option value="{{ $categoryId }}"
                                                data-category-title="{{ $category['title'] }}"
                                                data-category-path="{{ $category['title'] }}">{{ $category['title'] }}</option>
                                        @foreach($category['subs'] ?? [] as $subcategoryId => $subcategory)
                                            <option value="{{ $subcategoryId }}"
                                                    data-category-title="{{ $subcategory['title'] }}"
                                                    data-category-parent="{{ $category['title'] }}"
                                                    data-category-path="{{ $category['title'] }} › {{ $subcategory['title'] }}">{{ $category['title'] }} › {{ $subcategory['title'] }}</option>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </select>
                            <div class="small text-muted mt-1">Vrijedi za skupni i pojedinačni uvoz. Prazno polje znači samo obavezne kategorije {{ $importUi['required_mapping_label'] }}.</div>
                        </div>
                        <div class="form-group col-lg-7 mb-2">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <label class="mb-0">Skupne akcije</label>
                                <span class="badge badge-primary px-2 py-1">Odabrano: <strong data-selected-count>0</strong></span>
                            </div>
                            <div class="d-flex flex-column flex-sm-row align-items-stretch">
                                <button class="btn btn-outline-primary flex-fill mr-sm-2 mb-2 mb-sm-0" type="button" data-run-action="inspect" disabled><i class="fa fa-search mr-1"></i> Provjeri označene</button>
                                <button class="btn btn-success flex-fill" type="button" data-run-action="import" disabled><i class="fa fa-download mr-1"></i> Uvezi označene</button>
                            </div>
                            <div class="small text-muted mt-2" data-progress>Označite jednu ili više knjiga za skupnu obradu.</div>
                        </div>
                    </div>
                </div>

                <div class="progress mb-3 d-none" data-progress-bar-wrap>
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0" data-progress-bar></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-striped table-vcenter">
                        <thead>
                        <tr>
                            <th style="width:35px"><input type="checkbox" data-select-all></th>
                            <th style="width:75px">Slika</th>
                            <th style="min-width:260px">Artikl</th>
                            <th style="min-width:150px">Podaci</th>
                            <th class="text-right" style="min-width:145px">Cijena</th>
                            <th style="min-width:150px">Status</th>
                            <th class="text-right" style="width:130px">Akcije</th>
                        </tr>
                        </thead>
                        <tbody class="js-gallery">
                        @forelse($products as $source)
                            @php
                                $status = $source->ui_status;
                                $needsInspection = $source->is_current
                                    && ($source->check_status === 'error' || $source->checked_source_hash !== $source->source_hash);
                                $sourcePrice = (float) data_get($source, $importUi['source_price_field'], 0);
                                $sourceSalePrice = data_get($source, $importUi['source_sale_price_field']);
                                $regularEur = $importUi['uses_exchange_rate']
                                    ? $priceCalculator->convert($sourcePrice, $settings['exchange_rate'], $settings['markup_percent'])
                                    : round($sourcePrice * (1 + max(0, (float) $settings['markup_percent']) / 100), 2);
                                $saleEur = $sourceSalePrice
                                    ? ($importUi['uses_exchange_rate']
                                        ? $priceCalculator->convert((float) $sourceSalePrice, $settings['exchange_rate'], $settings['markup_percent'])
                                        : round((float) $sourceSalePrice * (1 + max(0, (float) $settings['markup_percent']) / 100), 2))
                                    : null;
                                $sourceAvailable = in_array(strtolower(trim((string) $source->availability)), [
                                    'in stock', 'in_stock', 'instock', 'available', 'onbackorder',
                                ], true);
                            @endphp
                            <tr data-source-row="{{ $source->id }}">
                                <td><input type="checkbox" value="{{ $source->id }}" data-source-checkbox {{ ! $source->is_current ? 'disabled' : '' }}></td>
                                <td>
                                    @if($source->image_url)
                                        <a class="img-link img-link-zoom-in img-lightbox"
                                           href="{{ $source->image_url }}"
                                           title="{{ $source->name }}">
                                            <img src="{{ $source->image_url }}" alt="Naslovnica knjige {{ $source->name }}" style="max-height:75px;max-width:60px" loading="lazy">
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <a class="font-w600" href="{{ $source->source_url }}" target="_blank" rel="noopener noreferrer">{{ $source->name }}</a>
                                    <div class="small text-muted">{{ $importUi['source_id_label'] }}: {{ data_get($source, $importUi['source_id_field']) ?: ($source->remote_product_id ?: $source->external_id) }}</div>
                                    @if($importUi['secondary_source_id_label'] && $source->nav_id && $source->remote_product_id)
                                        <div class="small text-muted">{{ $importUi['secondary_source_id_label'] }}: {{ $source->remote_product_id }}</div>
                                    @endif
                                    @if($importUi['supports_source_publisher_mapping'] && $source->source_publisher)
                                        <div class="small text-muted">Izvorni nakladnik: {{ $source->source_publisher }}</div>
                                    @endif
                                    @if($source->author)<div class="small">{{ $source->author }}</div>@endif
                                </td>
                                <td class="small">
                                    <div><span class="text-muted">ISBN:</span> {{ $source->isbn ?: 'nije provjeren' }}</div>
                                    @if($source->format)<div><span class="text-muted">Format:</span> {{ $source->format }}</div>@endif
                                    @if($source->pages)<div><span class="text-muted">Stranica:</span> {{ $source->pages }}</div>@endif
                                    @if($source->letter || $source->binding)<div>{{ $source->letter }}{{ $source->letter && $source->binding ? ' · ' : '' }}{{ $source->binding }}</div>@endif
                                    @if($source->publication_year)<div><span class="text-muted">Godina:</span> {{ $source->publication_year }}</div>@endif
                                    @if($importUi['supports_source_taxonomy_mapping'] && ! empty($source->source_genres))
                                        <div><span class="text-muted">{{ ucfirst($importUi['source_taxonomy_item_label']) }}:</span> {{ implode(' · ', (array) $source->source_genres) }}</div>
                                    @endif
                                </td>
                                <td class="text-right small">
                                    <div>{{ number_format($sourcePrice, 2, ',', '.') }} {{ $importUi['source_currency'] }}</div>
                                    <div class="font-w700">{{ number_format($regularEur, 2, ',', '.') }} EUR</div>
                                    @if($saleEur && $saleEur < $regularEur)
                                        <div class="text-danger">Akcija {{ number_format($saleEur, 2, ',', '.') }} EUR</div>
                                    @endif
                                    <div class="text-{{ $sourceAvailable ? 'success' : 'muted' }}">{{ $sourceAvailable ? 'Dostupno' : 'Nedostupno' }}</div>
                                </td>
                                <td data-source-status>
                                    <span class="badge badge-{{ $statusLabels[$status][1] ?? 'secondary' }}" data-source-status-badge>{{ $statusLabels[$status][0] ?? $status }}</span>
                                    <div class="small mt-1{{ $source->product ? '' : ' d-none' }}" data-source-product-link>
                                        @if($source->product)
                                            <a href="{{ route('products.edit', ['product' => $source->product]) }}">Zuzi #{{ $source->product->id }}</a>
                                        @endif
                                    </div>
                                    <div class="small text-muted mt-1{{ $source->check_message ? '' : ' d-none' }}" data-source-status-message>{{ $source->check_message }}</div>
                                </td>
                                <td class="text-right text-nowrap">
                                    <a class="btn btn-sm btn-alt-secondary" href="{{ $source->source_url }}" target="_blank" rel="noopener noreferrer" title="Otvori na {{ $importUi['source_site'] }}" aria-label="Otvori {{ $source->name }} na {{ $importUi['source_site'] }} u novom tabu"><i class="fa fa-external-link-alt"></i></a>
                                    @if($needsInspection)
                                        <button class="btn btn-sm btn-alt-primary" type="button" title="{{ $source->check_status === 'error' ? 'Ponovi provjeru' : 'Provjeri' }}" data-single-action="inspect" data-source-id="{{ $source->id }}"><i class="fa fa-search"></i></button>
                                    @endif
                                    <button class="btn btn-sm btn-alt-success" type="button" title="Uvezi" data-single-action="import" data-source-id="{{ $source->id }}"><i class="fa fa-download"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Nema zapisa. Najprije osvježite feed ili promijenite filtre.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $products->links() }}
            </div>
        </div>
            </div>

            <div class="tab-pane fade{{ $settingsTabActive ? ' show active' : '' }}" id="{{ $settingsTabId }}" role="tabpanel">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <div>
                            <h3 class="block-title">Postavke {{ $importUi['name'] }} importa</h3>
                            <div class="small text-muted mt-1">Ovdje se određuju cijene, zaliha i obavezno Zuzi mapiranje.</div>
                        </div>
                    </div>
                    <form action="{{ route($routePrefix . '.settings') }}" method="post">
                        @csrf
                        <div class="block-content">
                            <h4 class="font-size-h5 mb-1"><i class="fa fa-euro-sign text-primary mr-2"></i>Cijena i dostupnost</h4>
                            @if($importUi['uses_exchange_rate'])
                                <p class="text-muted small mb-4">Izvorna {{ $importUi['name'] }} cijena automatski se pretvara iz {{ $importUi['source_currency'] }} u EUR i zatim uvećava za zadani postotak.</p>
                            @else
                                <p class="text-muted small mb-4">Izvorna {{ $importUi['name'] }} cijena već je u EUR; na nju se primjenjuje samo zadano postotno uvećanje.</p>
                            @endif

                            <div class="form-row">
                                @if($importUi['uses_exchange_rate'])
                                    <div class="form-group col-md-6 col-xl-3">
                                        <label for="exchange-rate">{{ $importUi['source_currency'] }} za 1 EUR</label>
                                        <input id="exchange-rate" class="form-control" type="number" step="0.0001" min="0.0001" name="exchange_rate" value="{{ old('exchange_rate', $settings['exchange_rate']) }}" required>
                                        <small class="form-text text-muted">Primjer: 117,2</small>
                                    </div>
                                @endif
                                <div class="form-group col-md-6 {{ $importUi['uses_exchange_rate'] ? 'col-xl-3' : 'col-xl-4' }}">
                                    <label for="markup-percent">Uvećanje cijene</label>
                                    <div class="input-group">
                                        <input id="markup-percent" class="form-control" type="number" step="0.01" min="0" name="markup_percent" value="{{ old('markup_percent', $settings['markup_percent']) }}" required>
                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                    </div>
                                    <small class="form-text text-muted">{{ $importUi['uses_exchange_rate'] ? 'Dodaje se nakon konverzije.' : 'Dodaje se na izvornu EUR cijenu.' }}</small>
                                </div>
                                <div class="form-group col-md-6 {{ $importUi['uses_exchange_rate'] ? 'col-xl-3' : 'col-xl-4' }}">
                                    <label for="default-quantity">Količina kad je dostupno</label>
                                    <input id="default-quantity" class="form-control" type="number" min="0" name="default_quantity" value="{{ old('default_quantity', $settings['default_quantity']) }}" required>
                                    <small class="form-text text-muted">Za nedostupne knjige sprema se 0.</small>
                                </div>
                                <div class="form-group col-md-6 {{ $importUi['uses_exchange_rate'] ? 'col-xl-3' : 'col-xl-4' }}">
                                    <label for="existing-action">Ako artikl već postoji</label>
                                    <select id="existing-action" class="form-control" name="existing_action">
                                        <option value="skip" {{ old('existing_action', $settings['existing_action']) === 'skip' ? 'selected' : '' }}>Ne mijenjaj cijenu ni zalihu</option>
                                        <option value="price_stock" {{ old('existing_action', $settings['existing_action']) === 'price_stock' ? 'selected' : '' }}>Ažuriraj cijenu i zalihu</option>
                                    </select>
                                    <small class="form-text text-muted">Kategorije se uvijek dopunjuju.</small>
                                </div>
                            </div>

                            <div class="alert alert-info d-flex align-items-center mb-4" role="status">
                                <i class="fa fa-calculator mr-3"></i>
                                <div>Primjer izračuna: <strong>{{ number_format($importUi['price_preview_source_amount'], 2, ',', '.') }} {{ $importUi['source_currency'] }}</strong> → <strong data-price-preview>—</strong> EUR</div>
                            </div>

                            <hr class="my-4">

                            <h4 class="font-size-h5 mb-1"><i class="fa fa-project-diagram text-primary mr-2"></i>Obavezno mapiranje</h4>
                            <p class="text-muted small mb-4">Svaka uvezena knjiga dobiva obje kategorije i nakladnika. Dodatna kategorija bira se na tabu Knjige.</p>

                            <div class="form-row">
                                <div class="form-group col-lg-4">
                                    <label for="publisher-parent-category">Glavna kategorija</label>
                                    <select id="publisher-parent-category" class="js-select2 form-control" name="publisher_parent_category_id" required style="width:100%">
                                        <option value="">Odaberi glavnu kategoriju</option>
                                        @foreach($categories as $group => $groupCategories)
                                            @foreach($groupCategories as $categoryId => $category)
                                                <option value="{{ $categoryId }}" {{ (int) old('publisher_parent_category_id', $settings['publisher_parent_category_id']) === (int) $categoryId ? 'selected' : '' }}>{{ $group }} › {{ $category['title'] }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">Zadano: Nakladnici</small>
                                </div>
                                <div class="form-group col-lg-4">
                                    <label for="publisher-category">{{ $importUi['publisher_category_label'] }}</label>
                                    <select id="publisher-category" class="js-select2 form-control" name="publisher_category_id" required style="width:100%">
                                        <option value="">Odaberi podkategoriju</option>
                                        @foreach($categories as $group => $groupCategories)
                                            @foreach($groupCategories as $categoryId => $category)
                                                @foreach($category['subs'] ?? [] as $subcategoryId => $subcategory)
                                                    <option value="{{ $subcategoryId }}" data-parent-category="{{ $categoryId }}" {{ (int) old('publisher_category_id', $settings['publisher_category_id']) === (int) $subcategoryId ? 'selected' : '' }}>{{ $group }} › {{ $category['title'] }} › {{ $subcategory['title'] }}</option>
                                                @endforeach
                                            @endforeach
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">Mora pripadati odabranoj glavnoj kategoriji.</small>
                                </div>
                                <div class="form-group col-lg-4">
                                    <label for="publisher-id">Nakladnik</label>
                                    <select id="publisher-id" class="js-select2 form-control" name="publisher_id" required style="width:100%">
                                        <option value="">Odaberi nakladnika</option>
                                        @foreach($publishers as $publisher)
                                            <option value="{{ $publisher->id }}" {{ (int) old('publisher_id', $settings['publisher_id']) === (int) $publisher->id ? 'selected' : '' }}>{{ $publisher->title }}</option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">Zadano: {{ $importUi['default_publisher_label'] }}</small>
                                </div>
                            </div>

                            @if($importUi['supports_source_publisher_mapping'])
                                <div class="bg-body-light rounded p-3 mb-4">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="map-source-publishers" name="map_source_publishers" value="1" {{ old('map_source_publishers', $settings['map_source_publishers'] ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-w600" for="map-source-publishers">Mapiraj nakladnika iz {{ $importUi['name'] }} podataka</label>
                                    </div>
                                    <div class="small text-muted mt-1 ml-4">Ako u Zuzi postoji nakladnik i njegova podkategorija istog naziva, koriste se automatski. Odabrani nakladnik i podkategorija iznad služe kao sigurna rezerva.</div>
                                </div>

                            @endif

                            @if($importUi['supports_source_taxonomy_mapping'])
                                <hr class="my-4">

                                <h4 class="font-size-h5 mb-1"><i class="fa fa-sitemap text-primary mr-2"></i>Mapiranje {{ $importUi['name'] }} {{ $importUi['source_taxonomy_items_label'] }}</h4>
                                <p class="text-muted small mb-3">Popis dolazi iz {{ $importUi['name'] }} kategorija, a broj uz vrijednost pokazuje koliko ju je provjerenih knjiga koristilo. Nove vrijednosti pojavit će se automatski; nemapirana vrijednost ne zaustavlja uvoz.</p>

                                @if($sourceGenres->isEmpty() && empty($genreCategoryMap))
                                    <div class="alert alert-light border mb-4">Još nema otkrivenih {{ $importUi['source_taxonomy_items_label'] }}. Osvježite feed ili provjerite nekoliko knjiga pa se vratite u postavke.</div>
                                @else
                                    <div class="form-row align-items-end mb-3">
                                        <div class="form-group col-lg-5 mb-2">
                                            <label for="source-genre-picker">{{ $importUi['name'] }} {{ $importUi['source_taxonomy_item_label'] }}</label>
                                            <select id="source-genre-picker" class="form-control" style="width:100%">
                                                <option value="">Odaberi {{ $importUi['source_taxonomy_item_label'] }}</option>
                                                @foreach($sourceGenres as $genre => $count)
                                                    <option value="{{ $genre }}">{{ $genre }}{{ $count > 0 ? ' · ' . $count . ' provjerenih' : '' }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-lg-5 mb-2">
                                            <label for="genre-category-picker">Zuzi kategorija</label>
                                            <select id="genre-category-picker" class="form-control" style="width:100%">
                                                <option value="">Odaberi Zuzi kategoriju</option>
                                                @foreach($categories as $group => $groupCategories)
                                                    @foreach($groupCategories as $categoryId => $category)
                                                        <option value="{{ $categoryId }}">{{ $category['title'] }}</option>
                                                        @foreach($category['subs'] ?? [] as $subcategoryId => $subcategory)
                                                            <option value="{{ $subcategoryId }}">↳ {{ $category['title'] }} › {{ $subcategory['title'] }}</option>
                                                        @endforeach
                                                    @endforeach
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-lg-2 mb-2">
                                            <button class="btn btn-alt-primary btn-block" type="button" data-add-genre-mapping>
                                                <i class="fa fa-plus mr-1"></i> Dodaj
                                            </button>
                                        </div>
                                    </div>

                                    <div class="table-responsive mb-4{{ empty($genreCategoryMap) ? ' d-none' : '' }}" data-genre-mappings-wrap>
                                        <table class="table table-sm table-vcenter mb-0">
                                            <thead>
                                            <tr>
                                                <th>{{ $importUi['name'] }} {{ $importUi['source_taxonomy_item_label'] }}</th>
                                                <th style="width:55%">Zuzi kategorija</th>
                                                <th class="text-right" style="width:55px"></th>
                                            </tr>
                                            </thead>
                                            <tbody data-genre-mappings>
                                            @foreach($genreCategoryMap as $genre => $mappedCategoryId)
                                                <tr data-genre-key="{{ mb_strtolower(trim($genre)) }}">
                                                    <td>
                                                        <input type="hidden" name="source_genres[]" value="{{ $genre }}">
                                                        <span class="font-w600">{{ $genre }}</span>
                                                        @if(($sourceGenres[$genre] ?? 0) > 0)
                                                            <span class="badge badge-light ml-1">{{ $sourceGenres[$genre] }}</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <select class="form-control" name="genre_category_ids[]" style="width:100%">
                                                            <option value="">Bez dodatnog mapiranja</option>
                                                            @foreach($categories as $group => $groupCategories)
                                                                @foreach($groupCategories as $categoryId => $category)
                                                                    <option value="{{ $categoryId }}" {{ (int) $mappedCategoryId === (int) $categoryId ? 'selected' : '' }}>{{ $category['title'] }}</option>
                                                                    @foreach($category['subs'] ?? [] as $subcategoryId => $subcategory)
                                                                        <option value="{{ $subcategoryId }}" {{ (int) $mappedCategoryId === (int) $subcategoryId ? 'selected' : '' }}>↳ {{ $category['title'] }} › {{ $subcategory['title'] }}</option>
                                                                    @endforeach
                                                                @endforeach
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td class="text-right">
                                                        <button class="btn btn-sm btn-alt-danger" type="button" title="Ukloni mapiranje" data-remove-genre-mapping><i class="fa fa-times"></i></button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            @endif

                            <div class="bg-body-light rounded p-3 mb-3">
                                @if($importUi['supports_translation'])
                                    <input type="hidden" name="translate_descriptions" value="0">
                                    <div class="custom-control custom-switch mb-3">
                                        <input type="checkbox" class="custom-control-input" id="{{ $importUi['slug'] }}-translate-descriptions" name="translate_descriptions" value="1" {{ old('translate_descriptions', $settings['translate_descriptions'] ?? false) ? 'checked' : '' }}>
                                        <label class="custom-control-label font-w600" for="{{ $importUi['slug'] }}-translate-descriptions">Prevedi opis na hrvatski</label>
                                        <div class="small text-muted mt-1">Zadano je isključeno. Koristi se besplatni servis bez API ključa; ako nije dostupan, uvoz nastavlja s izvornim opisom i prikazuje upozorenje.</div>
                                    </div>
                                    <hr class="my-3">
                                @endif
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="activate-new" name="activate_new_products" value="1" {{ old('activate_new_products', $settings['activate_new_products']) ? 'checked' : '' }}>
                                    <label class="custom-control-label font-w600" for="activate-new">Odmah aktiviraj nove artikle</label>
                                </div>
                                <div class="small text-muted mt-1 ml-4">Ako je isključeno, nove knjige spremaju se neaktivne za pregled prije objave.</div>
                            </div>
                        </div>
                        <div class="block-content block-content-full block-content-sm bg-body-light d-flex justify-content-between align-items-center">
                            <span class="small text-muted"><i class="fa fa-info-circle mr-1"></i> Postavke vrijede za sve sljedeće importe.</span>
                            <button class="btn btn-primary" type="submit"><i class="fa fa-save mr-1"></i> Spremi postavke</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/magnific-popup/jquery.magnific-popup.min.js') }}"></script>
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        jQuery(function ($) {
            Dashmix.helpers('magnific-popup');
            const publisherCategory = $('#publisher-category');
            const publisherCategoryOptions = publisherCategory.find('option').clone();
            const batchCategory = $('#batch-category');

            function renderBatchCategory(item) {
                if (!item.id || !item.element) {
                    return item.text;
                }

                const option = item.element;
                const result = $('<div class="laguna-category-option"></div>');
                if (option.dataset.categoryParent) {
                    result.addClass('laguna-category-option--child');
                }
                $('<div class="laguna-category-option__title"></div>')
                    .text(option.dataset.categoryTitle || item.text)
                    .appendTo(result);
                $('<div class="laguna-category-option__meta"></div>')
                    .text(option.dataset.categoryParent
                        ? `Podkategorija · ${option.dataset.categoryParent}`
                        : 'Glavna kategorija')
                    .appendTo(result);

                return result;
            }

            function renderSelectedBatchCategory(item) {
                return item.element?.dataset.categoryPath || item.text;
            }

            function filterPublisherCategories() {
                const parentId = String($('#publisher-parent-category').val() || '');
                const selectedId = String(publisherCategory.val() || '');
                publisherCategory.empty();

                publisherCategoryOptions.each(function () {
                    const option = $(this);
                    const optionParentId = String(option.attr('data-parent-category') || '');
                    if (!option.val() || optionParentId === parentId) {
                        publisherCategory.append(option.clone());
                    }
                });

                publisherCategory.val(publisherCategory.find(`option[value="${selectedId}"]`).length ? selectedId : '');
                publisherCategory.trigger('change.select2');
            }

            filterPublisherCategories();
            $('.js-select2').select2({ width: '100%' });
            batchCategory.select2({
                width: '100%',
                placeholder: 'Bez dodatne kategorije',
                allowClear: true,
                minimumResultsForSearch: 0,
                dropdownCssClass: 'laguna-category-dropdown',
                templateResult: renderBatchCategory,
                templateSelection: renderSelectedBatchCategory,
                language: {
                    noResults: function () { return 'Nema kategorije s tim nazivom'; },
                    searching: function () { return 'Pretražujem…'; }
                }
            });
            batchCategory.on('select2:open', function () {
                window.setTimeout(function () {
                    document.querySelector('.select2-container--open .select2-search__field')
                        ?.setAttribute('placeholder', 'Upišite naziv kategorije…');
                });
            });

            const sourceGenrePicker = $('#source-genre-picker');
            const genreCategoryPicker = $('#genre-category-picker');
            const genreMappings = document.querySelector('[data-genre-mappings]');
            const genreMappingsWrap = document.querySelector('[data-genre-mappings-wrap]');

            function initializeGenreCategorySelect(select) {
                $(select).select2({ width: '100%' });
            }

            if (sourceGenrePicker.length) {
                sourceGenrePicker.select2({
                    width: '100%',
                    placeholder: @json('Pretražite ' . $importUi['name'] . ' ' . $importUi['source_taxonomy_item_label']),
                    allowClear: true
                });
                genreCategoryPicker.select2({
                    width: '100%',
                    placeholder: 'Pretražite Zuzi kategoriju',
                    allowClear: true
                });
                genreMappings?.querySelectorAll('select[name="genre_category_ids[]"]')
                    .forEach(initializeGenreCategorySelect);
            }

            function refreshGenreMappingsVisibility() {
                genreMappingsWrap?.classList.toggle('d-none', !genreMappings?.children.length);
            }

            document.querySelector('[data-add-genre-mapping]')?.addEventListener('click', function () {
                const genre = String(sourceGenrePicker.val() || '').trim();
                const categoryId = String(genreCategoryPicker.val() || '');
                if (!genre || !categoryId || !genreMappings) {
                    return;
                }

                const key = genre.toLocaleLowerCase('hr-HR');
                const existing = Array.from(genreMappings.children)
                    .find(row => row.dataset.genreKey === key);
                if (existing) {
                    $(existing).find('select[name="genre_category_ids[]"]')
                        .val(categoryId)
                        .trigger('change');
                } else {
                    const row = document.createElement('tr');
                    row.dataset.genreKey = key;

                    const genreCell = document.createElement('td');
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'source_genres[]';
                    hidden.value = genre;
                    const label = document.createElement('span');
                    label.className = 'font-w600';
                    label.textContent = genre;
                    genreCell.append(hidden, label);

                    const categoryCell = document.createElement('td');
                    const select = document.createElement('select');
                    select.className = 'form-control';
                    select.name = 'genre_category_ids[]';
                    select.style.width = '100%';
                    genreCategoryPicker.find('option').clone().appendTo(select);
                    select.value = categoryId;
                    categoryCell.appendChild(select);

                    const actionCell = document.createElement('td');
                    actionCell.className = 'text-right';
                    actionCell.innerHTML = '<button class="btn btn-sm btn-alt-danger" type="button" title="Ukloni mapiranje" data-remove-genre-mapping><i class="fa fa-times"></i></button>';
                    row.append(genreCell, categoryCell, actionCell);
                    genreMappings.appendChild(row);
                    initializeGenreCategorySelect(select);
                }

                sourceGenrePicker.val('').trigger('change');
                genreCategoryPicker.val('').trigger('change');
                refreshGenreMappingsVisibility();
            });

            genreMappings?.addEventListener('click', function (event) {
                const button = event.target.closest('[data-remove-genre-mapping]');
                if (!button) {
                    return;
                }
                $(button.closest('tr')).find('select').select2('destroy');
                button.closest('tr').remove();
                refreshGenreMappingsVisibility();
            });
            $('#publisher-parent-category').on('change', filterPublisherCategories);

            const sourceCategoryFilter = $('#source-category');
            const sourceGenreFilter = $('#source-genre');
            const sourceGenreOptions = sourceGenreFilter.find('option').clone();

            function filterSourceGenres() {
                if (!sourceCategoryFilter.length || !sourceGenreFilter.length) {
                    return;
                }

                const category = String(sourceCategoryFilter.val() || '');
                const currentGenre = String(sourceGenreFilter.val() || '');
                sourceGenreFilter.empty();
                sourceGenreOptions.each(function () {
                    const optionCategory = String($(this).data('source-category') || '');
                    if (!this.value || !category || optionCategory === category) {
                        sourceGenreFilter.append($(this).clone());
                    }
                });
                sourceGenreFilter.val(
                    sourceGenreFilter.find('option').filter(function () { return this.value === currentGenre; }).length
                        ? currentGenre
                        : ''
                );
            }

            sourceCategoryFilter.on('change', filterSourceGenres);
            sourceGenreFilter.on('change', function () {
                const optionCategory = String($(this).find('option:selected').data('source-category') || '');
                if (!sourceCategoryFilter.val() && optionCategory) {
                    sourceCategoryFilter.val(optionCategory);
                    filterSourceGenres();
                }
            });
            filterSourceGenres();

            const settingsTabSelector = @json('#' . $settingsTabId);
            if (window.location.hash === settingsTabSelector) {
                $(`a[href="${settingsTabSelector}"]`).tab('show');
            }
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (event) {
                window.history.replaceState(null, '', event.target.hash);
            });

            function updatePricePreview() {
                const rate = Number(String($('#exchange-rate').val() || '').replace(',', '.'));
                const markup = Number(String($('#markup-percent').val() || '').replace(',', '.'));
                const sourceAmount = Number(@json($importUi['price_preview_source_amount']));
                const usesExchangeRate = Boolean(@json($importUi['uses_exchange_rate']));
                const converted = usesExchangeRate
                    ? (rate > 0 ? (sourceAmount / rate) * (1 + Math.max(0, markup) / 100) : 0)
                    : sourceAmount * (1 + Math.max(0, markup) / 100);
                $('[data-price-preview]').text(converted > 0
                    ? converted.toLocaleString('hr-HR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : '—');
            }
            $('#exchange-rate, #markup-percent').on('input change', updatePricePreview);
            updatePricePreview();

            const csrf = @json(csrf_token());
            const supportsBatchedRefresh = Boolean(@json($importUi['supports_batched_refresh']));
            const feedRefreshStartEndpoint = @json($feedRefreshStartEndpoint);
            const feedRefreshStepEndpoint = @json($feedRefreshStepEndpoint);
            const feedRefreshStorageKey = @json($importUi['slug'] . '-import-refresh-token');
            const feedRefreshState = document.querySelector('[data-feed-refresh-state]');
            const feedRefreshMessage = document.querySelector('[data-feed-refresh-message]');
            const feedRefreshPages = document.querySelector('[data-feed-refresh-pages]');
            const feedRefreshProgress = document.querySelector('[data-feed-refresh-progress]');
            const progressText = document.querySelector('[data-progress]');
            const progressWrap = document.querySelector('[data-progress-bar-wrap]');
            const progressBar = document.querySelector('[data-progress-bar]');
            const selectedCount = document.querySelector('[data-selected-count]');
            const inspectAllButton = document.querySelector('[data-inspect-all]');
            const inspectAllIcon = document.querySelector('[data-inspect-all-icon]');
            const inspectAllLabel = document.querySelector('[data-inspect-all-label]');
            const inspectAllCount = document.querySelector('[data-inspect-all-count]');
            const inspectAllProgressWrap = document.querySelector('[data-inspect-all-progress-wrap]');
            const inspectAllProgress = document.querySelector('[data-inspect-all-progress]');
            const inspectAllProgressBar = document.querySelector('[data-inspect-all-progress-bar]');
            const inspectionQueueEndpoint = @json(route($routePrefix . '.inspection-queue'));
            const bulkInspectionEndpoint = @json($importUi['bulk_inspection_route'] ? route($importUi['bulk_inspection_route']) : null);
            const sourceStatusLabels = @json($statusLabels);
            const endpointTemplates = {
                inspect: @json(route($routePrefix . '.inspect', [$importUi['route_parameter'] => '__ID__'])),
                import: @json(route($routePrefix . '.import', [$importUi['route_parameter'] => '__ID__']))
            };
            let inspectAllRunning = false;
            let inspectAllStopRequested = false;
            let inspectAllBulkResetRequested = false;
            let feedRefreshRunning = false;

            function storedFeedRefreshToken() {
                try {
                    const stored = window.localStorage.getItem(feedRefreshStorageKey) || '';
                    return stored || (new URL(window.location.href)).searchParams.get('refresh_token') || '';
                } catch (error) {
                    try {
                        return (new URL(window.location.href)).searchParams.get('refresh_token') || '';
                    } catch (urlError) {
                        return '';
                    }
                }
            }

            function storeFeedRefreshToken(token) {
                try {
                    if (token) {
                        window.localStorage.setItem(feedRefreshStorageKey, token);
                    } else {
                        window.localStorage.removeItem(feedRefreshStorageKey);
                    }
                } catch (error) {
                    // Preuzimanje radi i kad preglednik ne dopušta localStorage.
                }
            }

            function removeFeedRefreshTokenFromUrl() {
                try {
                    const cleanUrl = new URL(window.location.href);
                    cleanUrl.searchParams.delete('refresh_token');
                    window.history.replaceState(null, '', cleanUrl.toString());
                } catch (error) {
                    // URL ostaje nepromijenjen samo u vrlo starom pregledniku.
                }
            }

            async function postFeedRefresh(endpoint, values = {}) {
                const body = new URLSearchParams();
                Object.entries(values).forEach(([key, value]) => body.set(key, String(value)));
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });
                const text = await response.text();
                let payload = {};
                try {
                    payload = text ? JSON.parse(text) : {};
                } catch (error) {
                    payload = {};
                }
                if (!response.ok || payload.success === false) {
                    const exception = new Error(payload.message || 'Osvježavanje feeda privremeno nije uspjelo.');
                    exception.status = response.status;
                    throw exception;
                }

                return payload;
            }

            function renderFeedRefresh(payload = {}) {
                if (!feedRefreshState) {
                    return;
                }
                const processed = Math.max(0, Number(payload.processed_pages || 0));
                const total = Math.max(processed, Number(payload.total_pages || 0));
                const percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
                const staged = Math.max(0, Number(payload.staged || 0));
                feedRefreshState.classList.remove('d-none', 'alert-danger', 'alert-success');
                feedRefreshState.classList.add(payload.done ? 'alert-success' : 'alert-info');
                if (feedRefreshMessage) {
                    feedRefreshMessage.textContent = payload.message
                        || (payload.done ? 'Znanje feed je uspješno osvježen.' : 'Preuzimam dostupne Znanje knjige…');
                }
                if (feedRefreshPages) {
                    feedRefreshPages.textContent = total > 0
                        ? `${processed.toLocaleString('hr-HR')} / ${total.toLocaleString('hr-HR')} stranica · ${staged.toLocaleString('hr-HR')} knjiga`
                        : `${staged.toLocaleString('hr-HR')} knjiga`;
                }
                if (feedRefreshProgress) {
                    feedRefreshProgress.style.width = `${payload.done ? 100 : percent}%`;
                    feedRefreshProgress.setAttribute('aria-valuenow', String(payload.done ? 100 : percent));
                    feedRefreshProgress.classList.toggle('progress-bar-animated', !payload.done);
                }
            }

            async function runBatchedFeedRefresh(resumeToken = '') {
                if (feedRefreshRunning || !feedRefreshStartEndpoint || !feedRefreshStepEndpoint) {
                    return;
                }
                const form = document.querySelector('[data-refresh-form]');
                const button = form?.querySelector('button');
                feedRefreshRunning = true;
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Preuzimam feed…';
                }

                let token = resumeToken;
                try {
                    let payload;
                    if (!token) {
                        payload = await postFeedRefresh(feedRefreshStartEndpoint);
                        token = String(payload.token || '');
                        if (!token) {
                            throw new Error('Znanje osvježavanje nije vratilo identifikator postupka.');
                        }
                        storeFeedRefreshToken(token);
                        renderFeedRefresh(payload);
                    }

                    do {
                        payload = await postFeedRefresh(feedRefreshStepEndpoint, { token });
                        renderFeedRefresh(payload);
                        if (!payload.done) {
                            await new Promise(resolve => window.setTimeout(resolve, 125));
                        }
                    } while (!payload.done);

                    storeFeedRefreshToken('');
                    if (button) {
                        button.innerHTML = '<i class="fa fa-check mr-1"></i> Feed je osvježen';
                    }
                    window.setTimeout(() => {
                        const cleanUrl = new URL(window.location.href);
                        cleanUrl.searchParams.delete('refresh_token');
                        window.location.assign(cleanUrl.toString());
                    }, 900);
                } catch (error) {
                    if (Number(error.status) === 404 || Number(error.status) === 410) {
                        storeFeedRefreshToken('');
                        removeFeedRefreshTokenFromUrl();
                    }
                    if (feedRefreshState) {
                        feedRefreshState.classList.remove('d-none', 'alert-info', 'alert-success');
                        feedRefreshState.classList.add('alert-danger');
                    }
                    if (feedRefreshMessage) {
                        feedRefreshMessage.textContent = error.message || 'Osvježavanje feeda je prekinuto.';
                    }
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = '<i class="fa fa-redo mr-1"></i> Nastavi osvježavanje';
                    }
                } finally {
                    feedRefreshRunning = false;
                }
            }

            const selectedIds = () => Array.from(document.querySelectorAll('[data-source-checkbox]:checked')).map(input => input.value);
            const endpoint = (action, id) => endpointTemplates[action].replace('__ID__', id);

            function applySourceResult(row, payload) {
                if (!row || !payload) {
                    return;
                }

                if (payload.status && sourceStatusLabels[payload.status]) {
                    const badge = row.querySelector('[data-source-status-badge]');
                    const [label, color] = sourceStatusLabels[payload.status];
                    if (badge) {
                        badge.className = `badge badge-${color || 'secondary'}`;
                        badge.textContent = label || payload.status;
                    }
                }

                if (Object.prototype.hasOwnProperty.call(payload, 'product_id')) {
                    const productLink = row.querySelector('[data-source-product-link]');
                    if (productLink) {
                        if (payload.product_id) {
                            let anchor = productLink.querySelector('a');
                            if (!anchor) {
                                anchor = document.createElement('a');
                                productLink.appendChild(anchor);
                            }
                            anchor.href = payload.product_url || '#';
                            anchor.textContent = `Zuzi #${payload.product_id}`;
                            productLink.classList.remove('d-none');
                        } else {
                            productLink.textContent = '';
                            productLink.classList.add('d-none');
                        }
                    }
                }

                const statusMessage = typeof payload.check_message === 'string'
                    ? payload.check_message
                    : payload.message;
                if (typeof statusMessage === 'string' && statusMessage.trim() !== '') {
                    const message = row.querySelector('[data-source-status-message]');
                    if (message) {
                        message.textContent = statusMessage.trim();
                        message.classList.remove('d-none');
                    }
                }
            }

            function updateSelectionState() {
                const ids = selectedIds();
                selectedCount.textContent = ids.length;
                document.querySelectorAll('[data-run-action]').forEach(button => button.disabled = inspectAllRunning || ids.length === 0);
                document.querySelectorAll('[data-source-checkbox]').forEach(input => {
                    input.closest('tr')?.classList.toggle('table-active', input.checked);
                });
            }

            function setInspectAllCount(count) {
                const normalized = Math.max(0, Number(count) || 0);
                inspectAllCount.dataset.count = String(normalized);
                inspectAllCount.textContent = normalized.toLocaleString('hr-HR');
            }

            function setInspectAllRunning(running) {
                inspectAllRunning = running;
                inspectAllButton.classList.toggle('btn-alt-primary', !running);
                inspectAllButton.classList.toggle('btn-alt-danger', running);
                inspectAllIcon.className = running ? 'fa fa-stop mr-1' : 'fa fa-search mr-1';
                inspectAllLabel.textContent = running ? 'Zaustavi provjeru' : 'Provjeri sve neprovjerene';
            }

            async function loadInspectionQueue(limit = 20, cursor = null, includeCount = false) {
                const query = new URLSearchParams({ limit: String(limit) });
                if (cursor) {
                    query.set('cursor', cursor);
                }
                if (includeCount) {
                    query.set('include_count', '1');
                }
                const response = await fetch(`${inspectionQueueEndpoint}?${query.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const payload = await response.json();
                if (!response.ok || !Array.isArray(payload.items)) {
                    throw new Error(payload.message || 'Red za provjeru nije moguće učitati.');
                }

                return payload;
            }

            async function inspectQueuedItem(item) {
                const response = await fetch(`${endpoint('inspect', item.id)}?only_if_pending=1`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const payload = await response.json();

                if (response.ok && payload.success) {
                    return payload;
                }

                // Inspect service stores a 422 as a checked error. Other failures are
                // retryable and must stop this run so the same row cannot loop forever.
                if (response.status === 422) {
                    return false;
                }

                throw new Error(payload.message || 'Provjera je privremeno nedostupna.');
            }

            async function inspectQueueBatch(items, state) {
                let nextIndex = 0;
                const worker = async function () {
                    while (!inspectAllStopRequested) {
                        const itemIndex = nextIndex++;
                        if (itemIndex >= items.length) {
                            return;
                        }

                        const item = items[itemIndex];
                        state.started++;
                        inspectAllProgress.textContent = `Provjeravam ${state.started.toLocaleString('hr-HR')} / ${state.total.toLocaleString('hr-HR')}: ${item.name}`;

                        let result;
                        try {
                            result = await inspectQueuedItem(item);
                        } catch (error) {
                            state.networkError = true;
                            state.errorMessage = error?.message || 'Provjera je privremeno nedostupna.';
                            inspectAllStopRequested = true;
                            return;
                        }

                        const succeeded = result !== false;
                        state.processed++;
                        state[succeeded ? 'succeeded' : 'failed']++;
                        const row = document.querySelector(`[data-source-row="${item.id}"]`);
                        row?.classList.remove(succeeded ? 'table-danger' : 'table-success');
                        row?.classList.add(succeeded ? 'table-success' : 'table-danger');
                        if (succeeded) {
                            applySourceResult(row, result);
                            row?.querySelector('[data-single-action="inspect"]')?.remove();
                        }
                        inspectAllProgressBar.style.width = `${Math.round((state.processed / state.total) * 100)}%`;
                    }
                };

                const workerCount = Math.max(1, Number(@json($importUi['inspection_workers'])) || 1);
                await Promise.all(Array.from({ length: Math.min(workerCount, items.length) }, worker));
            }

            function showInspectAllSummary(message) {
                inspectAllProgress.textContent = message + ' ';
                const reload = document.createElement('button');
                reload.className = 'btn btn-sm btn-link p-0 ml-1';
                reload.type = 'button';
                reload.textContent = 'Osvježi rezultate';
                reload.addEventListener('click', () => window.location.reload());
                inspectAllProgress.appendChild(reload);
            }

            async function inspectAllPendingLegacy() {
                inspectAllStopRequested = false;
                setInspectAllRunning(true);
                inspectAllButton.disabled = false;
                document.querySelectorAll('[data-run-action], [data-single-action]').forEach(button => button.disabled = true);
                inspectAllProgressWrap.classList.remove('d-none');
                inspectAllProgressBar.classList.add('progress-bar-animated');
                inspectAllProgressBar.style.width = '0';

                const state = {
                    total: 0,
                    started: 0,
                    processed: 0,
                    succeeded: 0,
                    failed: 0,
                    networkError: false,
                    errorMessage: '',
                    remaining: Number(inspectAllCount.dataset.count || 0),
                    cursor: null
                };

                try {
                    let queue = await loadInspectionQueue(20, null, true);
                    state.remaining = Number(queue.remaining || 0);
                    state.total = state.remaining;
                    setInspectAllCount(state.remaining);

                    while (!inspectAllStopRequested) {
                        if (!queue.items.length) {
                            break;
                        }

                        await inspectQueueBatch(queue.items, state);
                        if (!inspectAllStopRequested) {
                            state.cursor = queue.next_cursor || null;
                            state.remaining = Math.max(0, state.total - state.processed);
                            setInspectAllCount(state.remaining);
                            if (queue.has_more === false) {
                                break;
                            }
                            await new Promise(resolve => window.setTimeout(resolve, Number(@json($importUi['inspection_delay_ms'])) || 250));
                            queue = await loadInspectionQueue(20, state.cursor, false);
                        }
                    }
                } catch (error) {
                    state.networkError = true;
                    state.errorMessage = error?.message || 'Red za provjeru je privremeno nedostupan.';
                }

                try {
                    const latestQueue = await loadInspectionQueue(1, null, true);
                    state.remaining = Number(latestQueue.remaining || 0);
                    setInspectAllCount(state.remaining);
                } catch (error) {
                    state.remaining = Math.max(0, state.total - state.processed);
                    setInspectAllCount(state.remaining);
                }

                inspectAllProgressBar.classList.remove('progress-bar-animated');
                if (!state.networkError && !inspectAllStopRequested && state.remaining === 0) {
                    inspectAllProgressBar.style.width = '100%';
                    showInspectAllSummary(`Provjera je završena: ${state.succeeded.toLocaleString('hr-HR')} uspješno, ${state.failed.toLocaleString('hr-HR')} s greškom.`);
                } else if (state.networkError) {
                    const reason = state.errorMessage ? ` Razlog: ${state.errorMessage}` : '';
                    showInspectAllSummary(`Provjera je privremeno prekinuta nakon ${state.processed.toLocaleString('hr-HR')} knjiga.${reason} Ponovnim pokretanjem nastavlja se od preostalih.`);
                } else {
                    showInspectAllSummary(`Provjera je zaustavljena nakon ${state.processed.toLocaleString('hr-HR')} knjiga. Preostalo: ${state.remaining.toLocaleString('hr-HR')}.`);
                }

                setInspectAllRunning(false);
                inspectAllButton.disabled = state.remaining === 0;
                document.querySelectorAll('[data-single-action]').forEach(button => button.disabled = false);
                updateSelectionState();
            }

            async function fetchBulkInspectionPage(reset = false) {
                const response = await fetch(bulkInspectionEndpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        limit: Math.min(100, Math.max(1, Number(@json($importUi['bulk_inspection_limit'])) || 100)),
                        reset
                    })
                });

                let payload = {};
                try {
                    payload = await response.json();
                } catch (error) {
                    // A proxy can return an HTML error page. Surface a useful
                    // message and leave the server-owned cursor untouched.
                }

                if (!response.ok || (!payload.success && !payload.done && !payload.incomplete)) {
                    const retryAfter = response.headers.get('Retry-After');
                    const retryHint = retryAfter
                        ? ` Pokušajte ponovno za ${Number(retryAfter).toLocaleString('hr-HR')} s.`
                        : '';
                    throw new Error((payload.message || `Bulk provjera nije dostupna (HTTP ${response.status}).`) + retryHint);
                }

                return payload;
            }

            function updateBulkInspectionProgress(state, payload) {
                state.processed += Math.max(0, Number(payload.processed) || 0);
                state.succeeded += Math.max(0, Number(payload.succeeded) || 0);
                state.failed += Math.max(0, Number(payload.failed) || 0);
                state.ignored += Math.max(0, Number(payload.ignored) || 0);
                state.remaining = Math.max(0, Number(payload.remaining) || 0);
                state.processedTotal = Math.max(state.processedTotal, Number(payload.processed_total) || 0);
                const hasCumulativeTotals = ['cumulative_succeeded', 'cumulative_failed', 'cumulative_ignored', 'succeeded_total', 'failed_total', 'ignored_total']
                    .some(key => Object.prototype.hasOwnProperty.call(payload, key));
                state.hasCumulativeTotals = state.hasCumulativeTotals || hasCumulativeTotals;
                state.succeededTotal = Math.max(state.succeededTotal, Number(payload.cumulative_succeeded ?? payload.succeeded_total) || 0);
                state.failedTotal = Math.max(state.failedTotal, Number(payload.cumulative_failed ?? payload.failed_total) || 0);
                state.ignoredTotal = Math.max(state.ignoredTotal, Number(payload.cumulative_ignored ?? payload.ignored_total) || 0);
                const previousPass = state.pass;
                state.pass = Math.max(1, Number(payload.pass) || state.pass);
                state.scanTotal = Math.max(state.scanTotal, Number(payload.records_total ?? payload.scan_total) || 0);
                const scanned = Math.max(0, Number(payload.scanned ?? payload.scan_processed) || 0);
                state.scanned = state.pass !== previousPass ? scanned : Math.max(state.scanned, scanned);
                state.done = Boolean(payload.done);
                state.incomplete = Boolean(payload.incomplete);
                state.canReset = Boolean(payload.can_reset);
                state.message = payload.message || '';
                setInspectAllCount(state.remaining);

                const inspected = state.processedTotal || state.processed;
                const scanText = state.scanTotal > 0
                    ? `Skenirano ${state.scanned.toLocaleString('hr-HR')} / ${state.scanTotal.toLocaleString('hr-HR')} · `
                    : '';
                inspectAllProgress.textContent = `${scanText}provjereno ${inspected.toLocaleString('hr-HR')} · preostalo ${state.remaining.toLocaleString('hr-HR')} · prolaz ${state.pass}`;

                let percent = state.scanTotal > 0
                    ? Math.round((state.scanned / state.scanTotal) * 100)
                    : Math.round((state.processed / Math.max(1, state.initialRemaining)) * 100);
                if (state.incomplete) {
                    percent = Math.min(99, percent);
                }
                inspectAllProgressBar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
            }

            async function inspectAllPendingBulk() {
                inspectAllStopRequested = false;
                setInspectAllRunning(true);
                inspectAllButton.disabled = false;
                document.querySelectorAll('[data-run-action], [data-single-action]').forEach(button => button.disabled = true);
                inspectAllProgressWrap.classList.remove('d-none');
                inspectAllProgressBar.classList.remove('bg-warning', 'bg-danger');
                inspectAllProgressBar.classList.add('progress-bar-animated');
                inspectAllProgressBar.style.width = '0';

                const state = {
                    initialRemaining: Math.max(0, Number(inspectAllCount.dataset.count) || 0),
                    processed: 0,
                    succeeded: 0,
                    failed: 0,
                    ignored: 0,
                    remaining: Math.max(0, Number(inspectAllCount.dataset.count) || 0),
                    processedTotal: 0,
                    succeededTotal: 0,
                    failedTotal: 0,
                    ignoredTotal: 0,
                    hasCumulativeTotals: false,
                    scanTotal: 0,
                    scanned: 0,
                    pass: 1,
                    done: false,
                    incomplete: false,
                    canReset: false,
                    message: '',
                    networkError: false,
                    errorMessage: ''
                };
                let reset = inspectAllBulkResetRequested;
                inspectAllBulkResetRequested = false;

                try {
                    while (!inspectAllStopRequested && !state.done) {
                        inspectAllProgress.textContent = state.processed === 0
                            ? 'Pokrećem bulk provjeru Delfi kataloga…'
                            : `Nastavljam bulk provjeru · preostalo ${state.remaining.toLocaleString('hr-HR')}…`;
                        const payload = await fetchBulkInspectionPage(reset);
                        reset = false;
                        updateBulkInspectionProgress(state, payload);

                        if (state.done || inspectAllStopRequested) {
                            break;
                        }

                        await new Promise(resolve => window.setTimeout(
                            resolve,
                            Math.max(0, Number(@json($importUi['bulk_inspection_delay_ms'])) || 350)
                        ));
                    }
                } catch (error) {
                    state.networkError = true;
                    state.errorMessage = error?.message || 'Bulk provjera je privremeno nedostupna.';
                }

                inspectAllProgressBar.classList.remove('progress-bar-animated');
                if (state.incomplete) {
                    inspectAllBulkResetRequested = state.canReset;
                    inspectAllProgressBar.classList.add('bg-warning');
                    const message = state.message || `Bulk prolaz je završen, ali ${state.remaining.toLocaleString('hr-HR')} knjiga nije pronađeno u Delfi listi.`;
                    showInspectAllSummary(`${message} Provjera nije označena kao potpuno dovršena.`);
                } else if (!state.networkError && state.done) {
                    inspectAllProgressBar.style.width = '100%';
                    const succeeded = state.hasCumulativeTotals ? state.succeededTotal : state.succeeded;
                    const failed = state.hasCumulativeTotals ? state.failedTotal : state.failed;
                    const totalsLabel = state.hasCumulativeTotals ? 'Ukupno' : 'U ovom pokretanju';
                    showInspectAllSummary(`${state.message || 'Provjera je završena.'} ${totalsLabel}: ${succeeded.toLocaleString('hr-HR')} uspješno, ${failed.toLocaleString('hr-HR')} s greškom.`);
                } else if (state.networkError) {
                    inspectAllProgressBar.classList.add('bg-danger');
                    showInspectAllSummary(`Bulk provjera je privremeno prekinuta. ${state.errorMessage} Već provjerene knjige su spremljene; ponovnim pokretanjem nastavlja se od istog mjesta.`);
                } else {
                    showInspectAllSummary(`Provjera je zaustavljena. U ovom pokretanju provjereno: ${state.processed.toLocaleString('hr-HR')}; preostalo: ${state.remaining.toLocaleString('hr-HR')}. Ponovnim pokretanjem nastavlja se od istog mjesta.`);
                }

                setInspectAllRunning(false);
                inspectAllButton.disabled = (!state.incomplete && state.done) || state.remaining === 0;
                document.querySelectorAll('[data-single-action]').forEach(button => button.disabled = false);
                updateSelectionState();
            }

            function inspectAllPending() {
                return bulkInspectionEndpoint ? inspectAllPendingBulk() : inspectAllPendingLegacy();
            }

            async function run(action, ids) {
                if (!ids.length) {
                    progressText.textContent = 'Odaberite barem jedan artikl.';
                    return;
                }

                document.querySelectorAll('[data-run-action], [data-single-action]').forEach(button => button.disabled = true);
                if (inspectAllButton) {
                    inspectAllButton.disabled = true;
                }
                progressWrap.classList.remove('d-none');
                let attempted = 0;
                let succeeded = 0;
                let failed = 0;
                let stoppedEarly = false;
                const errorMessages = [];
                const successMessages = [];

                for (const id of ids) {
                    attempted++;
                    const row = document.querySelector(`[data-source-row="${id}"]`);
                    const productName = row?.querySelector('a.font-w600')?.textContent?.trim() || `#${id}`;
                    progressText.textContent = `${action === 'inspect' ? 'Provjeravam' : 'Uvozim'} ${attempted} / ${ids.length}: ${productName}`;
                    progressBar.style.width = `${Math.round(((attempted - 1) / ids.length) * 100)}%`;

                    try {
                        const headers = {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        };
                        const options = {
                            method: 'POST',
                            headers
                        };

                        if (action === 'import') {
                            const selectedCategory = document.querySelector('[data-import-category]')?.value;
                            headers['Content-Type'] = 'application/json';
                            options.body = JSON.stringify({
                                category_id: selectedCategory ? Number(selectedCategory) : null
                            });
                        }

                        const response = await fetch(endpoint(action, id), options);
                        const payload = await response.json();
                        if (!response.ok || !payload.success) {
                            failed++;
                            row?.classList.add('table-danger');
                            errorMessages.push(`${productName}: ${payload.message || 'Akcija nije uspjela.'}`);
                            if (payload.retryable || response.status === 429 || response.status >= 500) {
                                stoppedEarly = true;
                                break;
                            }
                        } else {
                            succeeded++;
                            row?.classList.remove('table-danger');
                            row?.classList.add('table-success');
                            applySourceResult(row, payload);
                            if (typeof payload.message === 'string' && payload.message.trim() !== '') {
                                successMessages.push(`${productName}: ${payload.message.trim()}`);
                            }
                            if (action === 'inspect') {
                                row?.querySelector('[data-single-action="inspect"]')?.remove();
                            }
                            if (action === 'import') {
                                const checkbox = row?.querySelector('[data-source-checkbox]');
                                if (checkbox) {
                                    checkbox.checked = false;
                                }
                            }
                        }
                    } catch (error) {
                        failed++;
                        row?.classList.add('table-danger');
                        errorMessages.push(`${productName}: ${error?.message || 'Mrežna greška.'}`);
                        stoppedEarly = true;
                        break;
                    }
                }

                progressBar.style.width = '100%';
                progressText.innerHTML = `${stoppedEarly ? 'Prekinuto' : 'Završeno'}: <strong>${succeeded}</strong> uspješno, <strong>${failed}</strong> grešaka. <button class="btn btn-sm btn-link p-0 ml-1" type="button" data-reload-results>Osvježi rezultate</button>`;
                if (errorMessages.length) {
                    const errorDetail = document.createElement('div');
                    errorDetail.className = 'text-danger mt-1';
                    errorDetail.textContent = `Razlog: ${errorMessages[0]}`;
                    progressText.appendChild(errorDetail);
                }
                if (successMessages.length) {
                    const successDetail = document.createElement('div');
                    successDetail.className = 'text-success mt-1';
                    const visibleMessages = successMessages.slice(0, 3);
                    successDetail.textContent = visibleMessages.join(' · ')
                        + (successMessages.length > visibleMessages.length
                            ? ` · i još ${successMessages.length - visibleMessages.length}`
                            : '');
                    progressText.appendChild(successDetail);
                }
                progressText.querySelector('[data-reload-results]')?.addEventListener('click', () => window.location.reload());
                document.querySelectorAll('[data-single-action]').forEach(button => button.disabled = false);
                if (inspectAllButton) {
                    inspectAllButton.disabled = Number(inspectAllCount.dataset.count || 0) === 0;
                }
                updateSelectionState();
            }

            document.querySelector('[data-select-all]')?.addEventListener('change', event => {
                document.querySelectorAll('[data-source-checkbox]:not(:disabled)').forEach(input => input.checked = event.target.checked);
                updateSelectionState();
            });

            document.querySelectorAll('[data-source-checkbox]').forEach(input => {
                input.addEventListener('change', updateSelectionState);
            });

            document.querySelectorAll('[data-run-action]').forEach(button => {
                button.addEventListener('click', () => run(button.dataset.runAction, selectedIds()));
            });

            document.querySelectorAll('[data-single-action]').forEach(button => {
                button.addEventListener('click', () => run(button.dataset.singleAction, [button.dataset.sourceId]));
            });

            inspectAllButton?.addEventListener('click', function () {
                if (inspectAllRunning) {
                    inspectAllStopRequested = true;
                    inspectAllButton.disabled = true;
                    inspectAllLabel.textContent = 'Zaustavljam…';
                    return;
                }

                const remaining = Number(inspectAllCount.dataset.count || 0);
                if (!remaining || !window.confirm(`Pokrenuti provjeru svih ${remaining.toLocaleString('hr-HR')} neprovjerenih knjiga? Provjeru možete zaustaviti i nastaviti kasnije.`)) {
                    return;
                }

                inspectAllPending();
            });

            document.querySelector('[data-refresh-form]')?.addEventListener('submit', event => {
                const button = event.currentTarget.querySelector('button');
                if (supportsBatchedRefresh) {
                    event.preventDefault();
                    runBatchedFeedRefresh(storedFeedRefreshToken());
                    return;
                }
                button.disabled = true;
                button.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Preuzimam i uspoređujem...';
            });

            if (supportsBatchedRefresh && storedFeedRefreshToken()) {
                runBatchedFeedRefresh(storedFeedRefreshToken());
            }

            updateSelectionState();
        });
    </script>
@endpush
