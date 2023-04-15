<!-- {"title": "Simple Card Widget", "description": "Some description of a Simple Card Widget."} -->
<section class="container pb-3 mb-md-3">
    <div class="row">
        @foreach ($data as $widget)
            <div class="col-md-{{ $widget['width'] }} mb-4">
                <div class="card bg-third">
                    <div class="row g-0 d-sm-flex justify-content-between align-items-center">
                        @if ($widget['right'])
                            <div class="col-7">
                                <div class="card-body ps-md-4">
                                    <h3 class="mb-4">{{ $widget['title'] }}</h3>
                                    <a class="btn btn-primary btn-shadow btn-sm" href="{{ url($widget['url']) }}">Pogledajte ponudu <i class="ci-arrow-right "></i></a>
                                </div>
                            </div>
                            <div class="col-5">
                                <img src="{{ $widget['image'] }}" load="lazy" width="250" height="210" class="rounded-start" alt="Card image">
                            </div>
                        @else
                            <div class="col-5">
                                <img src="{{ $widget['image'] }}" load="lazy" width="250" height="210" class="rounded-start" alt="Card image">
                            </div>
                            <div class="col-7">
                                <div class="card-body ps-md-4">
                                    <h3 class="mb-4">{{ $widget['title'] }}</h3>
                                    <a class="btn btn-primary btn-shadow btn-sm" href="{{ url($widget['url']) }}">Pogledajte ponudu <i class="ci-arrow-right "></i></a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
