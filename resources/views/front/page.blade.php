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

        .home-sales-hub__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            background: #fff0f7;
            color: #e50077;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .home-sales-hub__featured-shell,
        .home-sales-hub__card {
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 0.4375rem;
            background: #fff;
            box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .home-sales-hub__featured-shell::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: linear-gradient(90deg, #e50077 0%, #ff7ab8 100%);
        }

        .home-sales-hub__featured-shell:hover,
        .home-sales-hub__card:hover {
            transform: translateY(-2px);
            box-shadow: 0 1.25rem 2.5rem rgba(15, 23, 42, 0.1);
        }

        .home-sales-hub__featured {
            display: block;
            position: relative;
            height: 100%;
            color: inherit;
            background: #fff;
            border-radius: inherit;
        }

        .home-sales-hub__featured-slider {
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .home-sales-hub__featured-shell .tns-outer,
        .home-sales-hub__featured-slider .tns-outer,
        .home-sales-hub__featured-slider .tns-inner,
        .home-sales-hub__featured-slider .tns-ovh,
        .home-sales-hub__featured-slider .tns-carousel-inner,
        .home-sales-hub__featured-slider .tns-item,
        .home-sales-hub__featured-slider .tns-item > div {
            height: 100%;
        }

        .home-sales-hub__featured-shell .tns-outer,
        .home-sales-hub__featured-slider .tns-outer,
        .home-sales-hub__featured-slider .tns-inner,
        .home-sales-hub__featured-slider .tns-ovh,
        .home-sales-hub__featured-slider .tns-carousel-inner {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
        }

        .home-sales-hub__featured-slider .tns-outer {
            position: relative;
        }

        .home-sales-hub__featured-slider .tns-item > div,
        .home-sales-hub__featured-slider .tns-item {
            display: flex;
        }

        .home-sales-hub__featured-image-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 1rem;
            background: #fff;
        }

        .home-sales-hub__featured-image {
            width: 100%;
            max-width: 250px;
            height: auto;
            border-radius: 1rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: none;
        }

        .home-sales-hub__featured-copy {
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
            padding: 1.5rem 1.5rem 3.25rem 0.75rem;
        }

        .home-sales-hub__featured-badge {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            background: #fff0f7;
            color: #e50077;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .home-sales-hub__featured-copy h3,
        .home-sales-hub__featured-copy p,
        .home-sales-hub__featured-price strong {
            color: #2b3445;
        }

        .home-sales-hub__featured-price strong {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .home-sales-hub__featured-price small {
            color: #8c93a3 !important;
        }

        .home-sales-hub__cta-button {
            display: inline-flex;
            align-items: center;
            align-self: flex-start;
            justify-content: center;
            gap: 0.1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .home-sales-hub__cta-button i {
            font-size: 0.95rem;
        }

        .home-sales-hub__featured-slider .tns-nav {
            position: absolute;
            right: 0;
            bottom: 0.9rem;
            left: 0;
            z-index: 4;
            margin-top: 0;
            text-align: center;
        }

        .home-sales-hub__featured-slider .tns-nav button {
            width: 8px;
            height: 8px;
            margin: 0 4px;
            padding: 0;
            border: 0;
            border-radius: 999px;
            background: rgba(17, 24, 39, 0.14);
        }

        .home-sales-hub__featured-slider .tns-nav .tns-nav-active {
            width: 22px;
            background: #e50077;
        }

        .home-sales-hub__featured-slider .tns-controls {
            position: absolute;
            top: 50%;
            right: 0;
            left: 0;
            z-index: 4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0;
            gap: 0.75rem;
            padding: 0 1rem;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .home-sales-hub__featured-slider .tns-controls button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.4rem;
            height: 2.4rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 999px;
            background: #fff;
            color: #2b3445;
            box-shadow: 0 0.45rem 1rem rgba(15, 23, 42, 0.08);
            pointer-events: auto;
        }

        .home-sales-hub__card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: var(--sales-card-accent, #e50077);
            opacity: 0.9;
        }

        .home-sales-hub__card-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.42rem 0.72rem;
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.06);
            background: #fff;
            color: var(--sales-card-accent, #e50077);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .home-sales-hub__card-count {
            color: #6b7280;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .home-sales-hub__card-eyebrow {
            color: var(--sales-card-accent, #e50077);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
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

            .home-sales-hub__featured-copy {
                padding: 0 1rem 2.75rem;
            }

            .home-sales-hub__featured-price strong {
                font-size: 1.25rem;
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
