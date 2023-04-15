@extends('back.layouts.backend')

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Postavke aplikacije</h1>
            </div>
        </div>
    </div>

    @include('back.layouts.partials.session')

@endsection

@push('js_after')

@endpush
