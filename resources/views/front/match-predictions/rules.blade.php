@extends('front.layouts.app')

@section('title', \App\Models\Seo::appendBrand('Pravila promotivnog natjecanja'))
@section('description', 'Pravila promotivnog natjecanja Pogodi rezultat Hrvatska – Gana na Zuzi.hr.')

@section('content')
    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb flex-lg-nowrap">
            <li class="breadcrumb-item"><a class="text-nowrap" href="{{ route('index') }}"><i class="ci-home"></i>Naslovnica</a></li>
            <li class="breadcrumb-item text-nowrap active" aria-current="page">Pravila promotivnog natjecanja</li>
        </ol>
    </nav>

    <section class="mb-4 pb-2">
        <h1 class="h2 mb-3">Promotivno natjecanje &ldquo;Pogodi rezultat Hrvatska – Gana&rdquo;</h1>
        <p class="text-muted mb-0">
            Trajanje: do {{ $deadline->format('d.m.Y. H:i') }} prema vremenskoj zoni {{ config('match_prediction.timezone') }}.
        </p>
    </section>

    <section class="fs-md mb-5" style="max-width: 960px;">
        <h2 class="h4">Organizator</h2>
        <p>
            Organizator promotivnog natjecanja je ZUZI, obrt za uslužne djelatnosti, VL. MIRJANA VULIĆ ŠALDIĆ.
        </p>

        <h2 class="h4 mt-4">Sudjelovanje</h2>
        <p>
            Sudjelovanje je besplatno. Kupnja nije uvjet sudjelovanja. Sudjeluje se putem forme na Zuzi.hr, a svaka osoba može poslati jednu valjanu prijavu po email adresi.
        </p>

        <h2 class="h4 mt-4">Nagrada</h2>
        <p>
            Nagrada je {{ config('match_prediction.prize_name') }}, koji dobitnik može iskoristiti za knjigu ili knjige po želji u toj vrijednosti. Nagrada se ne može zamijeniti za novac.
        </p>

        <h2 class="h4 mt-4">Odabir dobitnika</h2>
        <p>
            Dobitnik se ne određuje slučajnim izvlačenjem. Pobjednik se određuje prema unaprijed definiranim kriterijima:
        </p>
        <ol>
            <li>točan rezultat utakmice</li>
            <li>najbliža prognoza minute prvog gola</li>
            <li>najbliža prognoza ukupnog broja žutih kartona</li>
            <li>ranije zaprimljena valjana prijava</li>
        </ol>
        <p>
            Dobitnik će biti kontaktiran emailom nakon provjere zaprimljenih prijava i službenih podataka utakmice.
        </p>

        <h2 class="h4 mt-4">Meta/Facebook</h2>
        <p>
            Meta/Facebook nije organizator, sponzor ni administrator ovog promotivnog natjecanja.
        </p>

        <h2 class="h4 mt-4">Osobni podaci</h2>
        <p>
            Osobni podaci obrađuju se samo za provedbu promotivnog natjecanja, kontaktiranje dobitnika i evidenciju prijava. Newsletter privola je odvojena i opcionalna.
        </p>
    </section>
@endsection
