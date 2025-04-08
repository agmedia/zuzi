@extends('front.layouts.app')

@push('css_after')
    @livewireStyles
@endpush

@section('content')




    <!-- Page title + breadcrumb-->
    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            <li class="breadcrumb-item text-nowrap active" aria-current="page">Košarica</li>
        </ol>
    </nav>
    <!-- Content-->
    <!-- Sorting-->
    <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">
        <h1 class="h2 mb-3 mb-md-0 me-3">Košarica</h1>

    </section>

    <div class=" pb-5 mb-2 mb-md-4">
        <div class="row">
            <section class="col-lg-8">
                @livewire('front.checkout', ['step' => $step, 'is_free_shipping' => $is_free_shipping])
            </section>
            <!-- Sidebar-->
            <aside class="col-lg-4 pt-4 pt-lg-0 ps-xl-5 d-none d-lg-block">
                <cart-view-aside route="naplata" continueurl="{{ \Illuminate\Support\Facades\URL::previous() }}" checkouturl="{{ route('naplata') }}"></cart-view-aside>
            </aside>
        </div>
    </div>

@endsection

@push('js_after')
    @livewireScripts
@endpush
