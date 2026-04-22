@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/flatpickr/flatpickr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@php
    $publishDateValue = old('publish_date', isset($blog) ? $blog->publish_date : null);

    if (filled($publishDateValue)) {
        try {
            $publishDateValue = \Illuminate\Support\Carbon::parse($publishDateValue)->format('d.m.Y H:i');
        } catch (\Throwable $exception) {
            try {
                $publishDateValue = \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i', (string) $publishDateValue)->format('d.m.Y H:i');
            } catch (\Throwable $exception) {
            }
        }
    }

    $rawSelectedRelatedProducts = old('related_products');

    if (empty($rawSelectedRelatedProducts)) {
        $oldRelatedProductsJson = old('related_products_json');

        if (is_string($oldRelatedProductsJson) && trim($oldRelatedProductsJson) !== '') {
            $decodedOldRelatedProductsJson = json_decode($oldRelatedProductsJson, true);
            $rawSelectedRelatedProducts = is_array($decodedOldRelatedProductsJson) ? $decodedOldRelatedProductsJson : [];
        }
    }

    if (empty($rawSelectedRelatedProducts)) {
        $rawSelectedRelatedProducts = old('action_list', isset($blog) ? $blog->related_products : []);
    }

    if (is_string($rawSelectedRelatedProducts)) {
        $decodedSelectedRelatedProducts = json_decode($rawSelectedRelatedProducts, true);
        $rawSelectedRelatedProducts = is_array($decodedSelectedRelatedProducts) ? $decodedSelectedRelatedProducts : [];
    }

    $selectedRelatedProductIds = collect($rawSelectedRelatedProducts)
        ->map(fn ($id) => (int) $id)
        ->filter()
        ->values()
        ->all();

    $selectedRelatedProducts = \App\Models\Back\Catalog\Product\Product::query()
        ->whereIn('id', $selectedRelatedProductIds)
        ->get(['id', 'name', 'sku'])
        ->sortBy(fn ($product) => array_search($product->id, $selectedRelatedProductIds, true))
        ->values();

    $selectedRelatedProductOptions = $selectedRelatedProducts->map(function ($product) {
        return [
            'id' => (string) $product->id,
            'text' => $product->name . ($product->sku ? ' - ' . $product->sku : ''),
        ];
    })->values();

    $ctaButtonStyleOptions = [
        'primary' => 'Primary',
        'secondary' => 'Secondary',
        'outline' => 'Outline',
    ];

    $asBool = static function ($value, bool $default = true): bool {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $bool ?? $default;
    };

    $rawCtaBlocks = old('cta_blocks');

    if (! is_array($rawCtaBlocks)) {
        $existingCtaBlocks = isset($blog)
            ? (($blog->relationLoaded('ctaBlocks') ? $blog->ctaBlocks : $blog->ctaBlocks()->with('buttons')->get())->values())
            : collect();

        $rawCtaBlocks = $existingCtaBlocks->map(function ($ctaBlock) {
            return [
                'id' => $ctaBlock->id,
                'title' => $ctaBlock->title,
                'description' => $ctaBlock->description,
                'sort_order' => $ctaBlock->sort_order,
                'is_active' => $ctaBlock->is_active,
                'buttons' => $ctaBlock->buttons->values()->map(function ($ctaButton) {
                    return [
                        'id' => $ctaButton->id,
                        'label' => $ctaButton->label,
                        'url' => $ctaButton->url,
                        'icon' => $ctaButton->icon,
                        'style' => $ctaButton->style,
                        'sort_order' => $ctaButton->sort_order,
                        'is_active' => $ctaButton->is_active,
                    ];
                })->all(),
            ];
        })->all();
    }

    $ctaBlocksFormData = collect($rawCtaBlocks)
        ->filter(fn ($ctaBlock) => is_array($ctaBlock))
        ->values()
        ->map(function (array $ctaBlock, int $blockIndex) use ($asBool, $ctaButtonStyleOptions) {
            $buttons = collect($ctaBlock['buttons'] ?? [])
                ->filter(fn ($ctaButton) => is_array($ctaButton))
                ->values()
                ->map(function (array $ctaButton, int $buttonIndex) use ($asBool, $ctaButtonStyleOptions) {
                    $style = (string) ($ctaButton['style'] ?? 'outline');

                    if (! array_key_exists($style, $ctaButtonStyleOptions)) {
                        $style = 'outline';
                    }

                    return [
                        'id' => $ctaButton['id'] ?? null,
                        'label' => (string) ($ctaButton['label'] ?? ''),
                        'url' => (string) ($ctaButton['url'] ?? ''),
                        'icon' => (string) ($ctaButton['icon'] ?? ''),
                        'style' => $style,
                        'sort_order' => $ctaButton['sort_order'] ?? ($buttonIndex + 1),
                        'is_active' => $asBool($ctaButton['is_active'] ?? 1, true),
                    ];
                })
                ->all();

            return [
                'id' => $ctaBlock['id'] ?? null,
                'title' => (string) ($ctaBlock['title'] ?? ''),
                'description' => (string) ($ctaBlock['description'] ?? ''),
                'sort_order' => $ctaBlock['sort_order'] ?? ($blockIndex + 1),
                'is_active' => $asBool($ctaBlock['is_active'] ?? 1, true),
                'buttons' => $buttons,
            ];
        })
        ->all();

    $blogPublicBaseUrl = rtrim(route('catalog.route.blog'), '/');
    $reusableCtaBlocks = collect($reusableCtaBlocks ?? [])->values()->all();
