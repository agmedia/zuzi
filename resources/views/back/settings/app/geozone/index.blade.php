@extends('back.layouts.backend')

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Geo Zone</h1>
                <a class="btn btn-hero-success my-2" href="{{ route('geozones.create') }}">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> Dodaj novu
                </a>
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <div class="block">
            <div class="block-header block-header-default">
                <h3 class="block-title">Lista</h3>
            </div>
            <div class="block-content">
                <table class="table table-striped table-borderless table-vcenter">
                    <thead class="thead-light">
                    <tr>
                        <th style="width: 80%;">Naziv</th>
                        <th class="text-center" style="width: 15%;">Status</th>
                        <th class="text-right">Uredi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($geo_zones as $geo_zone)
                        <tr>
                            <td>
                                <a href="{{ route('geozones.edit', ['geozone' => $geo_zone->id]) }}">{{ $geo_zone->title }}</a>
                            </td>
                            <td class="text-center">
                                @include('back.layouts.partials.status', ['status' => $geo_zone->status])
                            </td>
                            <td class="text-right font-size-sm">
                                <a class="btn btn-sm btn-alt-secondary" href="{{ route('geozones.edit', ['geozone' => $geo_zone->id]) }}">
                                    <i class="fa fa-fw fa-pencil-alt"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr class="text-center">
                            <td colspan="2">Nema geo zona...</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                {{--{{ $geo_zones->links() }}--}}
            </div>
        </div>
    </div>
@endsection

@push('js_after')

@endpush
