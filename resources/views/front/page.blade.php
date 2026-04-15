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
        @font-face {
            font-family: "Font Awesome 5 Free";
            font-style: normal;
            font-weight: 900;
            font-display: block;
            src: url("{{ asset('fonts/fontawesome/fa-solid-900.woff2') }}") format("woff2"),
                 url("{{ asset('fonts/fontawesome/fa-solid-900.woff') }}") format("woff");
        }

        .fas {
            display: inline-block;
            font-family: "Font Awesome 5 Free";
            font-style: normal;
            font-weight: 900;
            line-height: 1;
            text-rendering: auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .fa-award::before {
            content: "\f559";
        }

        .fa-bolt::before {
            content: "\f0e7";
        }

        .fa-book::before {
            content: "\f02d";
        }

        .fa-book-open::before {
            content: "\f518";
        }

        .fa-coins::before {
            content: "\f51e";
        }

        .fa-crown::before {
            content: "\f521";
        }

        .fa-fire::before {
            content: "\f06d";
        }

        .fa-gem::before {
            content: "\f3a5";
        }

        .fa-gift::before {
            content: "\f06b";
        }

        .fa-star::before {
            content: "\f005";
        }

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

        .home-promo-icon-badge .fas {
            color: #e91e63;
            font-size: 1rem;
            filter: drop-shadow(0 1px 2px rgba(233, 30, 99, 0.18));
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
            box-shadow: 0 0.25rem 0.5625rem -0.0625rem rgba(0, 0, 0, 0.03), 0 0.275rem 1.25rem -0.0625rem rgba(0, 0, 0, 0.05);
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
            box-shadow: 0 0.3rem 1.525rem -0.375rem rgba(0, 0, 0, 0.1);
        }

        .home-sales-hub__featured {
            display: block;
            position: relative;
            isolation: isolate;
            height: 100%;
            color: inherit;
            background:
                radial-gradient(circle at left center, rgba(226, 232, 240, 0.72) 0%, rgba(226, 232, 240, 0.28) 24%, rgba(226, 232, 240, 0) 56%),
                linear-gradient(115deg, #f4f5f7 0%, #fafbfc 42%, #ffffff 100%);
            border-radius: inherit;
        }

        .home-sales-hub__featured::after {
            content: "";
            position: absolute;
            inset: auto -12% -26% auto;
            width: 12rem;
            height: 12rem;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.94) 0%, rgba(255, 255, 255, 0.36) 46%, rgba(255, 255, 255, 0) 74%);
            pointer-events: none;
            z-index: 0;
        }

        .home-sales-hub__featured > .row {
            position: relative;
            z-index: 1;
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
            background: transparent;
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

        .home-sales-hub__card {
            isolation: isolate;
            background: var(--sales-card-surface, #fff);
        }

        .home-sales-hub__card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: var(--sales-card-accent, #e50077);
            opacity: 0.9;
        }

        .home-sales-hub__card::after {
            content: "";
            position: absolute;
            inset: auto -12% -28% auto;
            width: 11rem;
            height: 11rem;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.45) 42%, rgba(255, 255, 255, 0) 72%);
            pointer-events: none;
            opacity: 0.95;
        }

        .home-sales-hub__card-body {
            position: relative;
            z-index: 1;
            padding: 1.15rem 1.15rem 1.1rem;
        }

        .home-sales-hub__card-top {
            position: relative;
            z-index: 1;
        }

        .home-sales-hub__card-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.48rem 0.78rem;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.88);
            background: rgba(255, 255, 255, 0.76);
            color: var(--sales-card-accent, #e50077);
            font-size: 0.8rem;
            font-weight: 700;
            box-shadow: 0 0.55rem 1rem rgba(15, 23, 42, 0.06);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .home-sales-hub__card-badge .fas {
            color: inherit;
            font-size: 0.8rem;
        }

        .home-sales-hub__card-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.42rem 0.72rem;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.84);
            background: rgba(255, 255, 255, 0.58);
            color: #667085;
            font-size: 0.8rem;
            font-weight: 700;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
        }

        .home-sales-hub__card-eyebrow {
            display: inline-block;
            margin-bottom: 0.45rem;
            color: var(--sales-card-accent, #e50077);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .home-sales-hub__card h3 {
            color: #2b3445;
            line-height: 1.2;
        }

        .home-sales-hub__card p {
            color: #4b5563 !important;
            line-height: 1.55;
        }

        .home-sales-hub__card-cta,
        .home-sales-hub__card-cta:hover,
        .home-sales-hub__card-cta:focus {
            padding: 0.72rem 0.95rem;
            border-color: rgba(255, 255, 255, 0.96) !important;
            background: rgba(255, 255, 255, 0.86);
            color: var(--sales-card-accent, #e50077) !important;
            box-shadow: 0 0.65rem 1.25rem rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .home-sales-hub__card-cta i {
            transition: transform 0.2s ease;
        }

        .home-sales-hub__card:hover .home-sales-hub__card-cta {
            background: rgba(255, 255, 255, 0.98);
        }

        .home-sales-hub__card:hover .home-sales-hub__card-cta i {
            transform: translateX(3px);
        }

        .home-sales-hub__loyalty {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 0.4375rem;
            background: #fff;
            box-shadow: 0 0.25rem 0.5625rem -0.0625rem rgba(0, 0, 0, 0.03), 0 0.275rem 1.25rem -0.0625rem rgba(0, 0, 0, 0.05);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        .home-sales-hub__loyalty::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: #e50077;
        }

        .home-sales-hub__loyalty:hover {
            transform: translateY(-1px);
            border-color: rgba(15, 23, 42, 0.12);
            box-shadow: 0 0.3rem 1.525rem -0.375rem rgba(0, 0, 0, 0.1);
        }

        .home-sales-hub__loyalty-body {
            padding: 1rem 1.15rem 1rem 1.35rem;
        }

        .home-sales-hub__loyalty-copy h3 {
            color: #2b3445;
        }

        .home-sales-hub__loyalty-copy p {
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.45;
        }

        .home-sales-hub__loyalty-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.38rem;
            margin-bottom: 0.45rem;
            padding: 0.32rem 0.65rem;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid rgba(229, 0, 119, 0.12);
            color: #e50077;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .home-sales-hub__loyalty-actions {
            flex-shrink: 0;
        }

        .home-sales-hub__loyalty-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.38rem;
            min-height: 2rem;
            padding: 0.42rem 0.8rem;
            border-radius: 999px;
            background: #fff;
            border: 1px solid rgba(229, 0, 119, 0.12);
            color: #e50077;
            font-size: 0.82rem;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .home-sales-hub__loyalty-kicker .fas,
        .home-sales-hub__loyalty-pill .fas {
            color: inherit;
            font-size: 0.82rem;
        }

        .home-sales-hub__loyalty-cta {
            white-space: nowrap;
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

            .home-sales-hub__card-body {
                padding: 1rem;
            }

            .home-sales-hub__card-count {
                font-size: 0.76rem;
            }

            .home-sales-hub__loyalty-body {
                padding: 0.9rem 0.9rem 0.9rem 1.1rem;
            }

            .home-sales-hub__loyalty-copy p {
                font-size: 0.9rem;
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
                          <i class="fas fa-crown" aria-hidden="true"></i>
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
