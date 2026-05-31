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
    $isReviewWidget = ($data['tablename'] ?? null) === 'reviews';
    $hasBackground = ! empty($data['background']);
    $hasContainer = ! empty($data['container']);
    $customCss = trim((string) ($data['css'] ?? ''));
    $reviewInitialLimit = 8;
    $reviewBatchSize = 8;
    $reviewItems = $isReviewWidget
        ? collect($data['items'] ?? [])->sortByDesc('created_at')->values()
        : collect();
    $sectionClasses = collect([
        'page-carousel-widget',
        'py-0',
        'pt-5',
        $customCss ?: null,
        $hasBackground ? 'page-carousel-widget--background' : null,
        $hasContainer ? 'page-carousel-widget--container' : null,
        $isReviewWidget ? 'review-widget-section' : null,
        $isReviewWidget && $hasBackground ? 'review-widget-section--background' : null,
    ])->filter()->implode(' ');
@endphp
<section class="{{ $sectionClasses }}">
    <div class="page-carousel-widget__header d-flex flex-wrap justify-content-between align-items-center pt-1 pb-3 mb-2">
        <div class="{{ $isReviewWidget ? 'review-widget-heading' : '' }}">
            <h2 class="h3 mb-0 pt-0 font-title me-3"> {{ $data['title'] }}  @if($data['subtitle'])  <span class="d-block fw-normal  text-dark opacity-80 mt-1 fs-base">{{ $data['subtitle'] }}</span> @endif</h2>
        </div>
        @if ($data['tablename'] == 'blog')
            <a class="btn btn-primary btn-sm btn-shadow mt-0" href="/blog"><span class="d-none d-sm-inline-block">Pogledajte sve</span> <i class="ci-arrow-right "></i></a>
        @elseif ($isReviewWidget)
            <div class="review-widget-cta">
                <a class="btn btn-primary btn-sm btn-shadow" href="{{ route('moji-dojmovi') }}">Podijeli dojam</a>
            </div>
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

        <div class="review-widget-masonry" data-review-masonry data-review-batch="{{ $reviewBatchSize }}" style="columns: 15rem 4; column-gap: 1.25rem;">
            @foreach ($reviewItems as $review)
                @php
                    $reviewProduct = $review->product;
                    $reviewProductTitle = $reviewProduct->name ?? 'Obrisan artikl';
                    $reviewUrl = $reviewProduct && filled($reviewProduct->url)
                        ? url($reviewProduct->url)
                        : null;
                    $reviewReviewsUrl = $reviewUrl ? $reviewUrl . '#reviews' : null;
                    $reviewProductImage = null;

                    if ($reviewProduct && filled($reviewProduct->image ?? null)) {
                        $reviewProductImage = (string) $reviewProduct->image;

                        if (! \Illuminate\Support\Str::contains($reviewProductImage, '-thumb.')) {
                            $reviewProductImage = preg_replace('/\.(jpe?g|png|webp)$/i', '-thumb.webp', $reviewProductImage);
                        }

                        if (! \Illuminate\Support\Str::startsWith($reviewProductImage, ['http://', 'https://'])) {
                            $reviewProductImage = rtrim((string) config('settings.images_domain'), '/') . '/' . ltrim($reviewProductImage, '/');
                        }
                    }

                    $reviewProductImageAlt = 'Naslovnica knjige ' . $reviewProductTitle;
                @endphp
                <div class="review-widget-masonry-item" data-review-item @if($loop->index >= $reviewInitialLimit) hidden @endif style="display: inline-block; width: 100%; margin-bottom: 1rem; break-inside: avoid; -webkit-column-break-inside: avoid; page-break-inside: avoid;">
                    <blockquote class="review-widget-quote">
                        <div class="card card-body fs-md text-muted shadow review-widget-card">
                            <div class="review-widget-product-head" style="display: flex; align-items: flex-start; gap: .75rem; margin-bottom: .75rem;">
                                @if ($reviewProductImage)
                                    <a class="review-widget-product-image-link" href="{{ $reviewReviewsUrl ?: $reviewUrl }}" style="display: block; flex: 0 0 20%; width: 20%; max-width: 3.5rem; min-width: 2.25rem;">
                                        <img class="review-widget-product-image" loading="lazy" src="{{ $reviewProductImage }}" alt="{{ $reviewProductImageAlt }}" style="display: block; width: 100%; max-width: 100%; height: auto; aspect-ratio: 2 / 3; object-fit: contain;">
                                    </a>
                                @endif

                                <div class="review-widget-product-copy" style="flex: 1 1 auto; min-width: 0; padding-top: .1rem;">
                                    @if ($reviewReviewsUrl)
                                        <a class="review-widget-title text-decoration-none mb-1" href="{{ $reviewReviewsUrl }}">
                                            {{ $reviewProductTitle }}
                                        </a>
                                    @else
                                        <div class="review-widget-title text-muted mb-1">{{ $reviewProductTitle }}</div>
                                    @endif

                                    <div class="review-widget-stars">
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
                                </div>
                            </div>

                            @if($review->title)
                                <div class="review-widget-review-title mb-2" style="color: #373f50; font-weight: 700; line-height: 1.35;">{{ $review->title }}</div>
                            @endif
                            <div class="review-widget-message" style="display: -webkit-box; -webkit-line-clamp: 8; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.6; max-height: 12.8em; text-overflow: ellipsis;">{{ strip_tags($review->message) }}</div>
                            <footer class="review-widget-card-footer d-flex flex-wrap justify-content-between align-items-center pt-3 mt-auto">
                                <span class="review-widget-author d-inline-flex align-items-center">
                                    <i class="ci-user me-2"></i>{{ $review->fname }} {{ $review->lname }}
                                </span>
                                @if ($reviewReviewsUrl)
                                    <a class="review-widget-link text-decoration-none" href="{{ $reviewReviewsUrl }}" style="line-height: 1.2; text-align: right;">Pročitaj više</a>
                                @endif
                            </footer>
                        </div>
                    </blockquote>
                </div>
                @endforeach
        </div>

        @if ($reviewItems->count() > $reviewInitialLimit)
            <div class="text-center pt-2">
                <button class="btn btn-outline-primary" type="button" data-review-load-more>
                    Vidi još
                </button>
            </div>
        @endif

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
