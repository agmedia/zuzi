<!-- {"title": "Simple Widget", "description": "Some description of a Simple Widget."} -->
<div class="container clearfix">
    @if($data->count() == 1)
        <div class="heading-block center bottommargin">
            <h2>{{ $data->first()->title }}</h2>
            <span>{{ $data->first()->subtitle }}</span>
        </div>
    @endif

    @if($data->count() == 3)
        <div class="row bottommargin topmargin">
            @foreach($data as $i => $item)
                <div class="col_one_third {{ $i == 2 ? 'col_last' : '' }}">
                    <div class="feature-box fbox-center fbox-plain">
                        <h3>{{ $item->title }}</h3>
                        <p>{{ $item->subtitle }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
