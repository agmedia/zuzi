<!-- {"title": "Page Carousel", "description": "Some description of a Page Carousel widget template."} -->
<section class="border-top mb-0 pb-5 py-5" style="background-image: url({{ $data['background'] ? url('cache/image?src=media/img/glag.png') : '' }});-webkit-background-size: cover;-moz-background-size: cover;-o-background-size: cover;background-size: cover;">
    <div class="container py-lg-3">
        <h2 class="h3 text-center">{{ $data['title'] }}</h2>
        <p class="text-muted-light text-center mb-3 pb-4">{{ $data['subtitle'] }}</p>
        <div class="tns-carousel pb-5">
            <div class="tns-carousel-inner" data-carousel-options="{&quot;items&quot;: 2, &quot;gutter&quot;: 15, &quot;controls&quot;: false, &quot;nav&quot;: true, &quot;responsive&quot;: {&quot;0&quot;:{&quot;items&quot;:1},&quot;500&quot;:{&quot;items&quot;:2},&quot;768&quot;:{&quot;items&quot;:3}, &quot;992&quot;:{&quot;items&quot;:3, &quot;gutter&quot;: 30}}}">
            @foreach ($data['items'] as $item)
                <!-- Product-->
                    <div>
                        <div class="card"><a class="blog-entry-thumb" href="{{ route('catalog.route.blog', ['blog' => $item]) }}"><img class="card-img-top" load="lazy" src="{{ $item['image'] }}" width="400" height="230" alt="{{ $item['title'] }}"></a>
                            <div class="card-body">
                                <h2 class="h6 blog-entry-title"><a href="{{ route('catalog.route.blog', ['blog' => $item]) }}">{{ $item['title'] }}</a></h2>
                                <p class="fs-sm">{{ $item['short_description'] }}</p>
                                <div class="fs-xs text-nowrap"><a class="blog-entry-meta-link text-nowrap" href="#">{{ \Carbon\Carbon::make($item['created_at'])->locale('hr')->format('d.m.Y.') }}</a></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
