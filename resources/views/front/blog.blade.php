@extends('front.layouts.app')
@if(isset($blogs))
    @section ( 'title', 'Blog - Zuzi Shop' )
    @section ( 'description', 'Medijske objave, članci i obavijesti -  Zuzi Shop' )
@else
    @section ( 'title', $blog->title. ' - Zuzi Shop' )
@section ( 'description', $blog->meta_description )

    @push('meta_tags')




        <link rel="canonical" href="{{ route('catalog.route.blog', ['blog' => $blog]) }}" />
        <meta property="og:locale" content="hr_HR" />
        <meta property="og:type" content="product" />
        <meta property="og:title" content="{{ $blog->title }}" />
        <meta property="og:description" content="{{ $blog->meta_description  }}" />
        <meta property="og:url" content="{{ route('catalog.route.blog', ['blog' => $blog]) }}"  />
        <meta property="og:site_name" content="ZUZI SHOP" />
        <meta property="og:updated_time" content="{{ $blog->updated_at  }}" />
        <meta property="og:image" content="{{ asset($blog->image) }}" />
        <meta property="og:image:secure_url" content="{{ asset($blog->image) }}" />
        <meta property="og:image:width" content="640" />
        <meta property="og:image:height" content="480" />
        <meta property="og:image:type" content="image/jpeg" />
        <meta property="og:image:alt" content="{{ asset($blog->image) }}" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $blog->title }}" />
        <meta name="twitter:description" content="{{ $blog->meta_description }}" />
        <meta name="twitter:image" content="{{ asset($blog->image) }}" />

    @endpush

@endif

@section('content')

    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('catalog.route.blog') }}"><i class="ci-home"></i>Blog</a></li>
        </ol>
    </nav>


    <section class="d-md-flex justify-content-between align-items-center mb-4 pb-2">


        @if(isset($blogs))
            <h1 class="h2 mb-3 mb-md-0 me-3">Blog</h1>
        @else
            <h1 class="h2 mb-3 mb-md-0 me-3">{{ $blog->title }}</h1>
        @endif

    </section>



    @if(isset($blogs))

    <div class=" pb-5 mb-2 mb-md-4">


            <!-- Entries grid-->
            <div class="masonry-grid" data-columns="3">
                @foreach ($blogs as $blog)

                <article class="masonry-grid-item">
                    <div class="card">
                        <a class="blog-entry-thumb" href="{{ route('catalog.route.blog', ['blog' => $blog]) }}"><img class="card-img-top" src="{{ $blog->image }}" alt="Post"></a>
                        <div class="card-body">
                            <h2 class="h6 blog-entry-title"><a href="{{ route('catalog.route.blog', ['blog' => $blog]) }}">{{ $blog->title }}</a></h2>
                            <p class="fs-sm">{{ $blog->short_description }}</p>
                        </div>
                        <div class="card-footer d-flex align-items-left fs-xs">
                            <div class="me-auto text-nowrap"><a class="blog-entry-meta-link text-nowrap" href="{{ route('catalog.route.blog', ['blog' => $blog]) }}">{{ \Carbon\Carbon::make($blog->created_at)->locale('hr')->format('d.m.Y.') }}</a></div>
                        </div>
                    </div>
                </article>

                @endforeach

            </div>



    </div>
    @else
        <div class="mt-2 mb-5 fs-md" style="max-width:1240px">
                    <!-- Post meta-->
                    <!-- Gallery-->
                    <div class="gallery row pb-2">
                        <div class="col-sm-12 mb-2"><img src="{{ asset($blog->image) }}" alt="Gallery image"></div>

                    </div>
                    <!-- Post content-->

                    {!! $blog->description !!}

        </div>


    @endif

@endsection
