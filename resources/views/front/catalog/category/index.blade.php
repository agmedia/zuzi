@extends('front.layouts.app')
@php
    if (Route::currentRouteName() === 'pretrazi') {
        $listingSeo = \App\Models\Seo::getSearchData(request()->input('pojam'));
    } elseif (isset($author) && $author) {
        $listingSeo = $seo;
    } elseif (isset($publisher) && $publisher) {
        $listingSeo = $seo;
    } else {
        $listingSeo = \App\Models\Seo::getCategoryData($group ?? null, $cat ?? null, $subcat ?? null);
    }

    $listingImage = null;

    if (isset($subcat) && ! empty($subcat->image)) {
        $listingImage = $subcat->image;
    } elseif (isset($cat) && ! empty($cat->image)) {
        $listingImage = $cat->image;
    } elseif (isset($author) && $author && ! empty($author->image)) {
        $listingImage = $author->image;
    } elseif (isset($publisher) && $publisher && ! empty($publisher->image)) {
        $listingImage = $publisher->image;
    }

    $listingUpdatedAt = isset($subcat) && $subcat ? $subcat->updated_at : null;
    $listingUpdatedAt = $listingUpdatedAt ?: (isset($cat) && $cat ? $cat->updated_at : null);
    $listingUpdatedAt = $listingUpdatedAt ?: (isset($author) && $author ? $author->updated_at : null);
    $listingUpdatedAt = $listingUpdatedAt ?: (isset($publisher) && $publisher ? $publisher->updated_at : null);
    $groupHeading = 'Knjige';

    if (($group ?? null) === 'snizenja') {
        $groupHeading = 'Snižene knjige';
    } elseif (($group ?? null) === 'zemljovidi-i-vedute') {
        $groupHeading = 'Zemljovidi i vedute';
    } elseif (isset($group) && $group) {
        $groupHeading = \Illuminate\Support\Str::headline(str_replace('-', ' ', (string) $group));
    }

    $listingSchemaType = Route::currentRouteName() === 'pretrazi' ? 'SearchResultsPage' : 'CollectionPage';
    $listingSchemaName = $groupHeading;

    if (Route::currentRouteName() === 'pretrazi') {
        $listingSchemaName = 'Rezultati pretrage knjiga';
    } elseif (isset($author) && $author) {
        $listingSchemaName = 'Knjige autora ' . $author->title;
    } elseif (isset($publisher) && $publisher) {
        $listingSchemaName = 'Knjige nakladnika ' . $publisher->title;
    } elseif (isset($subcat) && $subcat) {
        $listingSchemaName = 'Knjige: ' . $subcat->title;
    } elseif (isset($cat) && $cat) {
        $listingSchemaName = 'Knjige: ' . $cat->title;
    } elseif (($group ?? null) === 'snizenja') {
        $listingSchemaName = 'Snižene knjige';
    }

    $listingSchemaUrl = \App\Models\Seo::canonical(request());
    $listingIntro = null;

    if (Route::currentRouteName() === 'pretrazi') {
        $searchQuery = trim((string) request()->input('pojam'));
        $listingIntro = $searchQuery ? 'Pregledajte dostupne knjige, autore i srodne naslove za pojam "' . $searchQuery . '".' : null;
    } elseif (isset($author) && $author) {
        $listingIntro = 'Pregledajte izbor knjiga autora ' . $author->title . ', dostupna izdanja i srodne naslove u ' . \App\Models\Seo::brand() . '.';
    } elseif (isset($publisher) && $publisher) {
        $listingIntro = 'Istražite knjige nakladnika ' . $publisher->title . ', dostupna izdanja i povezane naslove u ' . \App\Models\Seo::brand() . '.';
    } elseif (isset($subcat) && $subcat) {
        $listingIntro = 'Pregledajte knjige iz kategorije ' . $subcat->title . ' i pronađite izdanja koja odgovaraju vašem interesu.';
    } elseif (isset($cat) && $cat) {
        $listingIntro = 'Pregledajte knjige iz kategorije ' . $cat->title . ' i izdvojite naslove koji vas zanimaju.';
    } elseif (($group ?? null) === 'snizenja') {
        $listingIntro = 'Pregledajte aktualno snižene knjige i izdvojena izdanja po povoljnijim cijenama.';
    } elseif (isset($group) && $group) {
        $listingIntro = 'Pregledajte aktualnu ponudu knjiga i izdvojena izdanja u ovoj grupi.';
    }

