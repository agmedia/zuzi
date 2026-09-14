@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Sajamske akcije</h1>
                <a class="btn btn-hero-success my-2" href="{{ route('marketing.fair-discounts.create') }}">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> Nova sajamska akcija</span>
                </a>
            </div>
        </div>
    </div>

    <div class="content">
        @include('back.layouts.partials.session')

        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Sajamske akcije ({{ $actions->total() }})</h3>
            </div>

            <div class="block-content">
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter">
                        <thead>
                        <tr>
                            <th class="text-left">Naziv</th>
                            <th>Pragovi</th>
                            <th>Vrijedi od</th>
                            <th>Vrijedi do</th>
                            <th>BOX NOW</th>
                            <th class="text-center">Status</th>
                            <th class="text-right" style="width: 10%;">Uredi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($actions as $action)
                            <tr>
                                <td class="font-size-sm">
                                    <a class="font-w600" href="{{ route('marketing.fair-discounts.edit', ['fairDiscount' => $action]) }}">{{ $action->title }}</a>
                                </td>
                                <td class="font-size-sm">{{ $action->selection_text ?: '...' }}</td>
                                <td class="font-size-sm">{{ $action->date_start ? \Illuminate\Support\Carbon::make($action->date_start)->format('d.m.Y') : '...' }}</td>
                                <td class="font-size-sm">{{ $action->date_end ? \Illuminate\Support\Carbon::make($action->date_end)->format('d.m.Y') : '...' }}</td>
                                <td class="font-size-sm">
                                    @include('back.layouts.partials.status', ['status' => (bool) data_get($action->data, 'free_boxnow'), 'simple' => true])
                                </td>
                                <td class="text-center font-size-sm">
                                    @include('back.layouts.partials.status', ['status' => $action->status, 'simple' => true])
                                </td>
                                <td class="text-right font-size-sm">
                                    <a class="btn btn-sm btn-alt-secondary" href="{{ route('marketing.fair-discounts.edit', ['fairDiscount' => $action]) }}">
                                        <i class="fa fa-fw fa-pencil-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="font-size-sm text-center" colspan="7">
                                    <label>Nema sajamskih akcija...</label>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $actions->links() }}
            </div>
        </div>
    </div>
@endsection
