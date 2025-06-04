@extends('front.layouts.app')
@section ('title', $seo['title'])
@section ('description', $seo['description'])
@push('meta_tags')

    <link rel="canonical" href="{{ url($prod->url) }}" />
    <meta property="og:locale" content="hr_HR" />
    <meta property="og:type" content="product" />
    <meta property="og:title" content="{{ $seo['title'] }}" />
    <meta property="og:description" content="{{ $seo['description']  }}" />
    <meta property="og:url" content="{{ url($prod->url) }}"  />
    <meta property="og:site_name" content="ZUZI SHOP" />
    <meta property="og:updated_time" content="{{ $prod->updated_at  }}" />
    <meta property="og:image" content="{{ asset($prod->image) }}" />
    <meta property="og:image:secure_url" content="{{ asset($prod->image) }}" />
    <meta property="og:image:width" content="640" />
    <meta property="og:image:height" content="480" />
    <meta property="og:image:type" content="image/jpeg" />
    <meta property="og:image:alt" content="{{ $prod->image_alt }}" />
    <meta property="product:price:amount" content="{{ number_format($prod->price, 2) }}" />
    <meta property="product:price:currency" content="EUR" />
    <meta property="product:availability" content="instock" />
    <meta property="product:retailer_item_id" content="{{ $prod->sku }}" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $seo['title'] }}" />
    <meta name="twitter:description" content="{{ $seo['description'] }}" />
    <meta name="twitter:image" content="{{ asset($prod->image) }}" />

    <link rel="stylesheet" media="screen" href="{{ asset('vendor/lightgallery/css/lightgallery-bundle.min.css')}}"/>

@endpush

@if (isset($gdl))
    @section('google_data_layer')
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({
                'event': 'view_item',
                'ecommerce': {
                    'items': [<?php echo json_encode($gdl); ?>]
                } });
        </script>
    @endsection
@endif

