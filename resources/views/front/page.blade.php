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
                      <small><strong>AKCIJA: 20% popusta</strong> na sve uz kod: <strong>INTERLIBER20</strong> -  <strong>Važno:</strong> <a href="https://www.zuzi.hr/info/uvjeti-dostave"><!--<strong>Rok isporuke </strong></a> za regionalne naslove je do 20 dana.--> Zbog velikog interesa isporuka može malo kasniti. Radimo brzo — vi samo uživajte. ❤️ </small>

                  </div>
              </div>
          </div>
      </section>
      <h1 style="visibility: hidden;height:1px "> ZUZI Shop | Prodaja knjiga | Otkup knjiga | Webshop </h1>

        {!! $page->description !!}



      <section>

          <div class="d-flex flex-wrap justify-content-between align-items-center pt-1  pb-3 mb-2"><h3 class="h3 mb-0 pt-0 font-title me-3"> Virtualna šetnja </h3> </div>


          <iframe width="100%" height="563" style="border: none" frameborder="0" allow="autoplay; clipboard-write; encrypted-media;
fullscreen; gyroscope; picture-in-picture" src="https://virtualtours.virtualno360.hr/F1tEg2Htxw/p&amp;0h&amp;85.17t/"></iframe>
      </section>
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
