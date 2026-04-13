@extends('front.layouts.app')
@php($pageSeo = \App\Models\Seo::getPageData($page))
@if (request()->routeIs(['index']))
    @section('title', \App\Models\Seo::defaultTitle())
    @section('description', \App\Models\Seo::defaultDescription())
    @section('seo_image', \App\Models\Seo::defaultImage())
    @section('seo_image_alt', \App\Models\Seo::defaultTitle())
    @section('seo_updated_time', optional($page->updated_at)->toAtomString())
@else
    @section('title', $pageSeo['title'])
    @section('description', $pageSeo['description'])
    @if (! empty($page->image))
        @section('seo_image', \App\Models\Seo::image($page->image))
    @endif
    @section('seo_image_alt', $page->title)
    @section('seo_updated_time', optional($page->updated_at)->toAtomString())
@endif

@section('content')

    @if (request()->routeIs(['index']))


      <div class="col-md-12 d-flex justify-content-between d-md-none mt-3">

          <a href="{{ route('savjeti.za.poklone') }}"
             class="btn btn-outline-dark flex-fill mx-1 d-flex align-items-center justify-content-center">
              <i class="ci-gift me-2"></i>Tražiš poklon?
          </a>

          <a href="{{ route('poklon.bon') }}"
             class="btn btn-primary flex-fill mx-1 d-flex align-items-center justify-content-center">
              <i class="ci-card me-2"></i>Poklon bon
          </a>

      </div>

        {!! $page->description !!}



      <section>

          <div class="d-flex flex-wrap justify-content-between align-items-center pt-1  pb-3 mb-2"><h3 class="h3 mb-0 pt-0 font-title me-3"> Virtualna šetnja </h3> </div>


          <iframe width="100%" height="563" style="border: none" frameborder="0" allow="autoplay; clipboard-write; encrypted-media;
fullscreen; gyroscope; picture-in-picture" src="https://virtualtours.virtualno360.hr/F1tEg2Htxw/p&amp;0h&amp;85.17t/"></iframe>
      </section>
      @push('js_after')
          <script type="application/ld+json">
              {!! collect(\App\Helpers\Metatags::organizationSchema())->toJson() !!}
          </script>
          <script type="application/ld+json">
              {!! collect(\App\Helpers\Metatags::websiteSchema())->toJson() !!}
          </script>
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
