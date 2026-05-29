@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}">
@endpush

@section('content')
    @php
        $formTiers = old('tiers', $tiers ?? []);
        $formTiers = collect($formTiers)->values();
        $statusChecked = $errors->any()
            ? old('status') === 'on'
            : (isset($bogo) && (bool) $bogo->status);

        if ($formTiers->isEmpty()) {
            $formTiers = collect([['quantity' => 2, 'discount' => 5]]);
        }
    @endphp

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">BOGO edit</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('marketing.bogo') }}">BOGO</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ isset($bogo) ? 'Uredi akciju' : 'Nova akcija' }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <form action="{{ isset($bogo) ? route('marketing.bogo.update', ['bogo' => $bogo]) : route('marketing.bogo.store') }}" method="POST">
            @csrf
            @if (isset($bogo))
                {{ method_field('PATCH') }}
            @endif

            <div class="row">
                <div class="col-md-8">
                    <div class="block">
                        <div class="block-header block-header-default">
                            <a class="btn btn-light" href="{{ route('marketing.bogo') }}">
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
                                <input type="text" class="form-control" id="title-input" name="title" placeholder="Upišite naziv akcije" value="{{ old('title', isset($bogo) ? $bogo->title : 'BOGO količinski popust') }}">
                            </div>

                            <div class="form-group">
                                <label for="date-start-input">Akcija vrijedi</label>
                                <div class="input-daterange input-group" data-date-format="dd.mm.yyyy" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                    <input type="text" class="form-control" id="date-start-input" name="date_start"
                                           value="{{ old('date_start', isset($bogo) && $bogo->date_start ? \Illuminate\Support\Carbon::make($bogo->date_start)->format('d.m.Y') : '') }}"
                                           placeholder="od" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                    <div class="input-group-prepend input-group-append">
                                        <span class="input-group-text font-w600">
                                            <i class="fa fa-fw fa-arrow-right"></i>
                                        </span>
                                    </div>
                                    <input type="text" class="form-control" id="date-end-input" name="date_end"
                                           value="{{ old('date_end', isset($bogo) && $bogo->date_end ? \Illuminate\Support\Carbon::make($bogo->date_end)->format('d.m.Y') : '') }}"
                                           placeholder="do" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                </div>
                            </div>

                            <div class="form-group mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="mb-0">Pragovi popusta <span class="text-danger">*</span></label>
                                    <button type="button" class="btn btn-sm btn-alt-primary" id="add-bogo-tier">
                                        <i class="fa fa-plus mr-1"></i> Dodaj prag
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-vcenter mb-0">
                                        <thead>
                                        <tr>
                                            <th>Broj artikala</th>
                                            <th>Popust na sve</th>
                                            <th class="text-right" style="width: 70px;">Ukloni</th>
                                        </tr>
                                        </thead>
                                        <tbody id="bogo-tier-rows" data-next-index="{{ $formTiers->count() }}">
                                        @foreach ($formTiers as $index => $tier)
                                            <tr class="bogo-tier-row">
                                                <td>
                                                    <input type="number" min="1" step="1" class="form-control" name="tiers[{{ $index }}][quantity]" value="{{ data_get($tier, 'quantity') }}" required>
                                                </td>
                                                <td>
                                                    <div class="input-group">
                                                        <input type="number" min="0.01" max="100" step="0.01" class="form-control" name="tiers[{{ $index }}][discount]" value="{{ data_get($tier, 'discount') }}" required>
                                                        <div class="input-group-append">
                                                            <span class="input-group-text">%</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-right">
                                                    <button type="button" class="btn btn-sm btn-alt-danger remove-bogo-tier">
                                                        <i class="fa fa-fw fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="alert alert-info mt-4 mb-0">
                                BOGO se automatski primjenjuje na artikle u košarici i ne kombinira se s kuponima.
                            </div>
                        </div>

                        <div class="block-content bg-body-light">
                            <div class="row">
                                <div class="col-md-6">
                                    <button type="submit" class="btn btn-hero-success mb-3">
                                        <i class="fas fa-save mr-1"></i> Snimi
                                    </button>
                                </div>
                                @if (isset($bogo))
                                    <div class="col-md-6 text-right">
                                        <a href="{{ route('marketing.bogo.destroy', ['bogo' => $bogo]) }}" class="btn btn-hero-danger my-2" onclick="event.preventDefault(); document.getElementById('delete-bogo-form{{ $bogo->id }}').submit();">
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

        @if (isset($bogo))
            <form id="delete-bogo-form{{ $bogo->id }}" action="{{ route('marketing.bogo.destroy', ['bogo' => $bogo]) }}" method="POST" style="display: none;">
                @csrf
                {{ method_field('DELETE') }}
            </form>
        @endif
    </div>

    <script type="text/template" id="bogo-tier-template">
        <tr class="bogo-tier-row">
            <td>
                <input type="number" min="1" step="1" class="form-control" name="tiers[__INDEX__][quantity]" value="" required>
            </td>
            <td>
                <div class="input-group">
                    <input type="number" min="0.01" max="100" step="0.01" class="form-control" name="tiers[__INDEX__][discount]" value="" required>
                    <div class="input-group-append">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </td>
            <td class="text-right">
                <button type="button" class="btn btn-sm btn-alt-danger remove-bogo-tier">
                    <i class="fa fa-fw fa-trash-alt"></i>
                </button>
            </td>
        </tr>
    </script>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"></script>
    <script>jQuery(function(){Dashmix.helpers(['datepicker']);});</script>
    <script>
        $(() => {
            $('#add-bogo-tier').on('click', function () {
                let rows = $('#bogo-tier-rows');
                let nextIndex = parseInt(rows.attr('data-next-index'), 10);

                if (Number.isNaN(nextIndex)) {
                    nextIndex = rows.find('.bogo-tier-row').length;
                }

                rows.append($('#bogo-tier-template').html().replaceAll('__INDEX__', nextIndex));
                rows.attr('data-next-index', nextIndex + 1);
            });

            $(document).on('click', '.remove-bogo-tier', function () {
                if ($('#bogo-tier-rows .bogo-tier-row').length <= 1) {
                    return;
                }

                $(this).closest('.bogo-tier-row').remove();
            });
        });
    </script>
@endpush
