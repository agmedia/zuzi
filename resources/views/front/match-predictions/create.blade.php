@extends('front.layouts.app')

@section('title', \App\Models\Seo::appendBrand('Pogodi rezultat Hrvatska – Gana'))
@section('description', 'Pošalji prognozu rezultata utakmice Hrvatska – Gana i sudjeluj u Zuzi promotivnom natjecanju.')

@section('content')
    @include('partials.match-prediction-widget')
@endsection

@push('js_after')
    <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.sitekey') }}"></script>
    <script>
        grecaptcha.ready(function() {
            grecaptcha.execute('{{ config('services.recaptcha.sitekey') }}', {action: 'match_prediction'}).then(function(token) {
                if (token) {
                    document.getElementById('match-prediction-recaptcha').value = token;
                }
            });
        });
    </script>
@endpush
