<!-- {"title": "Page Carousel", "description": "Category, Publisher, Reviews."} -->
@php
    $categoryWidgetCarouselOptions = [
        'items' => 2,
        'controls' => true,
        'autoHeight' => false,
        'mouseDrag' => true,
        'touch' => true,
        'swipeAngle' => 30,
        'preventActionWhenRunning' => true,
        'preventScrollOnTouch' => 'auto',
        'responsive' => [
            0 => ['items' => 2, 'gutter' => 10, 'controls' => false],
            480 => ['items' => 2, 'gutter' => 10, 'controls' => true],
            800 => ['items' => 3, 'gutter' => 15],
            1300 => ['items' => 4, 'gutter' => 20],
            1400 => ['items' => 5, 'gutter' => 20],
        ],
    ];
    $reviewWidgetCarouselOptions = [
        'items' => 1,
        'controls' => true,
        'nav' => true,
        'autoplay' => true,
        'autoHeight' => false,
        'mouseDrag' => true,
        'touch' => true,
        'swipeAngle' => 30,
        'preventActionWhenRunning' => true,
        'preventScrollOnTouch' => 'auto',
        'responsive' => [
            0 => ['items' => 1, 'gutter' => 20],
            480 => ['items' => 2, 'gutter' => 20],
            800 => ['items' => 3, 'gutter' => 20],
            1300 => ['items' => 4, 'gutter' => 30],
        ],
    ];
    $blogWidgetCarouselOptions = [
        'items' => 2,
        'gutter' => 15,
        'controls' => false,
        'nav' => true,
        'mouseDrag' => true,
        'touch' => true,
        'swipeAngle' => 30,
        'preventActionWhenRunning' => true,
        'preventScrollOnTouch' => 'auto',
        'responsive' => [
            0 => ['items' => 1],
            500 => ['items' => 2],
            768 => ['items' => 2],
            992 => ['items' => 3, 'gutter' => 30],
        ],
    ];
@endphp
@once
    @push('css_after')
        <style>
            .review-widget-carousel .tns-slider {
                display: flex;
                align-items: stretch;
            }

            .review-widget-carousel .tns-item {
                display: flex;
                height: auto !important;
            }

            .review-widget-carousel .tns-item > * {
                display: flex;
                width: 100%;
            }

            .review-widget-slide {
                display: flex;
                flex: 1 1 auto;
                width: 100%;
            }

            .review-widget-quote {
                display: flex;
                flex-direction: column;
                flex: 1 1 auto;
                height: 100%;
                margin-bottom: 0;
            }

            .review-widget-card {
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                height: 100%;
                min-height: 12.5rem;
            }

            .review-widget-title {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                min-height: 2.8rem;
                font-weight: 700;
                line-height: 1.4;
            }

            .review-widget-message {
                display: -webkit-box;
                -webkit-line-clamp: 4;
                -webkit-box-orient: vertical;
                overflow: hidden;
                line-height: 1.6;
                min-height: 6.4em;
            }

            .review-widget-carousel [data-controls] {
                z-index: 2;
            }

            @media (max-width: 767.98px) {
                .review-widget-card {
                    min-height: 11.5rem;
                }
            }
        </style>
    @endpush
