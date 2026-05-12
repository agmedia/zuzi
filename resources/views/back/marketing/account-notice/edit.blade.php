@extends('back.layouts.backend')

@php
    $hasOldInput = old('_token') !== null;
    $active = $hasOldInput ? old('active') : $notice['active'];
    $preview = [
        'title' => old('title', $notice['title']),
        'intro' => old('intro', $notice['intro']),
        'coupon_label' => old('coupon_label', $notice['coupon_label']),
        'coupon_code' => old('coupon_code', $notice['coupon_code']),
        'discount_text' => old('discount_text', $notice['discount_text']),
        'outro' => old('outro', $notice['outro']),
        'button_text' => old('button_text', $notice['button_text']),
        'valid_until' => old('valid_until', $notice['valid_until']),
    ];

    try {
        $previewValidUntil = $preview['valid_until'] ? \Illuminate\Support\Carbon::parse($preview['valid_until'])->format('d.m.Y.') : null;
    } catch (\Throwable $e) {
        $previewValidUntil = null;
    }
@endphp

@push('css_after')
    <style>
        .account-notice-preview {
            border: 1px solid #edf0f5;
            border-radius: 6px;
            background: #fff;
            color: #1f2933;
            padding: 2rem;
            text-align: center;
        }

        .account-notice-preview__title,
        .account-notice-preview__code {
            color: #e50077;
            font-weight: 700;
        }

        .account-notice-preview__title {
            font-size: 1.9rem;
            line-height: 1.2;
        }

        .account-notice-preview__coupon {
            border: 2px dashed #e50077;
            margin: 1.75rem 0;
            padding: 1.5rem;
        }

        .account-notice-preview__code {
            font-size: 2.25rem;
            letter-spacing: 0;
        }

        .account-notice-preview__button {
            display: inline-block;
            border-radius: 6px;
            background: #e50077;
            color: #fff;
            font-weight: 700;
            margin-top: 1.25rem;
            padding: .85rem 2.5rem;
        }
    </style>
@endpush

@section('content')
    <div class="bg-body-light">
        <div class="content content-full">
            <div class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center">
                <h1 class="flex-sm-fill font-size-h2 font-w400 mt-2 mb-0 mb-sm-2">Obavijest u računu</h1>
                <nav class="flex-sm-00-auto ml-sm-3" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">Marketing</li>
                        <li class="breadcrumb-item active" aria-current="page">Obavijest u računu</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="content content-full">
        @include('back.layouts.partials.session')

        <form action="{{ route('account.notice.update') }}" method="POST">
            @csrf
            {{ method_field('PATCH') }}

            <div class="row">
                <div class="col-lg-7">
                    <div class="block">
                        <div class="block-header block-header-default">
                            <h3 class="block-title">Tekst obavijesti</h3>
                            <div class="block-options">
                                <div class="custom-control custom-switch custom-control-success block-options-item">
                                    <input type="checkbox" class="custom-control-input" id="active-switch" name="active" value="1" @if($active) checked @endif>
                                    <label class="custom-control-label pt-1" for="active-switch">Prikaži</label>
                                </div>
                            </div>
                        </div>
                        <div class="block-content">
                            <div class="form-group">
                                <label for="title-input">Naslov</label>
                                <input type="text" class="form-control @error('title') is-invalid @enderror" id="title-input" name="title" value="{{ old('title', $notice['title']) }}">
                                @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="intro-input">Uvodni tekst</label>
                                <textarea class="form-control @error('intro') is-invalid @enderror" id="intro-input" name="intro" rows="3">{{ old('intro', $notice['intro']) }}</textarea>
                                @error('intro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group row">
                                <div class="col-md-6">
                                    <label for="coupon-label-input">Labela kupona</label>
                                    <input type="text" class="form-control @error('coupon_label') is-invalid @enderror" id="coupon-label-input" name="coupon_label" value="{{ old('coupon_label', $notice['coupon_label']) }}">
                                    @error('coupon_label') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="coupon-code-input">Kupon kod</label>
                                    <input type="text" class="form-control @error('coupon_code') is-invalid @enderror" id="coupon-code-input" name="coupon_code" value="{{ old('coupon_code', $notice['coupon_code']) }}">
                                    @error('coupon_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="discount-text-input">Tekst popusta</label>
                                <input type="text" class="form-control @error('discount_text') is-invalid @enderror" id="discount-text-input" name="discount_text" value="{{ old('discount_text', $notice['discount_text']) }}">
                                @error('discount_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group">
                                <label for="outro-input">Završni tekst</label>
                                <textarea class="form-control @error('outro') is-invalid @enderror" id="outro-input" name="outro" rows="3">{{ old('outro', $notice['outro']) }}</textarea>
                                @error('outro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="form-group row">
                                <div class="col-md-6">
                                    <label for="button-text-input">Tekst gumba</label>
                                    <input type="text" class="form-control @error('button_text') is-invalid @enderror" id="button-text-input" name="button_text" value="{{ old('button_text', $notice['button_text']) }}">
                                    @error('button_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="button-url-input">Link gumba</label>
                                    <input type="text" class="form-control @error('button_url') is-invalid @enderror" id="button-url-input" name="button_url" value="{{ old('button_url', $notice['button_url']) }}">
                                    @error('button_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="valid-until-input">Kupon vrijedi do</label>
                                <input type="date" class="form-control @error('valid_until') is-invalid @enderror" id="valid-until-input" name="valid_until" value="{{ old('valid_until', $notice['valid_until']) }}">
                                @error('valid_until') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="block-content bg-body-light">
                            <button type="submit" class="btn btn-hero-success mb-3">
                                <i class="fas fa-save mr-1"></i> Snimi
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="block">
                        <div class="block-header block-header-default">
                            <h3 class="block-title">Pregled</h3>
                        </div>
                        <div class="block-content">
                            <div class="account-notice-preview mb-4">
                                <h2 class="account-notice-preview__title">{{ $preview['title'] }}</h2>
                                <p class="font-size-lg mt-3 mb-0">{{ $preview['intro'] }}</p>
                                <div class="account-notice-preview__coupon">
                                    <div class="font-size-lg">{{ $preview['coupon_label'] }}</div>
                                    <div class="account-notice-preview__code">{{ $preview['coupon_code'] }}</div>
                                    <div class="font-w700 font-size-lg">{{ $preview['discount_text'] }}</div>
                                </div>
                                <p class="font-size-lg mb-0">{{ $preview['outro'] }}</p>
                                @if($preview['button_text'])
                                    <span class="account-notice-preview__button">{{ $preview['button_text'] }}</span>
                                @endif
                                @if($previewValidUntil)
                                    <div class="text-muted mt-4">Kupon vrijedi do: <strong>{{ $previewValidUntil }}</strong></div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