@section('content')


    <!-- Page title + breadcrumb-->
    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            @if ($group)
                @if ($group && ! $cat && ! $subcat)
                  <!--  <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ \Illuminate\Support\Str::ucfirst($group) }}</li> -->
                @elseif ($group && $cat)
              <!--      <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group]) }}">{{ \Illuminate\Support\Str::ucfirst($group) }}</a></li> -->
                @endif

                @if ($cat && ! $subcat)
                    @if ($prod)
                        <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                    @else
                        <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $cat->title }}</li>
                    @endif
                @elseif ($cat && $subcat)
                    <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                    @if ($prod)
                        @if ($cat && ! $subcat)
                            <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ \Illuminate\Support\Str::limit($prod->name, 50) }}</a></li>
                        @else
                            <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat, 'subcat' => $subcat]) }}">{{ $subcat->title }}</a></li>
                        @endif
                    @endif
                @endif
            @endif

        </ol>
    </nav>
    <!-- Content-->
    <section class="row g-0 mx-n2 ">
        @include('back.layouts.partials.session')
        <!-- Product Gallery + description-->
        <div class="col-xl-6 px-2 mb-3">
            <div class="h-100 bg-light shadow rounded-3 p-4">
                <div class="product-gallery">
                    <div class="product-gallery-preview  gallery order-sm-2">
                            @if ( ! empty($prod->image))
                                <div class="product-gallery-preview-item active" id="first">
                                    <a class="gallery-item" data-sub-html='{{ $prod->name }}' href="{{ asset($prod->image) }}"><img  src="{{ asset($prod->image) }}"  alt="{{ $prod->name }}" height="800"></a></div>
                            @endif
                            @if ($prod->images->count())
                                @foreach ($prod->images as $key => $image)
                                        <div class="product-gallery-preview-item" id="key{{ $key + 1 }}"><a class="gallery-item rounded-3" href="{{ asset($image->image) }}"><img  src="{{ asset($image->image) }}" alt="{{ $image->alt }}"  height="800"></a></div>
                                @endforeach
                            @endif
                    </div>
                    <div class="product-gallery-thumblist order-sm-1">
                        @if ($prod->images->count())
                            @if ( ! empty($prod->thumb))
                                <a class="product-gallery-thumblist-item active" href="#first"><img src="{{ asset($prod->thumb) }}" alt="{{ $prod->name }}"></a>
                            @endif
                            @foreach ($prod->images as $key => $image)
                                <a class="product-gallery-thumblist-item" href="#key{{ $key + 1 }}"><img src="{{ url('cache/thumb?size=100x100&src=' . $image->thumb) }}" width="100" height="100" alt="{{ $image->alt }}"></a>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 px-2 mb-3">
            <div class="h-100 bg-light shadow  rounded-3 py-5 px-4 px-sm-5">

        @if ( $prod->quantity < 1)
                    <span class="badge bg-warning ">Rasprodano</span>
       @endif

   @if ($prod->main_price > $prod->main_special)
       <span class="badge bg-primary ">-{{ number_format(floatval(\App\Helpers\Helper::calculateDiscount($prod->price, $prod->special())), 0) }}%</span>
   @endif



   <h1 class="h3">{{ $prod->name }}</h1>

       <div class="mb-1">
           @if ($prod->main_price > $prod->main_special)
               <span class="h3 fw-normal text-accent me-1">{{ $prod->main_special_text }}</span>
               <span class="text-muted fs-lg me-3">*{{ $prod->main_price_text }}</span>

           @else
               <span class="h3 fw-normal text-accent me-1">{{ $prod->main_price_text }}</span>
           @endif

       </div>

   @if($prod->secondary_price_text)
       <div class="mb-1 mt-1 text-start">
           @if ($prod->main_price > $prod->main_special)
               <span class=" fs-sm text-muted me-1"> {{ $prod->secondary_special_text }}</span>
               <span class="text-muted fs-sm me-3">*{{ $prod->secondary_price_text }}</span>
           @else
               <span class="fs-sm text-muted  me-1">{{ $prod->secondary_price_text }}</span>
           @endif
       </div>
   @endif
   @if ($prod->main_price > $prod->main_special)

       <div class="mb-3 mt-1 text-start">
           <span class=" fs-sm text-muted me-1"> *Najniža cijena u zadnjih 30 dana.</span>
       </div>

                @if($prod->kat)


                    <div class="d-flex row justify-content-between"><div class="col-md-12"><div role="alert" class="alert alert-info d-flex  mb-1 "><div class="alert-icon"><i class="ci-truck"></i></div> <small>Dostava za Regionalne naslove je 20 dana.</small></div></div></div>
                    @endif

   @endif
            @if ( $prod->quantity > 0)
   <add-to-cart-btn id="{{ $prod->id }}" available="{{ $prod->quantity }}"></add-to-cart-btn>
            @endif
   <!-- Product panels-->
   <div class="accordion mb-4" id="productPanels">
       <div class="accordion-item">
           <h3 class="accordion-header"><a class="accordion-button" href="#productInfo" role="button" data-bs-toggle="collapse" aria-expanded="true" aria-controls="productInfo"><i class="ci-announcement text-muted fs-lg align-middle mt-n1 me-2"></i>Osnovne informacije</a></h3>
           <div class="accordion-collapse collapse show" id="productInfo" data-bs-parent="#productPanels">
               <div class="accordion-body">

                   <ul class="fs-sm ps-4 mb-0">
                       @if ($prod->author)
                           <li><strong>Autor:</strong> <a href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">{{ $prod->author->title }} </a></li>
                       @endif
                       @if ($prod->publisher)
                           <li><strong>Nakladnik:</strong> <a href="{{ route('catalog.route.publisher', ['publisher' => $prod->publisher]) }}">{{ $prod->publisher->title }}</a> </li>
                       @endif
                       @if ($prod->isbn)
                       <li><strong>EAN:</strong> {{ $prod->isbn }} </li>
                       @endif
                           @if ($prod->quantity)
                               @if ($prod->decrease)
                                   <li><strong>Dostupnost:</strong> {{ $prod->quantity }} </li>
                               @else
                                   <li><strong>Dostupnost:</strong> Dostupno</li>
                               @endif
                           @else
                               <li><strong>Dostupnost:</strong> Rasprodano</li>
                           @endif

                           <li><strong>Stanje:</strong> Nova knjiga</li>
                   </ul>

               </div>
           </div>
       </div>
       <div class="accordion-item">
           <h3 class="accordion-header"><a class="accordion-button collapsed" href="#shippingOptions" role="button" data-bs-toggle="collapse" aria-expanded="true" aria-controls="shippingOptions"><i class="ci-delivery text-muted lead align-middle mt-n1 me-2"></i>Opcije dostave</a></h3>
           <div class="accordion-collapse collapse" id="shippingOptions" data-bs-parent="#productPanels">
               <div class="accordion-body fs-sm">

                   @foreach($shipping_methods as $shipping_method)
                       <div class="d-flex justify-content-between border-bottom py-2">
                           <div>
                               <div class="fw-semibold text-dark">{{ $shipping_method->title }}</div>
                              {{--  <div class="fs-sm text-muted"> Besplatna dostava za narudžbe iznad {{ config('settings.free_shipping') }}€</div>--}}
                               @if ($prod->shipping_time)

                                   <span class=" fs-sm text-muted me-1"> Rok dostave: {{ $prod->shipping_time }}</span>

                               @endif
                           </div>
                           <div>{{ $shipping_method->data->price }}€ </div>
                       </div>
                   @endforeach

               </div>
               <small class="mt-2"></small>
           </div>
       </div>
       <div class="accordion-item">
           <h3 class="accordion-header"><a class="accordion-button collapsed" href="#localStore" role="button" data-bs-toggle="collapse" aria-expanded="true" aria-controls="localStore"><i class="ci-card text-muted fs-lg align-middle mt-n1 me-2"></i>Načini plaćanja</a></h3>
           <div class="accordion-collapse collapse" id="localStore" data-bs-parent="#productPanels">
               <div class="accordion-body fs-sm">


                   @foreach($payment_methods as $payment_method)
                       @if($prod->origin == 'Engleski' and $payment_method->code == 'cod' )

                       @else
                           <div class="d-flex justify-content-between border-bottom py-2">
                               <div>
                                   <div class="fw-semibold text-dark">{{ $payment_method->title }}</div>
                                   @if (isset($payment_method->data->description))
                                       <div class="fs-sm text-muted">{{ $payment_method->data->description }}</div>
                                   @endif
                               </div>
                           </div>
                       @endif
                   @endforeach

               </div>


           </div>
       </div>
   </div>
   <!-- Sharing-->
   <!-- ShareThis BEGIN --><div class="sharethis-inline-share-buttons"></div><!-- ShareThis END -->
