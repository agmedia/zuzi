@extends('back.layouts.backend')
@push('css_before')

    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('js/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}">


@endpush

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Akcije</h1>
                <a class="btn btn-hero-success my-2" href="{{ route('actions.create') }}">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> Nova akcija</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="content">
    @include('back.layouts.partials.session')


        <!-- All Products -->
        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h3 class="block-title">Sve akcije ({{ $actions->total() }})</h3>
                <div class="block-options">
                    <div class="dropdown">
                        <button type="button" class="btn btn-light" id="dropdown-ecom-filters" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Filtriraj
                            <i class="fa fa-angle-down ml-1"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdown-ecom-filters">
                            @foreach (config('settings.actions_sorting_list') as $item)
                                <a class="dropdown-item d-flex align-items-center justify-content-between" href="@if($item['value']) javascript:setPageURL('{{ $item['type'] }}', '{{ $item['value'] }}') @else {{ route('actions') }} @endif">
                                    <span class="font-size-sm font-weight-bold">{{ $item['title'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>


            <div class="block-content">
                <!-- All Products Table -->
                <div class="table-responsive">
                    <table class="table table-borderless table-striped table-vcenter">
                        <thead>
                        <tr>
                            <th class="text-left">Naziv</th>
                            <th class="text-left">Grupa</th>
                            <th>Vrijedi od</th>
                            <th>Vrijedi do</th>
                            <th>Popust</th>
                            <th>Kupon</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Lock</th>
                            <th class="text-right" style="width: 10%;">Uredi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($actions as $action)
                            <tr>
                                <td class="font-size-sm">
                                    <a class="font-w600" href="{{ route('actions.edit', ['action' => $action]) }}">{{ $action->title }}</a>
                                </td>
                                <td class="font-size-sm">
                                    {{ $groups->where('id', $action->group)->first()->title }}
                                </td>
                                <td class="font-size-sm">{{ $action->date_start ? \Illuminate\Support\Carbon::make($action->date_start)->format('d.m.Y') : '...' }}</td>
                                <td class="font-size-sm">{{ $action->date_end ? \Illuminate\Support\Carbon::make($action->date_end)->format('d.m.Y') : '...' }}</td>
                                <td class="font-size-sm">{{ $action->discount_text }}</td>
                                <td class="font-size-sm">{{ $action->coupon }}</td>
                                <td class="text-center font-size-sm">
                                    @include('back.layouts.partials.status', ['status' => $action->status, 'simple' => true])
                                </td>
                                <td class="text-center font-size-sm">
                                    @if($action->lock)
                                        <i class="fa fa-lock text-danger-light"></i>
                                    @else
                                        <i class="fa fa-lock-open text-success-light"></i>
                                    @endif
                                </td>
                                <td class="text-right font-size-sm">
                                    <a class="btn btn-sm btn-alt-secondary" href="{{ route('actions.edit', ['action' => $action]) }}">
                                        <i class="fa fa-fw fa-pencil-alt"></i>
                                    </a>
                                    <button class="btn btn-sm btn-alt-danger" onclick="event.preventDefault(); deleteItem({{ $action->id }}, '{{ route('actions.destroy.api') }}');"><i class="fa fa-fw fa-trash-alt"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="font-size-sm text-center" colspan="9">
                                    <label for="">Nema Akcija...</label>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $actions->links() }}
            </div>
        </div>
        <!-- END All Products -->
    </div>
    <!-- END Page Content -->

@endsection

@push('js_after')


    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('js/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"></script>

    <!-- Page JS Helpers (CKEditor 5 plugins) -->
    <script>jQuery(function(){Dashmix.helpers(['select2','datepicker']);});</script>
    <script>
        $(() => {
            $('#category-select').select2({
                placeholder: 'Odaberite kategoriju'
            });
            $('#author-select').select2({
                placeholder: 'Odaberite autora'
            });
            $('#publisher-select').select2({
                placeholder: 'Odaberite izdavača'
            });
        })
    </script>
    <script>
        $("#checkAll").click(function () {
            $('input:checkbox').not(this).prop('checked', this.checked);
        });
    </script>

@endpush
