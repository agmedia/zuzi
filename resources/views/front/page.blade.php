@extends('front.layouts.app')
@php($pageSeo = \App\Models\Seo::getPageData($page))
@if (request()->routeIs(['index']))
    @section('title', $pageSeo['title'])
    @section('description', $pageSeo['description'])
    @section('seo_image', ! empty($page->image) ? \App\Models\Seo::image($page->image) : \App\Models\Seo::defaultImage())
    @section('seo_image_alt', $page->title ?: $pageSeo['title'])
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

@push('css_after')
    <style>
        .home-promo-alert {
            gap: 0;
            padding: 0;
            overflow: hidden;
            border-color: #ffd0e2;
            background: #fff;
        }

        .home-promo-icon {
            display: inline-flex;
            flex: 0 0 4.4rem;
            align-items: center;
            justify-content: center;
            align-self: stretch;
            min-height: 100%;
            background: linear-gradient(180deg, #ffe9f3 0%, #ffdbe9 100%);
        }

        .home-promo-icon-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 2px 10px rgba(233, 30, 99, 0.14);
        }

        .home-promo-copy {
            padding: 0.8rem 0.95rem;
            color: #e50077;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .home-promo-copy strong {
            color: #e50077;
        }

        @media (max-width: 575.98px) {
            .home-promo-alert {
                align-items: stretch !important;
            }

            .home-promo-icon {
                flex-basis: 3.35rem;
            }

            .home-promo-icon-badge {
                width: 1.65rem;
                height: 1.65rem;
            }

            .home-promo-icon-badge svg {
                width: 16px;
                height: 16px;
            }

            .home-promo-copy {
                padding: 0.65rem 0.8rem;
                font-size: 0.88rem;
                line-height: 1.35;
            }
        }
    </style>
@endpush

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

      <div class="row justify-content-between mt-2">
          <div class="col-md-12">
              <div role="alert" class="alert alert-info d-flex align-items-stretch mb-1 home-promo-alert">
                  <div class="home-promo-icon">
                      <span class="home-promo-icon-badge">
                          <svg aria-hidden="true" viewBox="0 0 640 512" width="20" height="20" fill="currentColor"
                               xmlns="http://www.w3.org/2000/svg"
                               style="display: block; color: #e91e63; filter: drop-shadow(0 1px 2px rgba(233, 30, 99, .18));">
                              <path d="M372.2 52c0 20.9-12.4 39-30.2 47.2L448 192 552.4 171.1c-5.3-7.7-8.4-17.1-8.4-27.1 0-26.5 21.5-48 48-48s48 21.5 48 48c0 26-20.6 47.1-46.4 48L481 442.3c-10.3 23-33.2 37.7-58.4 37.7l-205.2 0c-25.2 0-48-14.8-58.4-37.7L46.4 192C20.6 191.1 0 170 0 144 0 117.5 21.5 96 48 96s48 21.5 48 48c0 10.1-3.1 19.4-8.4 27.1L192 192 298.1 99.1c-17.7-8.3-30-26.3-30-47.1 0-28.7 23.3-52 52-52s52 23.3 52 52z"/>
                          </svg>
                      </span>
                  </div>
                  <small class="d-block mb-0 home-promo-copy">
                      <strong>Broj 1 online knjižara i antikvarijat u Hrvatskoj</strong> s više od
                      <strong>84.000 artikala</strong>. BOX NOW dostava za samo <strong>0,99 €</strong>.
                  </small>
              </div>
          </div>
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
