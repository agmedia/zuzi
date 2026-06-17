@extends('front.layouts.app')

@section('title', \App\Models\Seo::appendBrand('Pogodi rezultat Hrvatska – Engleska'))
@section('description', 'Pošalji prognozu rezultata utakmice Hrvatska – Engleska i sudjeluj u Zuzi promotivnom natjecanju.')

@section('content')
    @include('partials.match-prediction-widget')
@endsection
