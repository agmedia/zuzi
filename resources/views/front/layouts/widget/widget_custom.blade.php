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
@endphp

<section class="tns-carousel mb-3 rounded-3 bg-light shadow widget-touch-carousel">
    <div class="tns-carousel-inner" data-carousel-options='@json($homeHeroCarouselOptions)'>
        @foreach($data as  $widget)

            <div>
                <div class="pt-3  px-md-5 text-center text-xl-start   px-2 mb-3 " >
                    <div class="d-xl-flex justify-content-between align-items-center px-4  mx-auto" style="max-width: 1226px;">
                        <div class=" py-sm-3 pb-0 me-xl-4 mx-auto mx-xl-0" style="max-width: 550px;">
                         <!--   <span class="badge bg-primary  mb-1 fs-md">Rođendanska akcija!</span>-->
                            <h4 class="h2 text-primary font-title mb-3 mb-sm-1">{{ $widget['title'] }} </h4>

                            <p class="text-dark  ">{{ $widget['subtitle'] }}</p>
                            <div class="d-flex flex-wrap justify-content-center justify-content-xl-start"><a class="btn btn-primary btn-shadow me-2 mb-2 slider-focus-btn" href="{{ url($widget['url']) }}" role="button">

                                         Opširnije

                                     <i class="ci-arrow-right ms-2 me-n1"></i></a></div>
                        </div>
                        <div class="p-3"><a  href="{{ url($widget['url']) }}" ><img src="{{ $widget['image'] }}" alt="{{ $widget['title'] }}" width="350" height="350" style="border-radius: 10px;"></a></div>
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
            // Nađi prvi gumb sa klasom .slider-focus-btn i fokusiraj ga
            const firstBtn = document.querySelector('.slider-focus-btn');
            if (firstBtn) {
                firstBtn.focus();
            }
        });
    </script>
@endpush