@endonce
<section class=" py-0 pt-5" >
    <div class="d-flex flex-wrap justify-content-between align-items-center pt-1  pb-3 mb-2">
        <h2 class="h3 mb-0 pt-0 font-title me-3"> {{ $data['title'] }}  @if($data['subtitle'])  <span class="d-block fw-normal  text-dark opacity-80 mt-1 fs-base">{{ $data['subtitle'] }}</span> @endif</h2>
        @if ($data['tablename'] == 'blog')
            <a class="btn btn-primary btn-sm btn-shadow mt-0" href="/blog"><span class="d-none d-sm-inline-block">Pogledajte sve</span> <i class="ci-arrow-right "></i></a>
        @endif
    </div>

    @if ($data['tablename'] == 'category')
        <div class="tns-carousel widget-touch-carousel widget-card-carousel">
            <div class="tns-carousel-inner" data-carousel-options='@json($categoryWidgetCarouselOptions)'>
                @foreach ($data['items'] as $item)
                    <!-- Product-->
                    <div class="article mb-grid-gutter">
                        <a class="card border-0 shadow" href="{{ $item['group'] }}/{{ $item['slug'] }}">
                            <span class="blog-entry-meta-label fs-sm"><i class="ci-book text-primary me-0"></i></span>
                            <img class="card-img-top" loading="lazy" width="400" height="300" src="{{ $item['image'] }}" alt="Kategorija {{ $item['title'] }}">
                            <div class="card-body py-2 text-center px-0">
                                <h3 class="h6 mt-1 font-title text-primary">{{ $item['title'] }}</h3>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>

    @elseif ($data['tablename'] == 'publisher')
        <div class="row pb-2 pb-sm-0 pb-md-3">
            @foreach ($data['items'] as $item)
                <div class="col-md-3 col-sm-4 col-6"><a class="d-block bg-white shadow-sm rounded-3 py-3 py-sm-4 mb-grid-gutter" href="{{ $item['url'] }}"><img loading="lazy" class="d-block mx-auto" src="{{ $item['image'] }}" style="width: 150px;" alt="{{ $item['title'] }}"></a></div>
            @endforeach
        </div>

    @elseif ($data['tablename'] == 'reviews')

        <div class="tns-carousel tns-controls-outside widget-touch-carousel widget-card-carousel review-widget-carousel">
            <div class="tns-carousel-inner" data-carousel-options='@json($reviewWidgetCarouselOptions)'>
                @foreach ($data['items'] as $review)
                    @php
                        $reviewProduct = $review->product;
                        $reviewProductTitle = $reviewProduct->name ?? 'Obrisan artikl';
                        $reviewUrl = $reviewProduct && filled($reviewProduct->url)
                            ? url($reviewProduct->url)
                            : null;
                    @endphp
                    <div class="review-widget-slide">
                        <blockquote class="mb-2 review-widget-quote">
                            <div class="card card-body fs-md text-muted border-0 shadow-sm review-widget-card">
                                @if ($reviewUrl)
                                    <a class="review-widget-title text-decoration-none mb-2" href="{{ $reviewUrl }}">
                                        {{ $reviewProductTitle }}
                                    </a>
                                @else
                                    <div class="review-widget-title text-muted mb-2">{{ $reviewProductTitle }}</div>
                                @endif

                                <div class="mb-2">
                                <div class="star-rating"> @for ($i = 0; $i < 5; $i++)
                                        @if (floor($review->stars) - $i >= 1)
                                            {{--Full Start--}}
                                            <i class="star-rating-icon ci-star-filled active"></i>
                                        @elseif ($review->stars - $i > 0)
                                            {{--Half Start--}}
                                            <i class="star-rating-icon ci-star"></i>
                                        @else
                                            {{--Empty Start--}}
                                            <i class="star-rating-icon ci-star"></i>
                                        @endif
                                    @endfor
                                </div>
                                </div>

                                <div class="review-widget-message">{{ strip_tags($review->message) }}</div>
                            </div>
                            <footer class="d-flex justify-content-center align-items-center pt-4 mt-auto">
                                <div class="ps-3">
                                    <p class="fs-sm fw-bold text-default mb-n1">{{ $review->fname }} {{ $review->lname }}</p>
                                </div>
                            </footer>
                        </blockquote>
                    </div>
                @endforeach
            </div>
        </div>

    @else
        <div class="tns-carousel pb-5 widget-touch-carousel widget-card-carousel">
            <div class="tns-carousel-inner" data-carousel-options='@json($blogWidgetCarouselOptions)'>
                @foreach ($data['items'] as $item)

                    <!-- Product-->
                    <div>
                        <div class="card"><a class="blog-entry-thumb" href="{{ route('catalog.route.blog', ['blog' => $item]) }}"><img class="card-img-top" loading="lazy" src="{{ $item['image'] }}" width="600" height="250" alt="{{ $item['title'] }}" style="width:  600px;height: 250px;object-fit: cover;"></a>
                            <div class="card-body">
                                <h2 class="h6 blog-entry-title"><a href="{{ route('catalog.route.blog', ['blog' => $item]) }}">{{ $item['title'] }}</a></h2>
                                <p class="fs-sm"> {!! Str::limit($item['short_description'], 180, ' ...') !!}</p>
                                <div class="fs-xs text-nowrap"><a class="blog-entry-meta-link text-nowrap" href="#">{{ \Carbon\Carbon::make($item['created_at'])->locale('hr')->format('d.m.Y.') }}</a></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    @endif



</section>
