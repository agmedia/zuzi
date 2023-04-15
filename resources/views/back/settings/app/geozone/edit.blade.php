@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Geo Zona edit</h1>
            </div>
        </div>
    </div>

    <div class="content content-full content-boxed">
        @include('back.layouts.partials.session')

        <form action="{{ isset($geo_zone) ? route('geozones.update', ['geozone' => $geo_zone->id]) : route('geozones.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if (isset($geo_zone))
                {{ method_field('PATCH') }}
            @endif
            <div class="block">
                <div class="block-header block-header-default">
                    <a class="btn btn-light" href="{{ back()->getTargetUrl() }}">
                        <i class="fa fa-arrow-left mr-1"></i> Povratak
                    </a>
                    <div class="block-options">
                        <div class="custom-control custom-switch custom-control-success">
                            <input type="checkbox" class="custom-control-input" id="geozone-switch" name="status"{{ (isset($geo_zone->status) and $geo_zone->status) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="geozone-switch">Status</label>
                        </div>
                    </div>
                </div>
                <div class="block-content">
                    <div class="row justify-content-center push">
                        <div class="col-md-10">

                            <div class="form-group mb-4">
                                <label for="title-input">Naslov</label>
                                <input type="text" class="form-control" id="title-input" name="title" placeholder="Upišite naslov..." value="{{ isset($geo_zone) ? $geo_zone->title : old('title') }}">
                            </div>

                            <div class="form-group mb-4">
                                <label for="description-input">Opis <span class="small text-gray">(Ako je potreban)</span></label>
                                <textarea class="form-control" id="description-input" name="description" rows="4">{{ isset($geo_zone) ? $geo_zone->description : old('description') }}</textarea>
                            </div>

                            @livewire('back.settings.states-addition', ['states' => isset($geo_zone) ? $geo_zone->state : []])

                            <input type="hidden" name="id" value="{{ isset($geo_zone) ? $geo_zone->id : 0 }}">

                        </div>
                    </div>
                </div>
                <div class="block-content bg-body-light">
                    <div class="row justify-content-center push">
                        <div class="col-md-10">
                            <button type="submit" class="btn btn-hero-success">
                                <i class="fas fa-save mr-1"></i> Snimi
                            </button>
                            @if (isset($geo_zone))
                                <a href="{{ route('geozones.destroy', ['geozone' => $geo_zone->id]) }}" type="submit" class="btn btn-hero-danger my-2 js-tooltip-enabled float-right" data-toggle="tooltip" title="" data-original-title="Obriši" onclick="event.preventDefault(); document.getElementById('delete-geozone-form{{ $geo_zone->id }}').submit();">
                                    <i class="fa fa-trash-alt"></i> Obriši
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @if (isset($geo_zone))
            <form id="delete-geozone-form{{ $geo_zone->id }}" action="{{ route('geozones.destroy', ['geozone' => $geo_zone->id]) }}" method="POST" style="display: none;">
                @csrf
                {{ method_field('DELETE') }}
            </form>
        @endif
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>

    <script>
        $(() => {
            $('#countries-select').select2({
                placeholder: "Odaberi državu..."
            });
        });

        function addState() {
            let selected = $('#countries-select').val();

            console.log(selected);
        }
    </script>

@endpush
