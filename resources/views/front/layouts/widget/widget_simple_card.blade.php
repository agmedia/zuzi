<!-- {"title": "Banneri", "description": "Widget za bannere"} -->



<section class=" py-3 " >


    <div class="row  mt-2 mt-lg-3 ">

        <div class="col-lg-12 col-xl-12 mb-grid-gutter">
            <div class="d-block d-sm-flex justify-content-between align-items-center bg-light shadow   rounded-3">
                <div class="pt-5 py-sm-4 px-4 ps-md-4 pe-md-0 text-center text-sm-start">
                    @foreach ($data as $widget)
                    {!! $widget['subtitle'] !!}
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</section>
