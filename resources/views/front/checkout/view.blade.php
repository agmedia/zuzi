
@extends('front.layouts.app')

@push('css_after')
    @livewireStyles
@endpush

@section('content')

    <!-- Page title + breadcrumb-->
    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            <li class="breadcrumb-item text-nowrap active" aria-current="page">Potvrdite narudžbu</li>
        </ol>
    </nav>
    <!-- Content-->
    <!-- Sorting-->
    <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">
        <h1 class="h2 mb-3 mb-md-0 me-3">Potvrdite narudžbu</h1>

    </section>
            <div class="row">

                <section class="col-lg-12">
                    <div class="steps steps-dark pt-2 pb-3 mb-2">
                        <a class="step-item active" href="{{ route('kosarica') }}">
                            <div class="step-progress"><span class="step-count">1</span></div>
                            <div class="step-label"><i class="ci-cart"></i>Košarica</div>
                        </a>
                        <a class="step-item active" href="{{ route('naplata', ['step' => 'podaci']) }}">
                            <div class="step-progress"><span class="step-count">2</span></div>
                            <div class="step-label"><i class="ci-user-circle"></i>Podaci</div>
                        </a>
                        <a class="step-item active" href="{{ route('naplata', ['step' => 'dostava']) }}">
                            <div class="step-progress"><span class="step-count">3</span></div>
                            <div class="step-label"><i class="ci-package"></i>Dostava</div>
                        </a>
                        <a class="step-item active" href="{{ route('naplata', ['step' => 'placanje']) }}">
                            <div class="step-progress"><span class="step-count">4</span></div>
                            <div class="step-label"><i class="ci-card"></i>Plaćanje</div>
                        </a>
                        <a class="step-item current active" href="{{ route('pregled') }}">
                            <div class="step-progress"><span class="step-count">5</span></div>
                            <div class="step-label"><i class="ci-check-circle"></i>Pregledaj</div>
                        </a>
                    </div>
                </section>

            </div>

    <div class="pb-5 mb-2 mt-5 mb-md-4">
        <div class="row">

            <section class="col-lg-8">
                <div class="card p-3">
               <h2 class="h5 pt-1 pb-3 mb-3">Pregledaj i potvrdi narudžbu</h2>
                <cart-view continueurl="{{ route('index') }}" checkouturl="{{ route('naplata') }}" buttons="false"></cart-view>

                <div class=" pt-4 pb-2">
                    <div class="row">
                        <div class="col-sm-6">
                            <h4 class="h6">Platitelj:</h4>
                            <ul class="list-unstyled fs-sm">
                                @if (auth()->guest())
                                    <li><span class="text-muted">Korisnik:&nbsp;</span>{{ $data['address']['fname'] }} {{ $data['address']['lname'] }}</li>
                                    <li><span class="text-muted">Adresa:&nbsp;</span>{{ $data['address']['address'] }}, {{ $data['address']['zip'] }} {{ $data['address']['city'] }}, {{ $data['address']['state'] }}</li>
                                    <li><span class="text-muted">Email:&nbsp;</span>{{ $data['address']['email'] }}</li>
                                @else
                                    <li><span class="text-muted">Korisnik:&nbsp;</span>{{ auth()->user()->details->fname }} {{ auth()->user()->details->lname }}</li>
                                    <li><span class="text-muted">Adresa:&nbsp;</span>{{ auth()->user()->details->address }}, {{ auth()->user()->details->zip }} {{ auth()->user()->details->city }}, {{ $data['address']['state'] }}</li>
                                    <li><span class="text-muted">Email:&nbsp;</span>{{ auth()->user()->email }}</li>
                                @endif
                            </ul>
                        </div>
                        <div class="col-sm-6">
                            <h4 class="h6">Dostaviti na:</h4>
                            <ul class="list-unstyled fs-sm">
                                <li><span class="text-muted">Korisnik:&nbsp;</span>{{ $data['address']['fname'] }} {{ $data['address']['lname'] }}</li>
                                <li><span class="text-muted">Adresa:&nbsp;</span>{{ $data['address']['address'] }}, {{ $data['address']['zip'] }} {{ $data['address']['city'] }}, {{ $data['address']['state'] }}</li>
                                <li><span class="text-muted">Email:&nbsp;</span>{{ $data['address']['email'] }}</li>
                            </ul>
                        </div>
                        <div class="col-sm-6">
                            <h4 class="h6">Način dostave:</h4>
                            <ul class="list-unstyled fs-sm">
                                <li>
                                    <span class="text-muted">{{ $data['shipping']->title }} </span><br>
                                    {{ $data['shipping']->data->description ?: $data['shipping']->data->short_description }}
                                </li>
                            </ul>
                        </div>
                        <div class="col-sm-6">
                            <h4 class="h6">Način plaćanja:</h4>
                            <ul class="list-unstyled fs-sm">
                                <li>
                                    <span class="text-muted">{{ $data['payment']->title }} </span><br>
                                    {{ (isset($data['payment']->data->description) && $data['payment']->data->description) ?: $data['payment']->data->short_description }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                </div>
                <div class="d-none d-lg-flex pt-0 mt-3">
                    {!! $data['payment_form'] !!}
                </div>

            </section>

            <aside class="col-lg-4 pt-4 pt-lg-0 mb-3 ps-xl-5 d-block">
                <cart-view-aside route="pregled" continueurl="{{ route('index') }}" checkouturl="/"></cart-view-aside>
            </aside>
        </div>

        <div class="row d-lg-none">
            <div class="col-lg-8">
                {!! $data['payment_form'] !!}
            </div>
        </div>
    </div>

    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable ">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Opći uvjeti korištenja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @foreach ($uvjeti_kupnje as $uvjet)
                        {!! $uvjet->description !!}

                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zatvori</button>

                </div>
            </div>
        </div>
    </div>

@endsection

@push('js_after')
    @if ($data['payment']->code == 'keks')
        <script type="text/javascript">
            let refreshTime = 3000;

            function checkKeksResponse() {
                $.ajax({
                    method: 'post',
                    url: '{{ route('keks.provjera') }}',
                    data: {
                        order_id: '{{ $data['id'] }}',
                        _token: "{{ csrf_token() }}"
                    },
                    dataType: 'json',
                    success: function(json) {
                        if (json.status) {
                            document.location = json.redirect;
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
                    },
                    complete: function (data) {
                        setTimeout(checkKeksResponse, refreshTime);
                    }
                });
            }

            setTimeout(checkKeksResponse, refreshTime);
        </script>
    @endif
@endpush
