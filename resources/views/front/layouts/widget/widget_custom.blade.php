<!-- {"title": "Slider Index", "description": "Index main slider."} -->
@php
    $homeHeroCarouselOptions = [
        'items' => 1,
        'mode' => 'gallery',
        'nav' => true,
        'touch' => true,
        'swipeAngle' => 30,
        'preventActionWhenRunning' => true,
        'preventScrollOnTouch' => 'auto',
        'responsive' => [
            0 => ['nav' => true, 'controls' => false],
            576 => ['nav' => false, 'controls' => true],
        ],
    ];
    $countdownTarget = \Carbon\CarbonImmutable::create(2026, 5, 1, 0, 0, 0, config('app.timezone', 'Europe/Zagreb'));
    $countdownNow = now(config('app.timezone', 'Europe/Zagreb'));
    $countdownDiff = max($countdownNow->diffInSeconds($countdownTarget, false), 0);
    $countdownDays = intdiv($countdownDiff, 86400);
    $countdownHours = intdiv($countdownDiff % 86400, 3600);
    $countdownMinutes = intdiv($countdownDiff % 3600, 60);
    $countdownSeconds = $countdownDiff % 60;
    $homeHeroMediaWrapStyle = 'width: min(350px, 100%);';
    $homeHeroMediaStyle = 'display: block; width: 100%; aspect-ratio: 1 / 1; height: auto; object-fit: cover; border-radius: 10px;';
@endphp

<section class="tns-carousel mb-3 rounded-3 bg-light shadow widget-touch-carousel">
    <div class="tns-carousel-inner" data-carousel-options='@json($homeHeroCarouselOptions)'>
        @foreach($data as  $widget)

            <div>
                <div class="pt-3  px-md-5 text-center text-xl-start   px-2 mb-3 " >
                    <div class="d-xl-flex justify-content-between align-items-center px-4  mx-auto" style="max-width: 1226px;">
                        <div class=" py-sm-3 pb-0 me-xl-4 mx-auto mx-xl-0" style="max-width: 550px;">
                           <span class="badge bg-primary  mb-1 fs-md">Akcija u tijeku!</span>
                            <div class="countdown mt-2 mb-2 justify-content-center justify-content-xl-start"
                                 data-countdown="{{ $countdownTarget->toIso8601String() }}"
                                 style="gap: .35rem;">
                                <div class="countdown-days bg-white border rounded-3 px-2 py-1 text-center shadow-sm" style="min-width: 54px; margin-right: 0; margin-bottom: 0;">
                                    <span class="countdown-value text-primary fw-bold d-block lh-1" style="font-size: 1.15rem;">{{ str_pad((string) $countdownDays, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="countdown-label text-muted text-uppercase d-block" style="margin-left: 0; font-size: .58rem; letter-spacing: .04em;">Dana</span>
                                </div>
                                <div class="countdown-hours bg-white border rounded-3 px-2 py-1 text-center shadow-sm" style="min-width: 54px; margin-right: 0; margin-bottom: 0;">
                                    <span class="countdown-value text-primary fw-bold d-block lh-1" style="font-size: 1.15rem;">{{ str_pad((string) $countdownHours, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="countdown-label text-muted text-uppercase d-block" style="margin-left: 0; font-size: .58rem; letter-spacing: .04em;">Sati</span>
                                </div>
                                <div class="countdown-minutes bg-white border rounded-3 px-2 py-1 text-center shadow-sm" style="min-width: 54px; margin-right: 0; margin-bottom: 0;">
                                    <span class="countdown-value text-primary fw-bold d-block lh-1" style="font-size: 1.15rem;">{{ str_pad((string) $countdownMinutes, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="countdown-label text-muted text-uppercase d-block" style="margin-left: 0; font-size: .58rem; letter-spacing: .04em;">Min</span>
                                </div>
                                <div class="countdown-seconds bg-white border rounded-3 px-2 py-1 text-center shadow-sm" style="min-width: 54px; margin-right: 0; margin-bottom: 0;">
                                    <span class="countdown-value text-primary fw-bold d-block lh-1" style="font-size: 1.15rem;">{{ str_pad((string) $countdownSeconds, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="countdown-label text-muted text-uppercase d-block" style="margin-left: 0; font-size: .58rem; letter-spacing: .04em;">Sek</span>
                                </div>
                            </div>

                            <h4 class="h2 text-primary font-title mb-3 mb-sm-1">{{ $widget['title'] }} </h4>

                            <p class="text-dark  ">{{ $widget['subtitle'] }}</p>
                            <div class="d-flex flex-wrap justify-content-center justify-content-xl-start"><a class="btn btn-primary btn-shadow me-2 mb-2 slider-focus-btn" href="{{ url($widget['url']) }}" role="button">

Pogledajte akcije
                                     <i class="ci-arrow-right ms-2 me-n1"></i></a></div>
                        </div>
                        <div class="p-3">
                            <a href="{{ url($widget['url']) }}" style="{{ $homeHeroMediaWrapStyle }}">
                                @if (! empty($widget['video']))
                                    <video autoplay loop muted playsinline preload="metadata" poster="{{ $widget['image'] ?? '' }}" style="{{ $homeHeroMediaStyle }}">
                                        <source src="{{ $widget['video'] }}">
                                    </video>
                                @elseif (! empty($widget['image']))
                                    <img src="{{ $widget['image'] }}" alt="{{ $widget['title'] }}" width="350" height="350" style="{{ $homeHeroMediaStyle }}">
                                @endif
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>




<!-- How it works-->


@push('js_after')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const firstBtn = document.querySelector('.slider-focus-btn');
            if (firstBtn) {
                firstBtn.focus();
            }
        });
    </script>
@endpush
