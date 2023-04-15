@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">History Log</h1>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="row no-gutters flex-md-10-auto">
        <div class="col-md-12 order-md-0 bg-body-dark">
            <!-- Main Content -->
            <div class="content content-full">
            @include('back.layouts.partials.session')
                <div class="block block-rounded">
                    <div class="block-header block-header-default">
                        <div class="block-content">
                            <form action="{{ route('history') }}" method="GET">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <select class="js-select2 form-control" id="trazi-select" name="trazi" style="width: 100%;" data-placeholder="Odaberi model pretrage">
                                                <option></option><!-- Required for data-placeholder attribute to work with Select2 plugin -->
                                                <option value="knjige" {{ 'knjige' == request()->input('trazi') ? 'selected' : '' }}>Knjige</option>
                                                <option value="narudzba" {{ 'narudzba' == request()->input('trazi') ? 'selected' : '' }}>Narudžbe</option>
                                                <option value="autor" {{ 'autor' == request()->input('trazi') ? 'selected' : '' }}>Autori</option>
                                                <option value="nakladnik" {{ 'nakladnik' == request()->input('trazi') ? 'selected' : '' }}>Nakladnici</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <input type="text" class="form-control" id="search-input" name="pojam" placeholder="Pretraži..." value="{{ request()->query('pojam') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary btn-block fs-base"><i class="fa fa-search"></i> Traži</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="accordion" role="tablist" aria-multiselectable="true">
                    @forelse($history as $item)
                        <div class="block block-rounded mb-1">
                            <div class="block-header block-header-default" role="tab" id="accordion_h{{ $item->id }}">
                                <a class="h4 block-title" style="line-height: 1.4rem;" data-toggle="collapse" data-parent="#accordion" href="#accordion_q{{ $item->id }}" aria-expanded="false{{--@if($loop->first) true @else false @endif--}}" aria-controls="accordion_q{{ $item->id }}">
                                    {!! $item->title !!}<br>
                                    <span class="font-weight-lighter" style="font-size: .72rem;">Korisnik: <strong class="text-info">{{ $item->user()->name }}</strong></span>
                                    <span class="font-weight-lighter" style="font-size: .72rem; margin-left: 18px;">Datum: <strong class="text-info">{{ $item->created_at->format('d.m.Y - h:i') }}</strong></span>
                                </a>
                                <div class="block-options">
                                    <div class="btn-group">
                                        <a href="{{ route('history.show', ['history' => $item->id]) }}" class="btn btn-sm btn-secondary js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="Uredi">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div id="accordion_q{{ $item->id }}" class="collapse {{--@if($loop->first) show @endif--}}" role="tabpanel" aria-labelledby="accordion_h{{ $item->id }}" data-parent="#accordion">
                                <div class="block-content pb-4">
                                    {!! $item->changes !!}
                                </div>
                            </div>
                        </div>
                    @empty
                        <h3>Nemate niti jedan history log...</h3>
                    @endforelse
                </div>
                {{ $history->links() }}
            </div>
        </div>
    </div>
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>

    <script>
        $(() => {
            $('#trazi-select').select2({
                placeholder: 'Pretraži logove prema...',
                allowClear: true
            });
            $('#trazi-select').on('change', (e) => {
                setURL('trazi', e.currentTarget.selectedOptions[0]);
            });
        });

        /**
         *
         * @param type
         * @param search
         * @param isValue
         */
        function setURL(type, search, isValue = false) {
            let url = new URL(location.href);
            let params = new URLSearchParams(url.search);
            let keys = [];

            for(var key of params.keys()) {
                if (key === type) {
                    keys.push(key);
                }
            }

            keys.forEach((value) => {
                if (params.has(value)) {
                    params.delete(value);
                }
            })

            if (search.value) {
                params.append(type, search.value);
            }

            if (isValue && search) {
                params.append(type, search);
            }

            url.search = params;
            location.href = url;
        }
    </script>
@endpush