@endphp

@section('title', $listingSeo['title'])
@section('description', $listingSeo['description'])
@if($listingImage)
    @section('seo_image', \App\Models\Seo::image($listingImage))
@endif
@section('seo_image_alt', isset($subcat) && $subcat ? $subcat->title : (isset($cat) && $cat ? $cat->title : (isset($author) && $author ? $author->title : (isset($publisher) && $publisher ? $publisher->title : $listingSeo['title']))))
@section('seo_updated_time', optional($listingUpdatedAt)->toAtomString())


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
                        <h1 class="h2 mb-2 mb-md-0 me-3">{{ $groupHeading }}</h1>

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

            @if ($listingIntro)
                <section class="mb-3">
                    <p class="fs-md text-muted mb-0">{{ $listingIntro }}</p>
                </section>
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

    @php
        $categoryDescription = null;

        if ($cat && ! $subcat) {
            $categoryDescription = $cat->description;
        } elseif ($subcat) {
            $categoryDescription = $subcat->description;
        }

        do {
            $previousCategoryDescription = (string) $categoryDescription;
            $categoryDescription = preg_replace(
                '/<([a-z][a-z0-9]*)\b[^>]*>(?:\s|&nbsp;|&#160;|&#xA0;|<br\s*\/?>)*<\/\1>/iu',
                '',
                $previousCategoryDescription
            );
        } while ((string) $categoryDescription !== $previousCategoryDescription);

        $categoryDescription = trim((string) $categoryDescription);

        $hasLongCategoryDescription = \Illuminate\Support\Str::length(trim(strip_tags((string) $categoryDescription))) > 420;
    @endphp

    @if (! empty($categoryDescription))
        <div class="container pb-4 mb-2 mt-5 mb-md-4">
            <div
                class="category-description{{ $hasLongCategoryDescription ? ' is-collapsed' : '' }}"
                data-category-description
            >
                <div class="category-description__content">
                    {!! $categoryDescription !!}
                </div>
            </div>

            @if ($hasLongCategoryDescription)
                <div class="text-center mt-3">
                    <button
                        class="btn btn-outline-secondary btn-sm"
                        type="button"
                        data-category-description-toggle
                        aria-expanded="false"
                    >
                        Prikaži više
                    </button>
                </div>
            @endif
        </div>
    @endif

@endsection

@if(!empty($crumbs))
    @push('js_after')
        <script type="application/ld+json">
            {!! collect($crumbs)->toJson() !!}
        </script>
    @endpush
@endif

@push('js_after')
    <script type="application/ld+json">
        {!! collect(\App\Helpers\Metatags::pageSchema(
            $listingSchemaType,
            $listingSchemaName,
            $listingSeo['description'],
            $listingSchemaUrl,
            $listingImage ? \App\Models\Seo::image($listingImage) : null
        ))->toJson() !!}
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

        .category-description {
            position: relative;
            text-align: left;
        }

        .category-description.is-collapsed {
            max-height: 16rem;
            overflow: hidden;
        }

        .category-description.is-collapsed::after {
            content: "";
            position: absolute;
            inset: auto 0 0;
            height: 5rem;
            background: linear-gradient(to bottom, rgba(246, 249, 252, 0), #f6f9fc 72%);
            pointer-events: none;
        }

        .category-description__content > :last-child {
            margin-bottom: 0;
        }

        .category-description__content h1,
        .category-description__content h2,
        .category-description__content h3,
        .category-description__content h4 {
            line-height: 1.2;
            margin-bottom: 0.9rem;
        }

        .category-description__content h1 {
            font-size: clamp(1.55rem, 2.2vw, 2rem);
        }

        .category-description__content h2 {
            font-size: clamp(1.3rem, 1.8vw, 1.65rem);
        }

        .category-description__content h3 {
            font-size: clamp(1.15rem, 1.55vw, 1.4rem);
        }

        .category-description__content h4 {
            font-size: clamp(1rem, 1.35vw, 1.15rem);
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const description = document.querySelector('[data-category-description]');
            const toggle = document.querySelector('[data-category-description-toggle]');

            if (!description || !toggle) {
                return;
            }

            toggle.addEventListener('click', function () {
                const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

                description.classList.toggle('is-collapsed', isExpanded);
                toggle.setAttribute('aria-expanded', String(!isExpanded));
                toggle.textContent = isExpanded ? 'Prikaži više' : 'Prikaži manje';
            });
        });
    </script>
@endpush
