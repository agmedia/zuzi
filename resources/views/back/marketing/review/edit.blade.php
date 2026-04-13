@extends('back.layouts.backend')

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Uredi komentar</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('reviews') }}">Komentari</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Uredi komentar</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content content-full content-boxed">
        @include('back.layouts.partials.session')

        <form action="{{ route('reviews.update', ['review' => $review]) }}" method="POST">
            @csrf
            {{ method_field('PATCH') }}

            <div class="block">
                <div class="block-header block-header-default">
                    <a class="btn btn-light" href="{{ route('reviews') }}">
                        <i class="fa fa-arrow-left mr-1"></i> Povratak
                    </a>
                    <div class="block-options">
                        <div class="custom-control custom-switch custom-control-info block-options-item mr-3">
                            <input type="checkbox" class="custom-control-input" id="featured-switch" name="featured" {{ $review->featured ? 'checked' : '' }}>
                            <label class="custom-control-label" for="featured-switch">Istaknuto</label>
                        </div>
                        <div class="custom-control custom-switch custom-control-success block-options-item">
                            <input type="checkbox" class="custom-control-input" id="status-switch" name="status" {{ $review->status ? 'checked' : '' }}>
                            <label class="custom-control-label" for="status-switch">Odobren</label>
                        </div>
                    </div>
                </div>

                <div class="block-content">
                    <div class="row justify-content-center push">
                        <div class="col-md-10">
                            <div class="form-group row">
                                <div class="col-md-8">
                                    <label>Artikl</label>
                                    <div class="form-control bg-body-light d-flex align-items-center justify-content-between">
                                        <span>
                                            @if ($review->product)
                                                {{ $review->product->name }}
                                                <small class="d-block text-muted">#{{ $review->product_id }} · {{ $review->product->sku }}</small>
                                            @else
                                                Obrisan artikl (#{{ $review->product_id }})
                                            @endif
                                        </span>
                                        @if ($review->product)
                                            <a class="btn btn-sm btn-alt-secondary" href="{{ route('products.edit', ['product' => $review->product]) }}">Otvori artikl</a>
                                        @endif
                                    </div>
                                    <input type="hidden" name="product_id" value="{{ old('product_id', $review->product_id) }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="lang-input">Jezik</label>
                                    <input type="text" class="form-control" id="lang-input" name="lang" value="{{ old('lang', $review->lang ?: app()->getLocale()) }}" maxlength="2">
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-md-6">
                                    <label for="name-input">Ime i prezime</label>
                                    <input type="text" class="form-control" id="name-input" name="name" value="{{ old('name', trim($review->fname . ' ' . $review->lname)) }}">
                                </div>
                                <div class="col-md-6">
                                    <label for="email-input">Email</label>
                                    <input type="email" class="form-control" id="email-input" name="email" value="{{ old('email', $review->email) }}">
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-md-4">
                                    <label for="stars-input">Ocjena</label>
                                    <select class="form-control" id="stars-input" name="stars">
                                        @for ($i = 5; $i >= 1; $i--)
                                            <option value="{{ $i }}" {{ (string) old('stars', (int) $review->stars) === (string) $i ? 'selected' : '' }}>{{ $i }} / 5</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="sort-order-input">Sort</label>
                                    <input type="number" class="form-control" id="sort-order-input" name="sort_order" value="{{ old('sort_order', $review->sort_order) }}">
                                </div>
                                <div class="col-md-4">
                                    <label>Zaprimljeno</label>
                                    <div class="form-control bg-body-light">{{ optional($review->created_at)->format('d.m.Y H:i') }}</div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="message-input">Komentar</label>
                                <textarea class="form-control" id="message-input" name="message" rows="8">{{ old('message', $review->message) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="block-content bg-body-light">
                    <div class="row justify-content-center push">
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-hero-success my-2">
                                <i class="fas fa-save mr-1"></i> Snimi
                            </button>
                        </div>
                        <div class="col-md-5 text-right">
                            <a href="{{ route('reviews.destroy', ['review' => $review]) }}" class="btn btn-hero-danger my-2" onclick="event.preventDefault(); document.getElementById('delete-review-form{{ $review->id }}').submit();">
                                <i class="fa fa-trash-alt"></i> Obriši
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <form id="delete-review-form{{ $review->id }}" action="{{ route('reviews.destroy', ['review' => $review]) }}" method="POST" style="display: none;">
            @csrf
            {{ method_field('DELETE') }}
        </form>
    </div>
@endsection
