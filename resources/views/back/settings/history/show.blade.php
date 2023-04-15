@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')

    <div class="bg-image" style="background-image: url({{ asset('media/photos/photo21@2x.jpg') }});">
        <div class="bg-gd-white-op-rl">
            <div class="content content-boxed text-center py-5">
                <h1 class="h2 mb-2">{!! substr($history->title, 0, strpos($history->title, '.')) !!}</h1>
                <p class="font-size-lg font-w400 text-muted mb-0">
                    Korisnik, <a href="{{ route('users.edit', ['user' => $user]) }}" class="text-info">{{ $user->name }}</a>
                </p>
            </div>
        </div>
    </div>

    <div class="content content-boxed">
        @include('back.layouts.partials.session')

        <h2 class="content-heading">
            <i class="fa fa-briefcase text-muted mr-1"></i> Datum upisa: {{ $history->created_at->format('d.m.Y - h:i') }} <span class="float-right">{{ $history->created_at->locale('hr_HR')->diffForHumans() }}</span>
        </h2>
        <div class="block block-rounded">
            <div class="block-content block-content-full">
                <div class="row">
                    <div class="col-sm-12 col-md-3 text-center">
                        <a class="img-link mt-3" href="{{ $history->getTargetUrl() }}">
                            <img src="{{ isset($target->image) ? asset($target->image) : asset('media/avatars/avatar0.jpg') }}" height="140px"/>
                        </a>
                    </div>
                    <div class="col-sm-12 col-md-9 py-2">
                        <a class="link-fx h4 mb-1 d-inline-block text-dark" href="be_pages_jobs_listing.html">
                            <a href="{{ $history->getTargetUrl() }}">{!! $history->title !!}</a>
                        </a>
                        <div class="font-size-sm font-w600 text-muted mb-2">
                            Korisnik <a href="{{ route('users.edit', ['user' => $user]) }}" class="text-info">{{ $user->name }}</a> - {{ $history->created_at->locale('hr_HR')->diffForHumans() }}
                        </div>
                        <p class="text-muted mb-2 pt-2">
                            {!! $history->changes !!}
                        </p>
<!--                        <div>
                            <a class="badge badge-primary font-w600" href="javascript:void(0)">Web</a>
                            <a class="badge badge-primary font-w600" href="javascript:void(0)">React</a>
                            <a class="badge badge-primary font-w600" href="javascript:void(0)">Social</a>
                        </div>-->
                    </div>
                </div>
            </div>
        </div>

        <h2 class="content-heading">
            <i class="fa fa-plus text-success mr-1"></i> Stari model vs. novi model
        </h2>
        <div class="block block-rounded bg-white">
            <!-- Job Information section -->
            <div class="block-content block-content-full">
                <div class="row">
                    <div class="col-sm-12 col-md-6">
                        <h2 class="content-heading">Stari model</h2>
                        <pre id="old-view"></pre>
                    </div>
                    <div class="col-sm-12 col-md-6">
                        <h2 class="content-heading">Novi model</h2>
                        <pre id="new-view"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js_after')
    <script src="{{ asset('js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>

    <script>
        $(() => {
            let str = JSON.stringify({!! collect($old)->toJson() !!}, undefined, 4);
            let new_str = JSON.stringify({!! collect($new)->toJson() !!}, undefined, 4);

            $('#old-view').html(str)
            $('#new-view').html(new_str)
        })
    </script>

@endpush
