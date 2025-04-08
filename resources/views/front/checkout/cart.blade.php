@extends('front.layouts.app')

@if (isset($gdl))
    @section('google_data_layer')
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({
                'event': 'view_cart',
                'ecommerce': {'items': <?php echo json_encode($gdl); ?>}
            });
        </script>
    @endsection
@endif

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

<!-- Page Title-->




    <div class=" pb-5 mb-2 mb-md-4">
    <div class="row">

        <section class="col-lg-8">
            <div class="steps steps-dark pt-2 pb-3 mb-2">
                <a class="step-item current active" href="{{ route('kosarica') }}">
                    <div class="step-progress"><span class="step-count">1</span></div>
                    <div class="step-label"><i class="ci-cart"></i>Košarica</div>
                </a>
                <a class="step-item" href="{{ route('naplata', ['step' => 'podaci']) }}">
                    <div class="step-progress"><span class="step-count">2</span></div>
                    <div class="step-label"><i class="ci-user-circle"></i>Podaci</div>
                </a>
                <a class="step-item" href="{{ route('naplata', ['step' => 'dostava']) }}">
                    <div class="step-progress"><span class="step-count">3</span></div>
                    <div class="step-label"><i class="ci-package"></i>Dostava</div>
                </a>
                <a class="step-item" href="{{ route('naplata', ['step' => 'placanje']) }}">
                    <div class="step-progress"><span class="step-count">4</span></div>
                    <div class="step-label"><i class="ci-card"></i>Plaćanje</div>
                </a>
                <a class="step-item" href="{{ route('pregled') }}">
                    <div class="step-progress"><span class="step-count">5</span></div>
                    <div class="step-label"><i class="ci-check-circle"></i>Pregledaj</div>
                </a>
            </div>
            <div class="card px-3">
            <cart-view continueurl="{{ \Illuminate\Support\Facades\URL::previous() }}" checkouturl="{{ route('naplata') }}" freeship="{{ config('settings.free_shipping') }}"></cart-view>
            </div>

        </section>
        <!-- Sidebar-->
        <aside class="col-lg-4 pt-4 pt-lg-0 ps-xl-5">

            <cart-view-aside route="kosarica" continueurl="{{ \Illuminate\Support\Facades\URL::previous() }}" checkouturl="{{ route('naplata') }}"></cart-view-aside>
        </aside>
    </div>

</div>

@endsection
