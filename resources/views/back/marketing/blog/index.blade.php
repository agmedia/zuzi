@extends('back.layouts.backend')

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Blog</h1>
                <a class="btn btn-hero-success my-2" href="{{ route('blogs.create') }}">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> Novi post</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="content content-full">
    @include('back.layouts.partials.session')


        <!-- Posts -->
        <div class="block">
            <div class="block-header block-header-default">
                <h3 class="block-title">Objave</h3>
                <div class="block-options">
                    <!-- Search Form -->
                    <form action="{{ route('blogs') }}" method="GET">
                        <div class="block-options-item">
                            <input type="text" class="form-control" id="search-input" name="search" placeholder="Pretraži autore" value="{{ request()->query('search') }}">
                        </div>
                        <div class="block-options-item">
                            <a href="{{ route('blogs') }}" class="btn btn-hero-sm btn-secondary"><i class="fa fa-search-minus"></i> Očisti</a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="block-content">
                <table class="table table-striped table-borderless table-vcenter">
                    <thead class="thead-light">
                    <tr>
                        <th style="width: 60px;">Slika</th>
                        <th style="width: 33%;">Naziv</th>
                        <th >Kreirano</th>
                        <th >Objavljeno</th>
                        <th style="width: 100px;" class="text-center">Uredi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($blogs as $blog)
                        <tr>
                            <td>
                                <a href="{{ route('blogs.edit', ['blog' => $blog]) }}">
                                    <img src="{{ asset($blog->image) }}" height="80px"/>
                                </a>
                            </td>
                            <td>
                                <i class="fa fa-eye text-success mr-1"></i>
                                <a href="{{ route('blogs.edit', ['blog' => $blog]) }}">{{ $blog->title }}</a>
                            </td>
                            <td>
                                {{ \Illuminate\Support\Carbon::make($blog->created_at)->format('d.m.Y') }}
                            </td>
                            <td>
                                {{ isset($blog->publish_date) ? \Illuminate\Support\Carbon::make($blog->publish_date)->format('d.m.Y u h:i') : '' }}
                            </td>
                            <td class="text-right font-size-sm">
                                <a class="btn btn-sm btn-alt-secondary" href="{{ route('blogs.edit', ['blog' => $blog]) }}">
                                    <i class="fa fa-fw fa-pencil-alt"></i>
                                </a>
                                <button class="btn btn-sm btn-alt-danger" onclick="event.preventDefault(); deleteItem({{ $blog->id }}, '{{ route('blogs.destroy.api') }}');"><i class="fa fa-fw fa-trash-alt"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr class="text-center">
                            <td colspan="6">Nema objava...</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $blogs->links() }}
            </div>
        </div>
        <!-- END Posts -->
    </div>
    <!-- END Page Content -->

@endsection

@push('js_after')

@endpush
