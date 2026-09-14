@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}">
@endpush

@section('content')
    @php
        $actionData = isset($fairDiscount) && is_array($fairDiscount->data) ? $fairDiscount->data : [];
        $formTiers = collect(old('tiers', $tiers ?? []))->values();
        $statusChecked = $errors->any()
            ? old('status') === 'on'
            : (isset($fairDiscount) && (bool) $fairDiscount->status);
        $freeBoxNowChecked = $errors->any()
            ? old('free_boxnow') === 'on'
            : (isset($fairDiscount) ? (bool) data_get($actionData, 'free_boxnow') : (bool) data_get($defaults ?? [], 'free_boxnow'));

        if ($formTiers->isEmpty()) {
            $formTiers = collect([['min_total' => 0, 'max_total' => null, 'discount' => 10]]);
        }
    @endphp

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Sajamska akcija</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('marketing.fair-discounts') }}">Sajamske akcije</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($fairDiscount) ? 'Uredi akciju' : 'Nova akcija' }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <form action="{{ isset($fairDiscount) ? route('marketing.fair-discounts.update', ['fairDiscount' => $fairDiscount]) : route('marketing.fair-discounts.store') }}" method="POST">
            @csrf
            @if (isset($fairDiscount))
                {{ method_field('PATCH') }}
            @endif

            <div class="row">
                <div class="col-md-9">
                    <div class="block">
                        <div class="block-header block-header-default">
                            <a class="btn btn-light" href="{{ route('marketing.fair-discounts') }}">
                                <i class="fa fa-arrow-left mr-1"></i> Povratak
                            </a>
                            <div class="block-options">
                                <div class="custom-control custom-switch custom-control-success block-options-item ml-4">
                                    <input type="checkbox" class="custom-control-input" id="status-switch" name="status" @if ($statusChecked) checked @endif>
                                    <label class="custom-control-label pt-1" for="status-switch">Aktiviraj</label>
                                </div>
                            </div>
                        </div>

                        <div class="block-content">
                            <div class="form-group">
                                <label for="title-input">Naziv akcije <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title-input" name="title" placeholder="Upišite naziv akcije" value="{{ old('title', isset($fairDiscount) ? $fairDiscount->title : data_get($defaults ?? [], 'title', 'Sajamski popust')) }}">
                            </div>

                            <div class="form-group">
                                <label for="date-start-input">Akcija vrijedi</label>
                                <div class="input-daterange input-group" data-date-format="dd.mm.yyyy" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                    <input type="text" class="form-control" id="date-start-input" name="date_start"
                                           value="{{ old('date_start', isset($fairDiscount) && $fairDiscount->date_start ? \Illuminate\Support\Carbon::make($fairDiscount->date_start)->format('d.m.Y') : data_get($defaults ?? [], 'date_start', '')) }}"
                                           placeholder="od" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                    <div class="input-group-prepend input-group-append">
                                        <span class="input-group-text font-w600"><i class="fa fa-fw fa-arrow-right"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="date-end-input" name="date_end"
                                           value="{{ old('date_end', isset($fairDiscount) && $fairDiscount->date_end ? \Illuminate\Support\Carbon::make($fairDiscount->date_end)->format('d.m.Y') : data_get($defaults ?? [], 'date_end', '')) }}"
                                           placeholder="do" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-switch custom-control-success">
                                    <input type="checkbox" class="custom-control-input" id="free-boxnow-switch" name="free_boxnow" @if ($freeBoxNowChecked) checked @endif>
                                    <label class="custom-control-label" for="free-boxnow-switch">Besplatna BOX NOW dostava dok akcija traje</label>
                                </div>
                            </div>

                            <div class="form-group mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="mb-0">Pragovi prema ukupnoj vrijednosti artikala <span class="text-danger">*</span></label>
                                    <button type="button" class="btn btn-sm btn-alt-primary" id="add-fair-tier">
                                        <i class="fa fa-plus mr-1"></i> Dodaj prag
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-vcenter mb-0">
                                        <thead>
                                        <tr>
                                            <th>Iznos od</th>
                                            <th>Iznos do</th>
                                            <th>Popust na artikle</th>
                                            <th class="text-right" style="width: 70px;">Ukloni</th>
                                        </tr>
                                        </thead>
                                        <tbody id="fair-tier-rows" data-next-index="{{ $formTiers->count() }}">
                                        @foreach ($formTiers as $index => $tier)
                                            <tr class="fair-tier-row">
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" min="0" max="999999.99" step="0.01" class="form-control" name="tiers[{{ $index }}][min_total]" value="{{ data_get($tier, 'min_total') }}" required>
                                                        <div class="input-group-append"><span class="input-group-text">€</span></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" min="0" max="999999.99" step="0.01" class="form-control" name="tiers[{{ $index }}][max_total]" value="{{ data_get($tier, 'max_total') }}" placeholder="bez granice">
                                                        <div class="input-group-append"><span class="input-group-text">€</span></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" min="0.01" max="100" step="0.01" class="form-control" name="tiers[{{ $index }}][discount]" value="{{ data_get($tier, 'discount') }}" required>
                                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                                    </div>
                                                </td>
                                                <td class="text-right">
                                                    <button type="button" class="btn btn-sm btn-alt-danger remove-fair-tier"><i class="fa fa-fw fa-trash-alt"></i></button>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <small class="form-text text-muted">Ostavite “Iznos do” prazan za zadnji, neograničeni prag.</small>
                            </div>

                            <div class="alert alert-info mt-4 mb-0">
                                Popust se automatski obračunava na vrijednost artikala u košarici. Poklon-bonovi i zamatanje se ne računaju, a akcija se ne kombinira s kuponima. BOX NOW može biti besplatan neovisno o odabranom pragu.
                            </div>
                        </div>

                        <div class="block-content bg-body-light">
                            <div class="row">
                                <div class="col-md-6">
                                    <button type="submit" class="btn btn-hero-success mb-3"><i class="fas fa-save mr-1"></i> Snimi</button>
                                </div>
                                @if (isset($fairDiscount))
                                    <div class="col-md-6 text-right">
                                        <a href="{{ route('marketing.fair-discounts.destroy', ['fairDiscount' => $fairDiscount]) }}" class="btn btn-hero-danger my-2" onclick="event.preventDefault(); document.getElementById('delete-fair-discount-form{{ $fairDiscount->id }}').submit();">
                                            <i class="fa fa-trash-alt"></i> Obriši
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @if (isset($fairDiscount))
            <form id="delete-fair-discount-form{{ $fairDiscount->id }}" action="{{ route('marketing.fair-discounts.destroy', ['fairDiscount' => $fairDiscount]) }}" method="POST" style="display: none;">
                @csrf
                {{ method_field('DELETE') }}
            </form>
        @endif
    </div>

    <script type="text/template" id="fair-tier-template">
        <tr class="fair-tier-row">
            <td>
                <div class="input-group">
                    <input type="number" min="0" max="999999.99" step="0.01" class="form-control" name="tiers[__INDEX__][min_total]" value="" required>
                    <div class="input-group-append"><span class="input-group-text">€</span></div>
                </div>
            </td>
            <td>
                <div class="input-group">
                    <input type="number" min="0" max="999999.99" step="0.01" class="form-control" name="tiers[__INDEX__][max_total]" value="" placeholder="bez granice">
                    <div class="input-group-append"><span class="input-group-text">€</span></div>
                </div>
            </td>
            <td>
                <div class="input-group">
                    <input type="number" min="0.01" max="100" step="0.01" class="form-control" name="tiers[__INDEX__][discount]" value="" required>
                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                </div>
            </td>
            <td class="text-right">
                <button type="button" class="btn btn-sm btn-alt-danger remove-fair-tier"><i class="fa fa-fw fa-trash-alt"></i></button>
            </td>
        </tr>
    </script>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"></script>
    <script>jQuery(function(){Dashmix.helpers(['datepicker']);});</script>
    <script>
        $(() => {
            $('#add-fair-tier').on('click', function () {
                let rows = $('#fair-tier-rows');
                let nextIndex = parseInt(rows.attr('data-next-index'), 10);

                if (Number.isNaN(nextIndex)) {
                    nextIndex = rows.find('.fair-tier-row').length;
                }

                rows.append($('#fair-tier-template').html().replaceAll('__INDEX__', nextIndex));
                rows.attr('data-next-index', nextIndex + 1);
            });

            $(document).on('click', '.remove-fair-tier', function () {
                if ($('#fair-tier-rows .fair-tier-row').length <= 1) {
                    return;
                }

                $(this).closest('.fair-tier-row').remove();
            });
        });
    </script>
@endpush
