@extends('front.layouts.app')
@if (request()->routeIs(['index']))
    @section ( 'title', 'ZUZI Shop | Prodaja knjiga | Otkup knjiga | Webshop' )
    @section ( 'description', 'Zuzi shop - Nudimo Vam praktičnu mogućnost pretraživanja i naručivanja željenih naslova putem web stranice zuzi.hr iz udobnosti naslonjača.' )


    @push('meta_tags')

        <link rel="canonical" href="{{ env('APP_URL')}}" />
        <meta property="og:locale" content="hr_HR" />
        <meta property="og:type" content="product" />
        <meta property="og:title" content="ZUZI Shop | Prodaja knjiga | Otkup knjiga | Webshop" />
        <meta property="og:description" content="Zuzi shop - Nudimo Vam praktičnu mogućnost pretraživanja i naručivanja željenih naslova putem web stranice zuzi.hr iz udobnosti naslonjača." />
        <meta property="og:url" content="{{ env('APP_URL')}}"  />
        <meta property="og:site_name" content="ZUZI Shop | Prodaja knjiga | Otkup knjiga | Webshop" />
        <meta property="og:image" content="{{ asset('media/img/cover-zuzi.jpg') }}" />
        <meta property="og:image:secure_url" content="{{ asset('media/img/cover-zuzi.jpg') }}" />
        <meta property="og:image:width" content="1920" />
        <meta property="og:image:height" content="720" />
        <meta property="og:image:type" content="image/jpeg" />
        <meta property="og:image:alt" content="ZUZI Shop | Prodaja knjiga | Otkup knjiga | Webshop" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="ZUZI Shop | Prodaja knjiga | Otkup knjiga | Webshop" />
        <meta name="twitter:description" content="Zuzi shop - Nudimo Vam praktičnu mogućnost pretraživanja i naručivanja željenih naslova putem web stranice zuzi.hr iz udobnosti naslonjača." />
        <meta name="twitter:image" content="{{ asset('media/img/cover-zuzi.jpg') }}" />

    @endpush

@else
    @section ( 'title', $page->title. ' - ZUZI Shop' )
    @section ( 'description', $page->meta_description )
@endif

@section('content')

    @if (request()->routeIs(['index']))

      {{--@include('front.layouts.partials.hometemp') --}}
      <section >
          <div class="d-flex row justify-content-between">

              <div class="col-md-12">
                  <div class="alert alert-info d-flex  mb-1 " role="alert">
                      <div class="alert-icon">
                          <i class="ci-gift"></i>
                      </div>
                      <small>Besplatna dostava za narudžbe > 70€</small>
                  </div>
              </div>
          </div>
      </section>
      <h1 style="visibility: hidden;height:1px "> ZUZI Shop | Prodaja knjiga | Otkup knjiga | Webshop </h1>

        {!! $page->description !!}



      @push('js_after')
          <style>
              @media only screen and (max-width: 1040px) {
                  .scrolling-wrapper {
                      overflow-x: scroll;
                      overflow-y: hidden;
                      white-space: nowrap;
                      padding-bottom: 15px;
                  }
              }
          </style>
      @endpush


    @else



        <nav class="mb-4" aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap">
                <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $page->title }}</li>
            </ol>
        </nav>


        <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">
            <h1 class="h2 mb-3 mb-md-0 me-3">{{ $page->title }}</h1>

        </section>



            <div class="mt-5 mb-5 fs-md" style="max-width:1240px">
                {!! $page->description !!}
            </div>


    @endif

@endsection
