@extends('back.layouts.backend')

@section('content')

    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Često postavljana pitanja - FAQ</h1>
                <a class="btn btn-hero-success my-2" href="{{ route('faqs.create') }}">
                    <i class="far fa-fw fa-plus-square"></i><span class="d-none d-sm-inline ml-1"> Dodaj novo</span>
                </a>
            </div>
        </div>
    </div>

    <div class="content content-full">
    @include('back.layouts.partials.session')

        <div class="block">
            <div class="block-header block-header-default">
                <h3 class="block-title">FAQ ({{ $faqs->total() }})</h3>
            </div>
            <div class="block-content">
                <table class="table table-striped table-borderless table-vcenter">
                    <thead class="thead-light">
                    <tr>

                        <th style="width: 80%;">Pitanje</th>

                        <th class="text-right"  class="text-center">Uredi</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($faqs as $faq)
                        <tr>
                            <td>
                                <a href="{{ route('faqs.edit', ['faq' => $faq]) }}">{{ $faq->title }}</a>
                            </td>
                            <td class="text-right font-size-sm">
                                <a class="btn btn-sm btn-alt-secondary" href="{{ route('faqs.edit', ['faq' => $faq]) }}">
                                    <i class="fa fa-fw fa-pencil-alt"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr class="text-center">
                            <td colspan="2">Nema čestih pitanja...</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $faqs->links() }}
            </div>
        </div>
    </div>
@endsection

@push('js_after')

@endpush
