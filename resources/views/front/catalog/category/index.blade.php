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
    $isActionListing = Route::currentRouteName() === 'catalog.route.actions';
    $actionLanding = $actionLanding ?? [];
    $actionLandingTitle = $isActionListing ? ($actionLanding['title'] ?? null) : null;
    $actionLandingLead = $isActionListing ? ($actionLanding['lead'] ?? null) : null;
    $actionLandingBody = $isActionListing ? ($actionLanding['body'] ?? null) : null;
    $actionLandingCategories = collect($isActionListing ? ($actionLanding['categories'] ?? []) : []);
    $actionLandingProducts = collect($isActionListing ? ($actionLanding['products'] ?? []) : []);
    $actionLandingUrl = $isActionListing ? ($actionLanding['landing_url'] ?? route('catalog.route.actions')) : null;
    $actionLandingPromotionStart = $isActionListing ? ($actionLanding['promotion_start'] ?? null) : null;
    $actionLandingPromotionEnd = $isActionListing ? ($actionLanding['promotion_end'] ?? null) : null;
    $actionLandingCurrentTitle = isset($subcat) && $subcat ? $subcat->title : (isset($cat) && $cat ? $cat->title : null);
    $groupHeading = 'Knjige';

    if (($group ?? null) === 'snizenja') {
        $groupHeading = 'Snižene knjige';
    } elseif (($group ?? null) === 'zemljovidi-i-vedute') {
        $groupHeading = 'Zemljovidi i vedute';
    } elseif (isset($group) && $group) {
        $groupHeading = \Illuminate\Support\Str::headline(str_replace('-', ' ', (string) $group));
    }

    if ($isActionListing && ! $cat && ! $subcat && $actionLandingTitle) {
        $groupHeading = $actionLandingTitle;
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

    if ($isActionListing && $actionLandingTitle) {
        $listingIntro = null;

        if (! $cat && ! $subcat) {
            $listingSeo['title'] = $actionLanding['seo_title'] ?? $listingSeo['title'];
            $listingSeo['description'] = $actionLanding['seo_description'] ?? $listingSeo['description'];
            $listingSchemaName = $actionLandingTitle;
        }
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
                            @if ($isActionListing)
                                @if (! $cat && ! $subcat)
                                    <li class="breadcrumb-item text-nowrap active" aria-current="page">Akcijska ponuda</li>
                                @else
                                    <li class="breadcrumb-item text-nowrap active" aria-current="page">
                                        <a class="text-nowrap" href="{{ route('catalog.route.actions') }}">Akcijska ponuda</a>
                                    </li>
                                @endif
                            @endif
                            @if ($group && ! $cat && ! $subcat)
                               <!-- <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ \Illuminate\Support\Str::ucfirst($group) }}</li> -->
                            @elseif ($group && $cat)
                            <!--    <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ route('catalog.route', ['group' => $group]) }}">{{ \Illuminate\Support\Str::ucfirst($group) }}</a></li>-->
                            @endif
                            @if ($cat && ! $subcat)
                                <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $cat->title }}</li>
                            @elseif ($cat && $subcat)
                                <li class="breadcrumb-item text-nowrap active" aria-current="page"><a class="text-nowrap" href="{{ $isActionListing ? route('catalog.route.actions', ['cat' => $cat]) : route('catalog.route', ['group' => $group, 'cat' => $cat]) }}">{{ $cat->title }}</a></li>
                                <li class="breadcrumb-item text-nowrap active" aria-current="page">{{ $subcat->title }}</li>
                            @endif
                        </ol>
                </nav>


                <section class="d-md-flex justify-content-between align-items-center text-center text-lg-start mb-1 pb-1">

                    @if ($group && ! $cat && ! $subcat && ! ($isActionListing && $actionLandingTitle))
                        <h1 class="h2 mb-2 mb-md-0 me-3">{{ $groupHeading }}</h1>

                    @endif
                    @if ($cat && ! $subcat && ! $isActionListing)
                            <h1 class="h2 mb-2 mt-2 mb-md-0 me-3">{{ $cat->title }}</h1>
                    @elseif ($cat && $subcat && ! $isActionListing)
                            <h1 class="h2 mb-2 mt-2 mb-md-0 me-3">{{ $subcat->title }}</h1>
                    @endif


                </section>

                @if ($cat && ! $subcat && ! $isActionListing)

                    @if ($cat->subcategories->isNotEmpty())
                        <section class="py-2 mb-0">
                            <div class="row  ">
                                <div class="col-lg-12   py-1 ">
                                    <div class="scrolling-wrapper">
                                        @foreach ($cat->subcategories as $item)
                                            <a href="{{ $isActionListing ? route('catalog.route.actions', ['cat' => $cat, 'subcat' => $item]) : route('catalog.route', ['group' => $group, 'cat' => $cat, 'subcat' => $item]) }}"
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

            @if ($isActionListing && $actionLandingTitle)
                <section class="birthday-landing mb-4">
                    <div class="birthday-landing__panel">
                        <p class="birthday-landing__eyebrow">AKCIJSKA PONUDA</p>
                        <h1 class="birthday-landing__title mb-3">{{ $actionLandingTitle }}</h1>

                        @if ($actionLandingLead)
                            <p class="birthday-landing__lead mb-2">{{ $actionLandingLead }}</p>
                        @endif

                        @if ($actionLandingBody)
                            <p class="birthday-landing__body mb-2">{{ $actionLandingBody }}</p>


                        @endif

                        @if ($actionLandingCurrentTitle)
                            <div class="birthday-landing__current mt-3">
                                Trenutno pregledavaš: <strong>{{ $actionLandingCurrentTitle }}</strong>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if ($isActionListing && $actionLandingCategories->isNotEmpty())
                <section class="discount-category-pills mb-4" aria-label="Kategorije na popustu">
                    <div class="discount-category-pills__inner">
                        @foreach ($actionLandingCategories as $actionCategory)
                            <a href="{{ $actionCategory['url'] }}" class="discount-category-pill{{ ! empty($actionCategory['is_active']) ? ' is-active' : '' }}">
                                <span class="discount-category-pill__title">{{ $actionCategory['title'] }}</span>
                                <span class="discount-category-pill__discount">-{{ $actionCategory['discount'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($listingIntro)
                <section class="mb-3">
                    <p class="fs-md text-muted mb-0">{{ $listingIntro }}</p>
                </section>
            @endif

            @if ($cat && ! $subcat && $isActionListing)

                @if ($cat->subcategories->isNotEmpty())
                    <section class="py-2 mb-3">
                        <div class="row">
                            <div class="col-lg-12 py-1">
                                <div class="scrolling-wrapper">
                                    @foreach ($cat->subcategories as $item)
                                        <a href="{{ route('catalog.route.actions', ['cat' => $cat, 'subcat' => $item]) }}"
                                           class="btn btn-dark btn-sm mb-2">
                                            <p class="py-0 mb-0 px-1">{{ $item->title }}</p>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </section>
                @endif

            @endif

            <products-view ids="{{ isset($ids) ? $ids : null }}"
                           group="{{ isset($group) ? $group : null }}"
                           cat="{{ isset($cat) ? $cat['id'] : null }}"
                           subcat="{{ isset($subcat) ? $subcat['id'] : null }}"
                           author="{{ isset($author) ? $author['slug'] : null }}"
                           publisher="{{ isset($publisher) ? $publisher['slug'] : null }}"
                           default-sort="{{ $isActionListing ? 'popular' : '' }}">
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
    @if ($isActionListing && $actionLandingCategories->isNotEmpty())
        <script type="application/ld+json">
            {!! collect(\App\Helpers\Metatags::itemListSchema(
                $actionLandingCategories->map(function ($item) {
                    return [
                        'name' => $item['title'] . ' (-' . $item['discount'] . ')',
                        'url' => $item['url'],
                    ];
                }),
                $actionLandingUrl ?: $listingSchemaUrl,
                'Kategorije na popustu'
            ))->toJson() !!}
        </script>
        <script type="application/ld+json">
            {!! collect(\App\Helpers\Metatags::offerCatalogSchema(
                'Rođendanski popusti po kategorijama',
                $actionLandingBody ?: $listingSeo['description'],
                $actionLandingUrl ?: $listingSchemaUrl,
                $actionLandingCategories->map(function ($item) {
                    return [
                        'name' => $item['title'],
                        'url' => $item['url'],
                        'discount' => $item['discount'],
                        'description' => 'Rođendanski popust ' . $item['discount'] . ' na kategoriju ' . $item['title'] . '.',
                    ];
                }),
                ($actionLandingUrl ?: $listingSchemaUrl) . '#offer-catalog'
            ))->toJson() !!}
        </script>
    @endif
    @if ($isActionListing && $actionLandingProducts->isNotEmpty())
        <script type="application/ld+json">
            {!! collect(\App\Helpers\Metatags::itemListSchema(
                $actionLandingProducts,
                $listingSchemaUrl,
                $actionLandingCurrentTitle ? 'Sniženi proizvodi: ' . $actionLandingCurrentTitle : 'Izdvojeni sniženi proizvodi'
            ))->toJson() !!}
        </script>
    @endif
    @if ($isActionListing && $actionLandingTitle)
        <script type="application/ld+json">
            {!! collect(\App\Helpers\Metatags::saleEventSchema(
                $actionLandingTitle,
                trim(collect([$actionLandingLead, $actionLandingBody])->filter()->implode(' ')),
                $actionLandingUrl ?: $listingSchemaUrl,
                $actionLandingPromotionStart,
                $actionLandingPromotionEnd,
                $listingImage ? \App\Models\Seo::image($listingImage) : null,
                ($actionLandingUrl ?: $listingSchemaUrl) . '#offer-catalog'
            ))->toJson() !!}
        </script>
    @endif
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

        .birthday-landing__panel {
            position: relative;
            overflow: hidden;
            padding: 1.1rem 1.35rem 1.15rem;
            border-radius: 1rem;
            background:
                radial-gradient(circle at top right, rgba(229, 0, 119, 0.12), transparent 26%),
                radial-gradient(circle at left center, rgba(53, 56, 74, 0.08), transparent 30%),
                linear-gradient(135deg, #ffffff 0%, #f8f9fc 46%, #fff3f8 100%);
            border: 1px solid rgba(229, 0, 119, 0.12);
            box-shadow: 0 0.8rem 1.8rem rgba(53, 56, 74, 0.08);
        }

        .birthday-landing__panel::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(53, 56, 74, 0.03) 0%, transparent 28%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.4) 0%, transparent 100%);
            pointer-events: none;
        }

        .birthday-landing__panel > * {
            position: relative;
            z-index: 1;
        }

        .birthday-landing__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-bottom: 0.45rem;
            padding: 0.32rem 0.68rem;
            border-radius: 999px;
            background: rgba(229, 0, 119, 0.08);
            color: #c32673;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .birthday-landing__title {
            color: #231f31;
            font-size: clamp(1.75rem, 3vw, 2.75rem);
            line-height: 1.02;
            letter-spacing: -0.03em;
        }

        .birthday-landing__lead {
            max-width: 60rem;
            color: #bb296f;
            font-size: clamp(1rem, 1.35vw, 1.15rem);
            font-weight: 700;
        }

        .birthday-landing__body {
            max-width: 66rem;
            color: #5b5d6f;
            font-size: 0.98rem;
            line-height: 1.5;
        }

        .birthday-landing__current {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            background: rgba(53, 56, 74, 0.06);
            color: #48485c;
            font-size: 0.9rem;
        }

        .discount-category-pills__inner {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.55rem;
        }

        .discount-category-pill {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.45rem;
            min-width: 0;
            padding: 0.55rem 0.75rem;
            border-radius: 999px;
            border: 1px solid rgba(229, 0, 119, 0.22);
            background: #fff;
            color: #4a3441;
            text-decoration: none;
            transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
            box-shadow: none;
        }

        .discount-category-pill:hover {
            color: #b80061;
            background: rgba(229, 0, 119, 0.04);
            border-color: rgba(229, 0, 119, 0.4);
        }

        .discount-category-pill.is-active {
            border-color: transparent;
            background: linear-gradient(135deg, #e50077 0%, #ff4b9a 100%);
            color: #fff;
            box-shadow: none;
        }

        .discount-category-pill__title {
            min-width: 0;
            font-size: 0.84rem;
            font-weight: 600;
            line-height: 1.15;
        }

        .discount-category-pill__discount {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 3.15rem;
            padding: 0.22rem 0.46rem;
            border-radius: 999px;
            background: rgba(229, 0, 119, 0.1);
            color: #d1006c;
            font-size: 0.76rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .discount-category-pill.is-active .discount-category-pill__discount {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
        }

        @media only screen and (min-width: 992px) {
            .birthday-landing__title {
                white-space: nowrap;
            }
        }

        @media only screen and (max-width: 1199px) {
            .discount-category-pills__inner {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media only screen and (max-width: 991px) {
            .discount-category-pills__inner {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media only screen and (max-width: 767px) {
            .birthday-landing__panel {
                padding: 1rem;
                border-radius: 0.95rem;
            }

            .birthday-landing__title {
                font-size: clamp(1.65rem, 8vw, 2.2rem);
            }

            .discount-category-pill {
                padding: 0.55rem 0.65rem;
            }

            .discount-category-pills__inner {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
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