@endphp

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Blog edit</h1>
            </div>
        </div>
    </div>

    <div class="content content-full content-boxed">
        @include('back.layouts.partials.session')

        <form action="{{ isset($blog) ? route('blogs.update', ['blog' => $blog]) : route('blogs.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if (isset($blog))
                {{ method_field('PATCH') }}
            @endif

            <div class="block">
                <div class="block-header block-header-default">
                    <a class="btn btn-light" href="{{ back()->getTargetUrl() }}">
                        <i class="fa fa-arrow-left mr-1"></i> Povratak
                    </a>
                    <div class="block-options">
                        <div class="custom-control custom-switch custom-control-success">
                            <input type="checkbox" class="custom-control-input" id="dm-post-edit-active" name="status" {{ (isset($blog) and $blog->status) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="dm-post-edit-active">Aktiviraj</label>
                        </div>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center push">
                        <div class="col-md-10">
                            <div class="form-group">
                                <label for="title-input">Naslov</label>
                                <input type="text" class="form-control" id="title-input" name="title" placeholder="Upišite naslov..." value="{{ isset($blog) ? $blog->title : old('title') }}" onkeyup="SetSEOPreview()">
                            </div>

                            <div class="form-group">
                                <label for="short-description-input">Sažetak</label>
                                <textarea class="form-control" id="short-description-input" name="short_description" rows="3" placeholder="Enter an excerpt..">{{ isset($blog) ? $blog->short_description : old('short_description') }}</textarea>
                                <div class="form-text text-muted font-size-sm font-italic">Vidljivo na početnoj stranici</div>
                            </div>

                            <div class="form-group row">
                                <div class="col-xl-6">
                                    <label>Glavna slika</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="image-input" name="image" data-toggle="custom-file-input" onchange="readURL(this);">
                                        <label class="custom-file-label" for="image-input">Odaberite sliku</label>
                                    </div>
                                    <div class="mt-2">
                                        <img class="img-fluid" id="image-view" src="{{ isset($blog) ? asset($blog->image) : asset('media/img/lightslider.webp') }}" alt="">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group row mb-4">
                                <div class="col-md-12">
                                    <label for="description-editor">Opis</label>
                                    <textarea id="description-editor" name="description">{!! isset($blog) ? $blog->description : old('description') !!}</textarea>
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-xl-6">
                                    <label for="publish-date-input">Datum objave</label>
                                    <input
                                        type="text"
                                        class="form-control bg-white"
                                        id="publish-date-input"
                                        value="{{ $publishDateValue }}"
                                        name="publish_date"
                                        placeholder="npr. 22.04.2026 14:30"
                                    >
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-xl-12">
                                    <label>Povezani artikli</label>
                                    <select class="form-control" id="related-products-select" name="related_products[]" multiple="multiple" data-placeholder="Pretraži i odaberi artikle..." style="width: 100%;">
                                        @foreach($selectedRelatedProducts as $selectedRelatedProduct)
                                            <option value="{{ $selectedRelatedProduct->id }}" selected>
                                                {{ $selectedRelatedProduct->name }}{{ $selectedRelatedProduct->sku ? ' - ' . $selectedRelatedProduct->sku : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" id="related-products-json" name="related_products_json" value='@json($selectedRelatedProductIds)'>
                                    <div class="form-text text-muted font-size-sm font-italic">Odabrani artikli prikazat će se kao carousel na dnu blog posta.</div>
                                    @error('related_products')
                                        <div class="text-danger font-italic mt-1">{{ $message }}</div>
                                    @enderror
                                    @error('related_products.*')
                                        <div class="text-danger font-italic mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="block">
                <div class="block-header block-header-default">
                    <h3 class="block-title">CTA blokovi</h3>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center push">
                        <div class="col-md-10">
                            <div class="form-text text-muted mb-3">
                                Dodaj jedan ili više CTA blokova. Svaki blok može imati više buttona, ručni redoslijed i aktivan/neaktivan status.
                            </div>

                            <div class="card card-body bg-body-light border mb-4">
                                <div class="row align-items-end">
                                    <div class="col-lg-9 form-group mb-lg-0">
                                        <label for="cta-library-select">Naslov bloka</label>
                                        <select class="form-control" id="cta-library-select">
                                            <option value="">Odaberi postojeći CTA blok...</option>
                                            @foreach($reusableCtaBlocks as $reusableCtaBlock)
                                                <option value="{{ $reusableCtaBlock['id'] }}">{{ $reusableCtaBlock['title'] }}</option>
                                            @endforeach
                                        </select>
                                        <div class="form-text text-muted font-size-sm font-italic">
                                            Prikazani su svi postojeći CTA blokovi. Odabrani blok se kopira u ovaj članak pa ga možeš dodatno urediti prije spremanja.
                                        </div>
                                    </div>

                                    <div class="col-lg-3 form-group mb-0">
                                        <button type="button" class="btn btn-alt-primary btn-block" id="add-existing-cta-block-button" disabled>
                                            <i class="fa fa-plus mr-1"></i> Dodaj blok
                                        </button>
                                    </div>
                                </div>

                                <div class="alert alert-info mb-0 mt-3 d-none" id="cta-import-feedback"></div>
                            </div>

                            <div
                                id="cta-blocks-builder"
                                data-initial-blocks='@json($ctaBlocksFormData)'
                                data-style-options='@json($ctaButtonStyleOptions)'
                                data-blog-base-url='@json($blogPublicBaseUrl)'
                                data-reusable-blocks='@json($reusableCtaBlocks)'>
                            </div>

                            <button type="button" class="btn btn-alt-success mt-3" id="add-cta-block-button">
                                <i class="fa fa-plus mr-1"></i> Dodaj CTA blok
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="block">
                <div class="block-header block-header-default">
                    <h3 class="block-title">Meta Data - SEO</h3>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center">
                        <div class="col-md-10 ">
                            <div class="form-group">
                                <label for="meta-title-input">Meta naslov</label>
                                <input type="text" class="js-maxlength form-control" id="meta-title-input" name="meta_title" value="{{ isset($blog) ? $blog->meta_title : old('meta_title') }}" maxlength="70" data-always-show="true" data-placement="top">
                                <small class="form-text text-muted">
                                    70 znakova max
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="meta-description-input">Meta opis</label>
                                <textarea class="js-maxlength form-control" id="meta-description-input" name="meta_description" rows="4" maxlength="160" data-always-show="true" data-placement="top">{{ isset($blog) ? $blog->meta_description : old('meta_description') }}</textarea>
                                <small class="form-text text-muted">
                                    160 znakova max
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="slug-input">SEO link (url)</label>
                                <input type="text" class="form-control" id="slug-input" name="slug" value="{{ isset($blog) ? $blog->slug : old('slug') }}" disabled>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="block-content bg-body-light">
                    <div class="row justify-content-center push">
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-hero-success my-2">
                                <i class="fas fa-save mr-1"></i> Snimi
                            </button>
                        </div>
                        @if (isset($blog))
                            <div class="col-md-5 text-right">
                                <a href="{{ route('blogs.destroy', ['blog' => $blog]) }}" type="submit" class="btn btn-hero-danger my-2 js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="Obriši" onclick="event.preventDefault(); document.getElementById('delete-blog-form{{ $blog->id }}').submit();">
                                    <i class="fa fa-trash-alt"></i> Obriši
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        @if (isset($blog))
            <form id="delete-blog-form{{ $blog->id }}" action="{{ route('blogs.destroy', ['blog' => $blog]) }}" method="POST" style="display: none;">
                @csrf
                {{ method_field('DELETE') }}
            </form>
        @endif
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>
    <script src="{{ asset('js/plugins/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>

    <script>
        $(() => {
            const publishDateInput = document.querySelector('#publish-date-input');
            const relatedProductsSelect = $('#related-products-select');
            const relatedProductsJson = $('#related-products-json');
            const initialRelatedProducts = @json($selectedRelatedProductOptions);
            const ctaBlocksBuilder = $('#cta-blocks-builder');
            const addCtaBlockButton = $('#add-cta-block-button');
            const ctaLibrarySelect = $('#cta-library-select');
            const addExistingCtaBlockButton = $('#add-existing-cta-block-button');
            const ctaImportFeedback = $('#cta-import-feedback');
            const styleOptions = ctaBlocksBuilder.data('style-options') || {};
            const blogBaseUrl = ctaBlocksBuilder.data('blog-base-url') || '';
            const reusableCtaBlocks = Array.isArray(ctaBlocksBuilder.data('reusable-blocks')) ? ctaBlocksBuilder.data('reusable-blocks') : [];
            let ctaBlocksState = Array.isArray(ctaBlocksBuilder.data('initial-blocks')) ? ctaBlocksBuilder.data('initial-blocks') : [];

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const normalizeSortOrder = (value, defaultValue) => {
                const parsed = Number.parseInt(value, 10);

                return Number.isFinite(parsed) && parsed >= 0 ? parsed : defaultValue;
            };

            const normalizeButtonState = (button = {}, buttonIndex = 0) => {
                const style = typeof button.style === 'string' && styleOptions[button.style] ? button.style : 'outline';
                const icon = button.icon_display ?? button.icon ?? '';

                return {
                    id: button.id ?? '',
                    label: button.label ?? '',
                    url: button.url ?? '',
                    icon,
                    style,
                    sort_order: normalizeSortOrder(button.sort_order, buttonIndex + 1),
                    is_active: button.is_active !== false && button.is_active !== 0 && button.is_active !== '0',
                };
            };

            const normalizeBlockState = (block = {}, blockIndex = 0) => {
                const buttons = Array.isArray(block.buttons) ? block.buttons.map((button, buttonIndex) => normalizeButtonState(button, buttonIndex)) : [];

                return {
                    id: block.id ?? '',
                    title: block.title ?? '',
                    description: block.description ?? '',
                    sort_order: normalizeSortOrder(block.sort_order, blockIndex + 1),
                    is_active: block.is_active !== false && block.is_active !== 0 && block.is_active !== '0',
                    buttons: buttons.length ? buttons : [normalizeButtonState({}, 0)],
                };
            };

            const syncSequentialSortOrders = (blocks) => blocks.map((block, blockIndex) => ({
                ...normalizeBlockState(block, blockIndex),
                sort_order: blockIndex + 1,
                buttons: (Array.isArray(block.buttons) ? block.buttons : []).map((button, buttonIndex) => ({
                    ...normalizeButtonState(button, buttonIndex),
                    sort_order: buttonIndex + 1,
                })),
            }));

            const readStateFromDom = () => ctaBlocksBuilder.find('.cta-block-item').map(function (blockIndex) {
                const block = $(this);

                return normalizeBlockState({
                    id: block.find('[data-field="block-id"]').val(),
                    title: block.find('[data-field="block-title"]').val(),
                    description: block.find('[data-field="block-description"]').val(),
                    sort_order: block.find('[data-field="block-sort-order"]').val(),
                    is_active: block.find('[data-field="block-is-active"]').is(':checked'),
                    buttons: block.find('.cta-button-item').map(function (buttonIndex) {
                        const button = $(this);

                        return normalizeButtonState({
                            id: button.find('[data-field="button-id"]').val(),
                            label: button.find('[data-field="button-label"]').val(),
                            url: button.find('[data-field="button-url"]').val(),
                            icon_display: button.find('[data-field="button-icon-display"]').val(),
                            icon: button.find('[data-field="button-icon"]').val(),
                            style: button.find('[data-field="button-style"]').val(),
                            sort_order: button.find('[data-field="button-sort-order"]').val(),
                            is_active: button.find('[data-field="button-is-active"]').is(':checked'),
                        }, buttonIndex);
                    }).get(),
                }, blockIndex);
            }).get();

            const styleOptionsMarkup = (selectedValue) => Object.entries(styleOptions).map(([value, label]) => {
                return `<option value="${escapeHtml(value)}" ${selectedValue === value ? 'selected' : ''}>${escapeHtml(label)}</option>`;
            }).join('');

            const renderButton = (button, blockIndex, buttonIndex) => {
                const activeInputId = `cta-button-active-${blockIndex}-${buttonIndex}`;

                return `
                    <div class="card card-body mb-3 cta-button-item" data-button-index="${buttonIndex}">
                        <input type="hidden" name="cta_blocks[${blockIndex}][buttons][${buttonIndex}][id]" value="${escapeHtml(button.id)}" data-field="button-id">

                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                            <strong>Button #${buttonIndex + 1}</strong>
                            <div class="btn-group btn-group-sm mt-2 mt-lg-0">
                                <button type="button" class="btn btn-alt-secondary move-cta-button-up" data-block-index="${blockIndex}" data-button-index="${buttonIndex}">
                                    <i class="fa fa-arrow-up"></i>
                                </button>
                                <button type="button" class="btn btn-alt-secondary move-cta-button-down" data-block-index="${blockIndex}" data-button-index="${buttonIndex}">
                                    <i class="fa fa-arrow-down"></i>
                                </button>
                                <button type="button" class="btn btn-alt-danger remove-cta-button" data-block-index="${blockIndex}" data-button-index="${buttonIndex}">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-4 form-group">
                                <label>Naziv</label>
                                <input type="text" class="form-control" name="cta_blocks[${blockIndex}][buttons][${buttonIndex}][label]" value="${escapeHtml(button.label)}" placeholder="npr. Moderni ljubavni romani" data-field="button-label">
                            </div>

                            <div class="col-lg-4 form-group">
                                <label>Link / URL</label>
                                <input type="text" class="form-control cta-button-url-input" name="cta_blocks[${blockIndex}][buttons][${buttonIndex}][url]" value="${escapeHtml(button.url)}" placeholder="/beletristika/ljubici/moderni" data-field="button-url">
                                <div class="text-warning font-size-sm mt-2 cta-self-link-warning" style="display:none;">
                                    Ovaj link vodi na isti blog članak.
                                </div>
                            </div>

                            <div class="col-lg-2 form-group">
                                <label>Ikonica / emoji</label>
                                <input type="text" class="form-control cta-button-icon-input" value="${escapeHtml(button.icon)}" placeholder="💕" data-field="button-icon-display">
                                <input type="hidden" name="cta_blocks[${blockIndex}][buttons][${buttonIndex}][icon]" value="${escapeHtml(button.icon)}" data-field="button-icon">
                            </div>

                            <div class="col-lg-2 form-group">
                                <label>Stil</label>
                                <select class="form-control" name="cta_blocks[${blockIndex}][buttons][${buttonIndex}][style]" data-field="button-style">
                                    ${styleOptionsMarkup(button.style)}
                                </select>
                            </div>
                        </div>

                        <div class="row align-items-end">
                            <div class="col-lg-2 form-group">
                                <label>Redoslijed</label>
                                <input type="number" class="form-control" name="cta_blocks[${blockIndex}][buttons][${buttonIndex}][sort_order]" value="${escapeHtml(button.sort_order)}" readonly data-field="button-sort-order">
                            </div>

                            <div class="col-lg-4 form-group">
                                <div class="custom-control custom-switch custom-control-success mt-4">
                                    <input type="hidden" name="cta_blocks[${blockIndex}][buttons][${buttonIndex}][is_active]" value="0">
                                    <input type="checkbox" class="custom-control-input" id="${activeInputId}" name="cta_blocks[${blockIndex}][buttons][${buttonIndex}][is_active]" value="1" ${button.is_active ? 'checked' : ''} data-field="button-is-active">
                                    <label class="custom-control-label" for="${activeInputId}">Aktivan</label>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            };

            const renderBlock = (block, blockIndex) => {
                const activeInputId = `cta-block-active-${blockIndex}`;
                const buttonsMarkup = block.buttons.map((button, buttonIndex) => renderButton(button, blockIndex, buttonIndex)).join('');

                return `
                    <div class="card mb-4 cta-block-item" data-block-index="${blockIndex}">
                        <div class="card-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
                            <strong>CTA blok #${blockIndex + 1}</strong>
                            <div class="btn-group btn-group-sm mt-2 mt-lg-0">
                                <button type="button" class="btn btn-alt-secondary move-cta-block-up" data-block-index="${blockIndex}">
                                    <i class="fa fa-arrow-up"></i>
                                </button>
                                <button type="button" class="btn btn-alt-secondary move-cta-block-down" data-block-index="${blockIndex}">
                                    <i class="fa fa-arrow-down"></i>
                                </button>
                                <button type="button" class="btn btn-alt-danger remove-cta-block" data-block-index="${blockIndex}">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="card-body">
                            <input type="hidden" name="cta_blocks[${blockIndex}][id]" value="${escapeHtml(block.id)}" data-field="block-id">

                            <div class="row">
                                <div class="col-lg-7 form-group">
                                    <label>Naslov bloka</label>
                                    <input type="text" class="form-control" name="cta_blocks[${blockIndex}][title]" value="${escapeHtml(block.title)}" placeholder="npr. Istraži ljubavne romane" data-field="block-title">
                                </div>

                                <div class="col-lg-2 form-group">
                                    <label>Redoslijed</label>
                                    <input type="number" class="form-control" name="cta_blocks[${blockIndex}][sort_order]" value="${escapeHtml(block.sort_order)}" readonly data-field="block-sort-order">
                                </div>

                                <div class="col-lg-3 form-group">
                                    <div class="custom-control custom-switch custom-control-success mt-4">
                                        <input type="hidden" name="cta_blocks[${blockIndex}][is_active]" value="0">
                                        <input type="checkbox" class="custom-control-input" id="${activeInputId}" name="cta_blocks[${blockIndex}][is_active]" value="1" ${block.is_active ? 'checked' : ''} data-field="block-is-active">
                                        <label class="custom-control-label" for="${activeInputId}">Aktivan blok</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Opis bloka</label>
                                <textarea class="form-control" rows="3" name="cta_blocks[${blockIndex}][description]" placeholder="Opcionalno" data-field="block-description">${escapeHtml(block.description)}</textarea>
                            </div>

                            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3">
                                <h4 class="font-size-base text-uppercase text-muted mb-2 mb-lg-0">Buttoni</h4>
                                <button type="button" class="btn btn-alt-primary btn-sm add-cta-button" data-block-index="${blockIndex}">
                                    <i class="fa fa-plus mr-1"></i> Dodaj button
                                </button>
                            </div>

                            <div class="cta-buttons-container">
                                ${buttonsMarkup}
                            </div>
                        </div>
                    </div>
                `;
            };

            const renderCtaBlocks = () => {
                ctaBlocksState = syncSequentialSortOrders(ctaBlocksState);

                if (! ctaBlocksState.length) {
                    ctaBlocksBuilder.html(`
                        <div class="alert alert-info mb-0">
                            Nema dodanih CTA blokova. Klikni na "Dodaj CTA blok" za početak.
                        </div>
                    `);

                    return;
                }

                ctaBlocksBuilder.html(ctaBlocksState.map((block, blockIndex) => renderBlock(block, blockIndex)).join(''));
                refreshCtaWarnings();
            };

            const showCtaImportFeedback = (message, type = 'info') => {
                ctaImportFeedback
                    .removeClass('d-none alert-info alert-success alert-warning alert-danger')
                    .addClass(`alert-${type}`)
                    .text(message);
            };

            const setExistingBlockButtonState = () => {
                addExistingCtaBlockButton.prop('disabled', ! ctaLibrarySelect.val());
            };

            const normalizeUrlPath = (value) => {
                if (! value) {
                    return '';
                }

                try {
                    const url = new URL(value, window.location.origin);
                    return url.pathname.replace(/\/+$/, '') || '/';
                } catch (error) {
                    const fallback = String(value).split('?')[0].split('#')[0];
                    const withLeadingSlash = fallback.startsWith('/') ? fallback : `/${fallback}`;
                    return withLeadingSlash.replace(/\/+$/, '') || '/';
                }
            };

            const currentBlogPath = () => {
                const slugInput = $('#slug-input').val() || '';

                if (! slugInput) {
                    return '';
                }

                return normalizeUrlPath(`${blogBaseUrl}/${slugInput}`);
            };

            const refreshCtaWarnings = () => {
                const blogPath = currentBlogPath();

                ctaBlocksBuilder.find('.cta-button-item').each(function () {
                    const button = $(this);
                    const urlInput = button.find('.cta-button-url-input');
                    const warning = button.find('.cta-self-link-warning');
                    const buttonPath = normalizeUrlPath(urlInput.val());

                    warning.toggle(Boolean(blogPath) && buttonPath === blogPath);
                });
            };

            window.refreshBlogCtaWarnings = refreshCtaWarnings;

            if (ctaBlocksState.length) {
                ctaBlocksState = ctaBlocksState.map((block, blockIndex) => normalizeBlockState(block, blockIndex));
            }

            flatpickr(publishDateInput, {
                enableTime: true,
                time_24hr: true,
                allowInput: true,
                dateFormat: 'd.m.Y H:i'
            });

            relatedProductsSelect.select2({
                placeholder: 'Pretraži i odaberi artikle...',
                multiple: true,
                ajax: {
                    url: '{{ route('products.autocomplete') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            query: params.term || ''
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(function (item) {
                                return {
                                    id: item.id,
                                    text: item.name + (item.sku ? ' - ' + item.sku : '')
                                };
                            })
                        };
                    },
                    cache: true
                },
                minimumInputLength: 1
            });

            const syncRelatedProducts = () => {
                const selectedItems = relatedProductsSelect.select2('data').filter((item) => item.id);
                const selectedIds = selectedItems.map((item) => String(item.id));

                selectedItems.forEach((item) => {
                    if (! relatedProductsSelect.find(`option[value="${item.id}"]`).length) {
                        relatedProductsSelect.append(new Option(item.text, item.id, true, true));
                    }
                });

                relatedProductsSelect.find('option').each(function () {
                    $(this).prop('selected', selectedIds.includes(String(this.value)));
                });

                relatedProductsJson.val(JSON.stringify(selectedIds));
            };

            initialRelatedProducts.forEach((item) => {
                if (! relatedProductsSelect.find(`option[value="${item.id}"]`).length) {
                    relatedProductsSelect.append(new Option(item.text, item.id, true, true));
                }
            });

            if (initialRelatedProducts.length) {
                relatedProductsSelect.val(initialRelatedProducts.map((item) => item.id)).trigger('change');
            }

            syncRelatedProducts();

            relatedProductsSelect.on('change select2:select select2:unselect', function () {
                syncRelatedProducts();
            });

            ctaLibrarySelect.on('change', function () {
                if (ctaImportFeedback.hasClass('alert-danger') || ctaImportFeedback.hasClass('alert-warning')) {
                    ctaImportFeedback.addClass('d-none').text('');
                }

                setExistingBlockButtonState();
            });

            addExistingCtaBlockButton.on('click', function () {
                const selectedBlockId = ctaLibrarySelect.val();
                const selectedBlock = reusableCtaBlocks.find((block) => String(block.id) === String(selectedBlockId));

                if (! selectedBlock) {
                    showCtaImportFeedback('Odabrani CTA blok nije pronađen.', 'warning');
                    return;
                }

                ctaBlocksState = syncSequentialSortOrders(readStateFromDom());
                ctaBlocksState.push(normalizeBlockState({
                    ...selectedBlock,
                    id: '',
                    buttons: Array.isArray(selectedBlock.buttons)
                        ? selectedBlock.buttons.map((button) => ({
                            ...button,
                            id: '',
                        }))
                        : [],
                }, ctaBlocksState.length));

                renderCtaBlocks();
                ctaLibrarySelect.val('');
                setExistingBlockButtonState();
                showCtaImportFeedback(`CTA blok "${selectedBlock.title}" je dodan u ovaj članak.`, 'success');
            });

            addCtaBlockButton.on('click', function () {
                ctaBlocksState = syncSequentialSortOrders(readStateFromDom());
                ctaBlocksState.push(normalizeBlockState({}, ctaBlocksState.length));
                renderCtaBlocks();
            });

            ctaBlocksBuilder.on('click', '.remove-cta-block', function () {
                const blockIndex = Number($(this).data('block-index'));
                ctaBlocksState = syncSequentialSortOrders(readStateFromDom()).filter((_, index) => index !== blockIndex);
                renderCtaBlocks();
            });

            ctaBlocksBuilder.on('click', '.move-cta-block-up, .move-cta-block-down', function () {
                const blockIndex = Number($(this).data('block-index'));
                const direction = $(this).hasClass('move-cta-block-up') ? -1 : 1;

                ctaBlocksState = syncSequentialSortOrders(readStateFromDom());
                const targetIndex = blockIndex + direction;

                if (targetIndex < 0 || targetIndex >= ctaBlocksState.length) {
                    return;
                }

                [ctaBlocksState[blockIndex], ctaBlocksState[targetIndex]] = [ctaBlocksState[targetIndex], ctaBlocksState[blockIndex]];
                renderCtaBlocks();
            });

            ctaBlocksBuilder.on('click', '.add-cta-button', function () {
                const blockIndex = Number($(this).data('block-index'));

                ctaBlocksState = syncSequentialSortOrders(readStateFromDom());
                ctaBlocksState[blockIndex].buttons.push(normalizeButtonState({}, ctaBlocksState[blockIndex].buttons.length));
                renderCtaBlocks();
            });

            ctaBlocksBuilder.on('click', '.remove-cta-button', function () {
                const blockIndex = Number($(this).data('block-index'));
                const buttonIndex = Number($(this).data('button-index'));

                ctaBlocksState = syncSequentialSortOrders(readStateFromDom());
                ctaBlocksState[blockIndex].buttons = ctaBlocksState[blockIndex].buttons.filter((_, index) => index !== buttonIndex);

                if (! ctaBlocksState[blockIndex].buttons.length) {
                    ctaBlocksState[blockIndex].buttons = [normalizeButtonState({}, 0)];
                }

                renderCtaBlocks();
            });

            ctaBlocksBuilder.on('click', '.move-cta-button-up, .move-cta-button-down', function () {
                const blockIndex = Number($(this).data('block-index'));
                const buttonIndex = Number($(this).data('button-index'));
                const direction = $(this).hasClass('move-cta-button-up') ? -1 : 1;

                ctaBlocksState = syncSequentialSortOrders(readStateFromDom());
                const buttons = ctaBlocksState[blockIndex].buttons;
                const targetIndex = buttonIndex + direction;

                if (targetIndex < 0 || targetIndex >= buttons.length) {
                    return;
                }

                [buttons[buttonIndex], buttons[targetIndex]] = [buttons[targetIndex], buttons[buttonIndex]];
                renderCtaBlocks();
            });

            ctaBlocksBuilder.on('input change', '.cta-button-url-input', function () {
                refreshCtaWarnings();
            });

            ctaBlocksBuilder.on('input change', '.cta-button-icon-input', function () {
                const input = $(this);
                input.closest('.cta-button-item').find('[data-field="button-icon"]').val(input.val());
            });

            relatedProductsSelect.closest('form').on('submit', function () {
                syncRelatedProducts();
                ctaBlocksBuilder.find('.cta-button-item').each(function () {
                    const button = $(this);
                    button.find('[data-field="button-icon"]').val(button.find('[data-field="button-icon-display"]').val());
                });
            });

            ClassicEditor
                .create(document.querySelector('#description-editor'), {
                    ckfinder: {
                        uploadUrl: '{{ route('blogs.upload.image') }}?_token=' + document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '&blog_id={{ (isset($blog->id) && $blog->id) ?: 0 }}',
                    }
                })
                .then(editor => {
                    console.log(editor);
                })
                .catch(error => {
                    console.error(error);
                });

            setExistingBlockButtonState();
            renderCtaBlocks();
        });
    </script>

    <script>
        function SetSEOPreview() {
            let title = $('#title-input').val();
            $('#slug-input').val(slugify(title));
            window.refreshBlogCtaWarnings?.();
        }

        function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    $('#image-view')
                        .attr('src', e.target.result);
                };

                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
@endpush
