@extends('front.layouts.app')

@section('content')


    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            <li class="breadcrumb-item text-nowrap active" aria-current="page">Česta pitanja (FAQ)</li>
        </ol>
    </nav>


    <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">
        <h1 class="h2 mb-3 mb-md-0 me-3">Česta pitanja (FAQ)</h1>

    </section>







        <div class="mt-5 mb-5" style="max-width:1240px">

    <!-- Flush accordion. Use this when you need to render accordions edge-to-edge with their parent container -->
    <div class="accordion accordion-flush" id="accordionFlushExample">


    @foreach ($faq as $fa)

        <!-- Item -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="flush-heading{{ $fa->id }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapse{{ $fa->id }}" aria-expanded="false" aria-controls="flush-collapse{{ $fa->id }}">{{ $fa->title }}</button>
                </h2>
                <div class="accordion-collapse collapse" id="flush-collapse{{ $fa->id }}" aria-labelledby="flush-heading{{ $fa->id }}" data-bs-parent="#accordionFlushExample">
                    <div class="accordion-body">  {!! $fa->description !!}</div>
                </div>
            </div>

    @endforeach











    </div>

        </div>





@endsection