</div>
</div>
</section>
<!-- Related products-->

<section class="mx-n2 pb-2 px-2 mb-xl-3" id="tabs_widget">
<div class="bg-light px-2 mb-3 shadow rounded-3">
<!-- Tabs-->
<ul class="nav nav-tabs" role="tablist">
   <li class="nav-item"><a class="nav-link py-4 px-sm-4 active" href="#specs" data-bs-toggle="tab" role="tab"><span>Opis</span> </a></li>

</ul>
<div class="px-4 pt-lg-3 pb-3 mb-5">
   <div class="tab-content px-lg-3">
       <!-- Tech specs tab-->
       <div class="tab-pane fade show active" id="specs" role="tabpanel">
           <!-- Specs table-->
           <div class="row pt-2">
               <div class="col-lg-7 col-sm-7">
                   <h3 class="h6">Sažetak</h3>
                   <div class=" fs-md pb-2 mb-4">
                       {!! $prod->description !!}
                   </div>


                   @if ($prod->author_web_url or $prod->serial_web_url or $prod->wiki_url or $prod->youtube_channel or $prod->youtube_product_url or $prod->goodreads_author_url or $prod->goodreads_book_url)

                       <h3 class="h6 mt-4">Multimedia i linkovi</h3>
                       <ul class="list-unstyled fs-sm pb-2">
                           @if ($prod->youtube_product_url)
                               <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">YouTube Video:</span><span><i class="ci-youtube text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->youtube_product_url }}">Pogledajte video</a></span></li>
                           @endif

                           @if ($prod->youtube_channel)
                                <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">YouTube Kanal:</span><span><i class="ci-youtube text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->youtube_channel }}">Pogledajte video</a></span></li>
                           @endif

                           @if ($prod->wiki_url)
                                   <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Wikipedia:</span><span><i class="ci-link text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->wiki_url }}">Pogledajte stranicu</a></span></li>
                           @endif

                           @if ($prod->author_web_url)
                                   <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Web stranica autora:</span><span><i class="ci-link text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->author_web_url }}">Pogledajte stranicu</a></span></li>
                           @endif

                            @if ($prod->serial_web_url)
                                   <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Web stranica serijala:</span><span><i class="ci-link text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->serial_web_url }}">Pogledajte stranicu</a></span></li>
                            @endif

                            @if ($prod->goodreads_author_url)
                                   <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Goodreads stranica autora:</span><span><i class="ci-link text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->goodreads_author_url }}">Pogledajte stranicu</a></span></li>

                            @endif

                            @if ($prod->goodreads_book_url)
                                   <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Goodreads stranica knjige:</span><span><i class="ci-link text-muted fs-lg align-middle mt-n1 me-1"></i> <a href="{{ $prod->goodreads_book_url }}">Pogledajte stranicu</a></span></li>

                            @endif
                       </ul>

                   @endif
               </div>
               <div class="col-lg-5 col-sm-5 ">
                   <h3 class="h6">Dodatne informacije</h3>
                   <ul class="list-unstyled fs-sm pb-2">


                       @if ($prod->author)
                               <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Autor:</span><span><a href="{{ route('catalog.route.author', ['author' => $prod->author]) }}">{{ Illuminate\Support\Str::limit($prod->author->title, 30) }}</a></span></li>
                       @endif
                       @if ($prod->publisher)
                               <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Nakladnik:</span><span><a href="{{ route('catalog.route.publisher', ['publisher' => $prod->publisher]) }}">{{ Illuminate\Support\Str::limit($prod->publisher->title, 30) }}</a> </span></li>
                       @endif


                       @if ($prod->binding)
                           <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Uvez:</span><span>{{ $prod->binding  }}</span></li>
                        @endif
                       @if ($prod->origin)
                           <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Jezik:</span><span>{{ $prod->origin  }}</span></li>
                       @endif
                        @if ($prod->year)
                           <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Godina izdanja:</span><span>{{ $prod->year }}</span></li>
                       @endif
                       @if ($prod->pages)
                           <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Broj stranica:</span><span>{{ $prod->pages }}</span></li>
                      @endif
                       @if ($prod->dimensions)
                           <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">Dimenzije:</span><span>{{ $prod->dimensions.' cm'  }}</span></li>
                       @endif
                       @if ($prod->isbn)
                            <li class="d-flex justify-content-between pb-2 border-bottom"><span class="text-muted">EAN:</span><span>{{ $prod->isbn }}</span></li>
                       @endif
                   </ul>

               </div>
           </div>
       </div>
       <!-- Reviews tab-->

   </div>
