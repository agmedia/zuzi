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
        $statusFilterQuery = request()->except(['page', 'status', 'tab', 'product_type']);
    @endphp

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <div>
                    <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Laguna import</h1>
                    <div class="text-muted">Inkrementalni uvoz knjiga s provjerom ISBN-a i prijevodom opisa na hrvatski</div>
                </div>
                <form action="{{ route('laguna-import.refresh') }}" method="post" class="my-2" data-refresh-form>
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

        <div class="block block-rounded mb-4">
            <ul class="nav nav-tabs nav-tabs-alt nav-justified" role="tablist">
                <li class="nav-item">
                    <a class="nav-link{{ ! $settingsTabActive ? ' active' : '' }}" data-toggle="tab" href="#laguna-products" role="tab">
                        <i class="fa fa-book mr-1"></i> Knjige
                        <span class="badge badge-pill badge-light ml-1">{{ number_format($statusCounts['all'], 0, ',', '.') }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link{{ $settingsTabActive ? ' active' : '' }}" data-toggle="tab" href="#laguna-settings" role="tab">
                        <i class="fa fa-sliders-h mr-1"></i> Postavke importa
                    </a>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade{{ ! $settingsTabActive ? ' show active' : '' }}" id="laguna-products" role="tabpanel">
                <div class="block block-rounded">
                    <div class="block-content block-content-full">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between">
                            <div class="d-flex align-items-center mb-3 mb-lg-0">
                                <div class="item item-rounded bg-{{ $feedMetadata['exists'] ? 'success-light' : 'warning-light' }} text-{{ $feedMetadata['exists'] ? 'success' : 'warning' }} mr-3">
                                    <i class="fa fa-{{ $feedMetadata['exists'] ? 'check' : 'exclamation-triangle' }}"></i>
                                </div>
                                <div>
                                    <div class="font-w600">{{ $feedMetadata['exists'] ? 'Laguna feed je spreman' : 'Feed još nije preuzet' }}</div>
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
                                <span class="badge badge-success p-2 mr-2"><i class="fa fa-filter mr-1"></i> Samo kategorija Knjige</span>
                                <a class="btn btn-sm btn-alt-secondary" href="{{ config('laguna_import.feed_url') }}" target="_blank" rel="noopener noreferrer">
                                    <i class="fa fa-external-link-alt mr-1"></i> Otvori RSS
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="block block-rounded">
                    <div class="block-header block-header-default d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center">
                        <h3 class="block-title">Filtriraj po statusu</h3>
                        <div class="block-options mt-2 mt-sm-0">
                            <button class="btn btn-sm btn-alt-primary" type="button" data-inspect-all {{ $inspectionPendingCount === 0 ? 'disabled' : '' }}>
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
                                <div class="small text-muted mt-1 mt-sm-0">Možete zaustaviti i nastaviti kasnije; već provjerene knjige automatski se preskaču.</div>
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
                               href="{{ route('laguna-import.index', array_merge($statusFilterQuery, ['status' => $status])) }}">
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
                <form action="{{ route('laguna-import.index') }}" method="get">
                    <div class="form-row align-items-end">
                        <div class="form-group col-lg-6">
                            <label for="search">Naziv, Laguna šifra ili ISBN</label>
                            <input id="search" class="form-control" type="text" name="search" value="{{ request('search') }}">
                        </div>
                        <div class="form-group col-lg-4">
                            <label for="status">Status</label>
                            <select id="status" class="form-control" name="status">
                                <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>Svi statusi</option>
                                @foreach($statusLabels as $status => $label)
                                    <option value="{{ $status }}" {{ $selectedStatus === $status ? 'selected' : '' }}>{{ $label[0] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-lg-2">
                            <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-filter mr-1"></i> Filtriraj</button>
                            @if(request('search') || request('status'))
                                <a class="btn btn-sm btn-link btn-block" href="{{ route('laguna-import.index') }}">Očisti filtre</a>
                            @endif
                        </div>
                    </div>
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
                            <div class="small text-muted mt-1">Vrijedi za skupni i pojedinačni uvoz. Prazno polje znači samo obavezne kategorije Nakladnici › Laguna.</div>
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
                                $regularEur = $priceCalculator->convert($source->price_rsd, $settings['exchange_rate'], $settings['markup_percent']);
                                $saleEur = $source->sale_price_rsd
                                    ? $priceCalculator->convert($source->sale_price_rsd, $settings['exchange_rate'], $settings['markup_percent'])
                                    : null;
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
                                    <div class="small text-muted">Laguna šifra: {{ $source->external_id }}</div>
                                    @if($source->author)<div class="small">{{ $source->author }}</div>@endif
                                </td>
                                <td class="small">
                                    <div><span class="text-muted">ISBN:</span> {{ $source->isbn ?: 'nije provjeren' }}</div>
                                    @if($source->format)<div><span class="text-muted">Format:</span> {{ $source->format }}</div>@endif
                                    @if($source->pages)<div><span class="text-muted">Stranica:</span> {{ $source->pages }}</div>@endif
                                    @if($source->letter || $source->binding)<div>{{ $source->letter }}{{ $source->letter && $source->binding ? ' · ' : '' }}{{ $source->binding }}</div>@endif
                                    @if($source->publication_year)<div><span class="text-muted">Godina:</span> {{ $source->publication_year }}</div>@endif
                                </td>
                                <td class="text-right small">
                                    <div>{{ number_format($source->price_rsd, 2, ',', '.') }} RSD</div>
                                    <div class="font-w700">{{ number_format($regularEur, 2, ',', '.') }} EUR</div>
                                    @if($saleEur && $saleEur < $regularEur)
                                        <div class="text-danger">Akcija {{ number_format($saleEur, 2, ',', '.') }} EUR</div>
                                    @endif
                                    <div class="text-{{ $source->availability === 'in stock' ? 'success' : 'muted' }}">{{ $source->availability === 'in stock' ? 'Dostupno' : 'Nedostupno' }}</div>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $statusLabels[$status][1] ?? 'secondary' }}">{{ $statusLabels[$status][0] ?? $status }}</span>
                                    @if($source->product)
                                        <div class="small mt-1"><a href="{{ route('products.edit', ['product' => $source->product]) }}">Zuzi #{{ $source->product->id }}</a></div>
                                    @endif
                                    @if($source->check_message)<div class="small text-muted mt-1">{{ $source->check_message }}</div>@endif
                                </td>
                                <td class="text-right text-nowrap">
                                    <a class="btn btn-sm btn-alt-secondary" href="{{ $source->source_url }}" target="_blank" rel="noopener noreferrer" title="Otvori na Laguna.rs" aria-label="Otvori {{ $source->name }} na Laguna.rs u novom tabu"><i class="fa fa-external-link-alt"></i></a>
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

            <div class="tab-pane fade{{ $settingsTabActive ? ' show active' : '' }}" id="laguna-settings" role="tabpanel">
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <div>
                            <h3 class="block-title">Postavke Laguna importa</h3>
                            <div class="small text-muted mt-1">Ovdje se određuju cijene, zaliha i obavezno Zuzi mapiranje.</div>
                        </div>
                    </div>
                    <form action="{{ route('laguna-import.settings') }}" method="post">
                        @csrf
                        <div class="block-content">
                            <h4 class="font-size-h5 mb-1"><i class="fa fa-euro-sign text-primary mr-2"></i>Cijena i dostupnost</h4>
                            <p class="text-muted small mb-4">Izvorna Laguna cijena automatski se pretvara iz RSD u EUR i zatim uvećava za zadani postotak.</p>

                            <div class="form-row">
                                <div class="form-group col-md-6 col-xl-3">
                                    <label for="exchange-rate">RSD za 1 EUR</label>
                                    <input id="exchange-rate" class="form-control" type="number" step="0.0001" min="0.0001" name="exchange_rate" value="{{ old('exchange_rate', $settings['exchange_rate']) }}" required>
                                    <small class="form-text text-muted">Primjer: 117,2</small>
                                </div>
                                <div class="form-group col-md-6 col-xl-3">
                                    <label for="markup-percent">Uvećanje cijene</label>
                                    <div class="input-group">
                                        <input id="markup-percent" class="form-control" type="number" step="0.01" min="0" name="markup_percent" value="{{ old('markup_percent', $settings['markup_percent']) }}" required>
                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                    </div>
                                    <small class="form-text text-muted">Dodaje se nakon konverzije.</small>
                                </div>
                                <div class="form-group col-md-6 col-xl-3">
                                    <label for="default-quantity">Količina kad je dostupno</label>
                                    <input id="default-quantity" class="form-control" type="number" min="0" name="default_quantity" value="{{ old('default_quantity', $settings['default_quantity']) }}" required>
                                    <small class="form-text text-muted">Za nedostupne knjige sprema se 0.</small>
                                </div>
                                <div class="form-group col-md-6 col-xl-3">
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
                                <div>Primjer izračuna: <strong>1.500 RSD</strong> → <strong data-price-preview>—</strong> EUR</div>
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
                                    <label for="publisher-category">Laguna podkategorija</label>
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
                                    <small class="form-text text-muted">Zadano: Laguna</small>
                                </div>
                            </div>

                            <div class="bg-body-light rounded p-3 mb-3">
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
            $('#publisher-parent-category').on('change', filterPublisherCategories);

            if (window.location.hash === '#laguna-settings') {
                $('a[href="#laguna-settings"]').tab('show');
            }
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (event) {
                window.history.replaceState(null, '', event.target.hash);
            });

            function updatePricePreview() {
                const rate = Number(String($('#exchange-rate').val() || '').replace(',', '.'));
                const markup = Number(String($('#markup-percent').val() || '').replace(',', '.'));
                const converted = rate > 0 ? (1500 / rate) * (1 + Math.max(0, markup) / 100) : 0;
                $('[data-price-preview]').text(converted > 0
                    ? converted.toLocaleString('hr-HR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : '—');
            }
            $('#exchange-rate, #markup-percent').on('input change', updatePricePreview);
            updatePricePreview();

            const csrf = @json(csrf_token());
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
            const inspectionQueueEndpoint = @json(route('laguna-import.inspection-queue'));
            const endpointTemplates = {
                inspect: @json(route('laguna-import.inspect', ['lagunaImportProduct' => '__ID__'])),
                import: @json(route('laguna-import.import', ['lagunaImportProduct' => '__ID__']))
            };
            let inspectAllRunning = false;
            let inspectAllStopRequested = false;

            const selectedIds = () => Array.from(document.querySelectorAll('[data-source-checkbox]:checked')).map(input => input.value);
            const endpoint = (action, id) => endpointTemplates[action].replace('__ID__', id);

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

            async function loadInspectionQueue(limit = 20) {
                const response = await fetch(`${inspectionQueueEndpoint}?limit=${limit}`, {
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
                    return true;
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

                        let succeeded;
                        try {
                            succeeded = await inspectQueuedItem(item);
                        } catch (error) {
                            state.networkError = true;
                            inspectAllStopRequested = true;
                            return;
                        }

                        state.processed++;
                        state[succeeded ? 'succeeded' : 'failed']++;
                        const row = document.querySelector(`[data-source-row="${item.id}"]`);
                        row?.classList.remove(succeeded ? 'table-danger' : 'table-success');
                        row?.classList.add(succeeded ? 'table-success' : 'table-danger');
                        if (succeeded) {
                            row?.querySelector('[data-single-action="inspect"]')?.remove();
                        }
                        inspectAllProgressBar.style.width = `${Math.round((state.processed / state.total) * 100)}%`;
                    }
                };

                await Promise.all(Array.from({ length: Math.min(2, items.length) }, worker));
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

            async function inspectAllPending() {
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
                    remaining: Number(inspectAllCount.dataset.count || 0)
                };

                try {
                    while (!inspectAllStopRequested) {
                        const queue = await loadInspectionQueue();
                        state.remaining = Number(queue.remaining || 0);
                        setInspectAllCount(state.remaining);

                        if (!state.total) {
                            state.total = state.remaining;
                        }
                        if (!queue.items.length) {
                            break;
                        }

                        await inspectQueueBatch(queue.items, state);
                        if (!inspectAllStopRequested) {
                            await new Promise(resolve => window.setTimeout(resolve, 250));
                        }
                    }
                } catch (error) {
                    state.networkError = true;
                }

                try {
                    const latestQueue = await loadInspectionQueue(1);
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
                    showInspectAllSummary(`Provjera je prekinuta zbog mrežne greške nakon ${state.processed.toLocaleString('hr-HR')} knjiga. Ponovnim pokretanjem nastavlja se od preostalih.`);
                } else {
                    showInspectAllSummary(`Provjera je zaustavljena nakon ${state.processed.toLocaleString('hr-HR')} knjiga. Preostalo: ${state.remaining.toLocaleString('hr-HR')}.`);
                }

                setInspectAllRunning(false);
                inspectAllButton.disabled = state.remaining === 0;
                document.querySelectorAll('[data-single-action]').forEach(button => button.disabled = false);
                updateSelectionState();
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
                        } else {
                            succeeded++;
                            row?.classList.remove('table-danger');
                            row?.classList.add('table-success');
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
                        break;
                    }
                }

                progressBar.style.width = '100%';
                progressText.innerHTML = `Završeno: <strong>${succeeded}</strong> uspješno, <strong>${failed}</strong> grešaka. <button class="btn btn-sm btn-link p-0 ml-1" type="button" data-reload-results>Osvježi rezultate</button>`;
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
                button.disabled = true;
                button.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Preuzimam i uspoređujem...';
            });

            updateSelectionState();
        });
    </script>
@endpush
