@extends('front.layouts.app')
@if(isset($blogs))
    @section('title', \App\Models\Seo::appendBrand('Blog'))
    @section('description', \App\Models\Seo::description(null, 'Medijske objave, clanci i obavijesti iz ' . \App\Models\Seo::brand() . '.'))
@else
    @php($blogSeo = \App\Models\Seo::getBlogData($blog))
    @section('title', $blogSeo['title'])
    @section('description', $blogSeo['description'])
    @section('seo_image', $blog->image)
    @section('seo_image_alt', $blog->title)
    @section('og_type', 'article')
    @section('seo_published_time', optional($blog->publish_date ?: $blog->created_at)->toAtomString())
    @section('seo_updated_time', optional($blog->updated_at ?: $blog->created_at)->toAtomString())
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
                        <a class="blog-entry-thumb" href="{{ route('catalog.route.blog', ['blog' => $blog]) }}"><img class="card-img-top" src="{{ $blog->image }}" alt="{{ $blog->title }}" loading="lazy" decoding="async"></a>
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
                        <div class="col-sm-12 mb-2"><img src="{{ $blog->image }}" alt="{{ $blog->title }}" loading="eager" fetchpriority="high" decoding="async"></div>

                    </div>
                    <!-- Post content-->

                    {!! $blog->description !!}

        </div>


    @endif

@endsection

@if(!isset($blogs))
    @push('js_after')
        <script type="application/ld+json">
            {!! collect(\App\Helpers\Metatags::articleSchema($blog))->toJson() !!}
        </script>
    @endpush
@else
    @push('js_after')
        @php
            $blogSchemas = [
                \App\Helpers\Metatags::pageSchema(
                    'CollectionPage',
                    'Blog o knjigama',
                    \App\Models\Seo::description(null, 'Medijske objave, clanci i preporuke o knjigama iz ' . \App\Models\Seo::brand() . '.'),
                    \App\Models\Seo::canonical(request())
                ),
                \App\Helpers\Metatags::itemListSchema(
                    $blogs->map(function ($item) {
                        return [
                            'name' => $item->title,
                            'url' => route('catalog.route.blog', ['blog' => $item]),
                        ];
                    }),
                    \App\Models\Seo::canonical(request()),
                    'Objave o knjigama'
                ),
            ];
        @endphp
        <script type="application/ld+json">
            {!! collect($blogSchemas)->toJson() !!}
        </script>
    @endpush
@endif