</div>
</div>
</section>
<!-- Product description-->
<section class="pb-5 mb-2 mb-xl-4">
<div class=" flex-wrap justify-content-between align-items-center  text-center">
<h2 class="h3 mb-4 pt-1 font-title me-3 text-center"> Možda vas zanima</h2>

</div>
<div class="tns-carousel tns-controls-static tns-controls-outside tns-nav-enabled pt-2">
<div class="tns-carousel-inner" data-carousel-options="{&quot;items&quot;: 2, &quot;gutter&quot;: 16, &quot;controls&quot;: true, &quot;autoHeight&quot;: true, &quot;responsive&quot;: {&quot;0&quot;:{&quot;items&quot;:2}, &quot;480&quot;:{&quot;items&quot;:2}, &quot;720&quot;:{&quot;items&quot;:3}, &quot;991&quot;:{&quot;items&quot;:2}, &quot;1140&quot;:{&quot;items&quot;:3}, &quot;1300&quot;:{&quot;items&quot;:4}, &quot;1500&quot;:{&quot;items&quot;:5}}}">
    @foreach ($cat->products()->get()->unique()->take(10) as $cat_product)
        @if ($cat_product->id  != $prod->id)
            <div>
                @include('front.catalog.category.product', ['product' => $cat_product])
            </div>
        @endif
    @endforeach
</div>
</div>
</section>

@endsection

@push('js_after')
<script type="application/ld+json">
{!! collect($crumbs)->toJson() !!}
</script>
<script type="application/ld+json">
{!! collect($bookscheme)->toJson() !!}
</script>
<script type='text/javascript' src='https://platform-api.sharethis.com/js/sharethis.js#property=6134a372eae16400120a5035&product=sop' async='async'></script>

<script>
$('#openReview').on('click', function() {
$('.nav-tabs a[href="#reviews"]').tab('show');
//  document.getElementById("tabs_widget").scrollIntoView();
});
</script>
@endpush
