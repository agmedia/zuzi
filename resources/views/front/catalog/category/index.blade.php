@extends('front.layouts.app')
@if (isset($group) && $group)
    @if ($group && ! $cat && ! $subcat)
        @section ( 'title',  \Illuminate\Support\Str::ucfirst($group). ' - ZuZi Shop' )
    @endif
    @if ($cat && ! $subcat)
        @section ( 'title',  $cat->title . ' - ZuZi Shop' )
        @section ( 'description', $cat->meta_description )
    @elseif ($cat && $subcat)
        @section ( 'title', $subcat->title . ' - ZuZi Shop' )
        @section ( 'description', $cat->meta_description )
    @endif
@endif

@if (isset($author) && $author)
    @section ('title',  $seo['title'])
    @section ('description', $seo['description'])
@endif

@if (isset($publisher) && $publisher)
    @section ('title',  $seo['title'])
    @section ('description', $seo['description'])
@endif

@if (isset($meta_tags))
    @push('meta_tags')
        @foreach ($meta_tags as $tag)
            <meta name={{ $tag['name'] }} content={{ $tag['content'] }}>
        @endforeach
    @endpush
@endif


@section('content')



    @if (Route::currentRouteName() == 'pretrazi')
        <section class="d-md-flex justify-content-between align-items-center mb-2 pb-2">
            <h1 class="h2 mb-2 mb-md-0 me-3"><span class="small fw-light me-2">Rezultati za:</span> {{ request()->input('pojam') }}</h1>
        </section>
    @endif

    @if (isset($author) && $author)

        <nav class="mb-4" aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap">
                    <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                    <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route.author') }}">Autori</a></li>
                    @if ( ! $cat && ! $subcat)
                        <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $author->title }}</li>
                    @endif
                    @if ($cat && ! $subcat)
                        <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route.author', ['author' => $author]) }}">{{ $author->title }}</a></li>
                        <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $cat->title }}</li>
                    @elseif ($cat && $subcat)
                        <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route.author', ['author' => $author]) }}">{{ $author->title }}</a></li>
                        <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route.author', ['author' => $author, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                        <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $subcat->title }}</li>
                    @endif
                </ol>
            </nav>

        <section class="d-md-flex justify-content-between align-items-center mb-2 pb-2">
            <h1 class="h2 mb-2 mb-md-0 me-3">{{ $author->title }}</h1>
        </section>
    @endif

    @if (isset($publisher) && $publisher)

        <nav class="mb-4" aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap">
                    <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                    <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route.publisher') }}">Nakladnici</a></li>
                    @if ( ! $cat && ! $subcat)
                        <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $publisher->title }}</li>
                    @endif
                    @if ($cat && ! $subcat)
                        <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route.publisher', ['publisher' => $publisher]) }}">{{ $publisher->title }}</a></li>
                        <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $cat->title }}</li>
                    @elseif ($cat && $subcat)
                        <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route.publisher', ['publisher' => $publisher]) }}">{{ $publisher->title }}</a></li>
                        <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route.publisher', ['publisher' => $publisher, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                        <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $subcat->title }}</li>
                    @endif
                </ol>
            </nav>

        <section class="d-md-flex justify-content-between align-items-center mb-2 pb-2">
            <h1 class="h2 mb-2 mb-md-0 me-3">{{ $publisher->title }}</h1>
        </section>
    @endif

            @if (isset($group) && $group)


                <nav class="mb-2 text-center text-lg-start" aria-label="breadcrumb">
                        <ol class="breadcrumb flex-lg-nowrap">
                            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
                            @if ($group && ! $cat && ! $subcat)
                               <!-- <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ \Illuminate\Support\Str::ucfirst($group) }}</li> -->
                            @elseif ($group && $cat)
                            <!--    <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group]) }}">{{ \Illuminate\Support\Str::ucfirst($group) }}</a></li>-->
                            @endif
                            @if ($cat && ! $subcat)
                                <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $cat->title }}</li>
                            @elseif ($cat && $subcat)
                                <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                                <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $subcat->title }}</li>
                            @endif
                        </ol>
                </nav>


                <section class="d-md-flex justify-content-between align-items-center text-center text-lg-start mb-1 pb-1">

                    @if ($group && ! $cat && ! $subcat)
                        <h1 class="h2 mb-2 mb-md-0 me-3">Zuzi Web Shop</h1>

                    @endif
                    @if ($cat && ! $subcat)
                            <h1 class="h2 mb-2 mt-2 mb-md-0 me-3">{{ $cat->title }}</h1>
                    @elseif ($cat && $subcat)
                            <h1 class="h2 mb-2 mt-2 mb-md-0 me-3">{{ $subcat->title }}</h1>
                    @endif


                </section>

                @if ($cat && ! $subcat)

                    @if ($cat->subcategories()->count())
                        <section class="py-2 mb-0">
                            <div class="row  ">
                                <div class="col-lg-12   py-1 ">
                                    <div class="scrolling-wrapper">
                                        @foreach ($cat->subcategories as $item)
                                            <a href="{{ route('catalog.route', ['group' => $group, 'cat' => $cat, 'subcat' => $item]) }}"
                                               class="btn btn-dark btn-sm mb-2">
                                                <p class=" py-0 mb-0 px-1">{{ $item->title }}</p></a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                        </section>

                    @endif

                @endif

            @endif






            <products-view ids="{{ isset($ids) ? $ids : null }}"
                           group="{{ isset($group) ? $group : null }}"
                           cat="{{ isset($cat) ? $cat['id'] : null }}"
                           subcat="{{ isset($subcat) ? $subcat['id'] : null }}"
                           author="{{ isset($author) ? $author['slug'] : null }}"
                           publisher="{{ isset($publisher) ? $publisher['slug'] : null }}">
            </products-view>



    @if (isset($author) && $author && ! empty($author->description))

        <div class=" pb-4 mb-2 mt-4 mb-md-4" >
            <p class="fs-md mb-2">{{ strip_tags($author->description) }}</p>
        </div>
    @endif

    <div class="container pb-4 mb-2 mt-5 mb-md-4" >
        @if ($cat && !$subcat)
            {!! $cat->description !!}
        @elseif ($subcat)
            {!! $subcat->description !!}
        @endif
    </div>





@endsection

@push('js_after')
    <script type="application/ld+json">
        {!! collect($crumbs)->toJson() !!}
    </script>
@endpush

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
