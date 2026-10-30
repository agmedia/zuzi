@extends('front.layouts.app')

@php
    $pageTitle = $offerActive
        ? 'Interliber popusti online: 20 % na sve uz ZUZI20 | ZUZI Shop'
        : 'Knjige online i najprodavaniji romani | ZUZI Shop';
    $pageDescription = $offerActive
        ? 'Povodom Interlibera ostvari 20 % popusta na sve na ZUZI.hr uz kod ZUZI20. Pronađi najprodavanije knjige, romane i knjige za poklon.'
        : 'Pronađi svoju sljedeću knjigu na ZUZI.hr. Istraži najprodavanije naslove, beletristiku, romane i ideje za knjiške poklone.';
    $faqs = $offerActive ? [
        ['question' => 'Kako ostvariti Interliber popust na ZUZI.hr?', 'answer' => 'Odaberi artikle, dodaj ih u košaricu i u polje za kupon upiši ZUZI20. Nakon primjene koda provjeri obračunati popust prije potvrde narudžbe.'],
        ['question' => 'Vrijedi li 20 % popusta samo za beletristiku?', 'answer' => 'Ne. Kod ZUZI20 donosi 20 % popusta na sve iz ZUZI ponude. Romani i bestseleri na ovoj stranici služe kao inspiracija za odabir.'],
        ['question' => 'Mogu li knjige naručiti online?', 'answer' => 'Da. Ponudu možeš pregledati i narudžbu dovršiti na ZUZI.hr. Za korištenje koda nije potreban dolazak na sajam.'],
        ['question' => 'Gdje mogu provjeriti dostupnost i dostavu?', 'answer' => 'Dostupnost i informacije o isporuci provjeri uz odabrani artikl. Trošak i raspoložive mogućnosti dostave prikazuju se tijekom naručivanja.'],
    ] : [
        ['question' => 'Gdje mogu pronaći aktualne popuste na knjige?', 'answer' => 'Aktualnu ponudu provjeri u katalogu i rubrici akcijske ponude na ZUZI.hr. Ova stranica trenutačno ne oglašava aktivan Interliber kupon.'],
        ['question' => 'Kako pronaći roman ili knjigu za poklon?', 'answer' => 'Započni s najprodavanijim knjigama, pregledaj beletristiku ili otvori vodič s idejama za knjiške poklone.'],
    ];
    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebPage',
                '@id' => route('interliber.popusti') . '#webpage',
                'url' => route('interliber.popusti'),
                'name' => $pageTitle,
                'description' => $pageDescription,
                'inLanguage' => 'hr-HR',
                'isPartOf' => ['@type' => 'WebSite', 'name' => 'ZUZI Shop', 'url' => route('index')],
            ],
            [
                '@type' => 'FAQPage',
                '@id' => route('interliber.popusti') . '#pitanja',
                'mainEntity' => array_map(static fn ($faq) => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                ], $faqs),
            ],
        ],
    ];
@endphp

@section('title', $pageTitle)
@section('description', $pageDescription)
@section('canonical', route('interliber.popusti'))
@section('seo_image', 'media/img/campaigns/interliber-2026/books.webp')
@section('seo_image_alt', 'Izbor romana i bestselera iz ZUZI ponude')

@push('css_after')
    <link rel="stylesheet" href="{{ asset('css/interliber-popusti.css') }}?v=1">
@endpush

@push('meta_tags')
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
    <article class="interliber-page">
        <nav class="interliber-page__breadcrumb" aria-label="Putanja">
            <a href="{{ route('index') }}">Naslovnica</a><span aria-hidden="true">/</span>
            <span aria-current="page">{{ $offerActive ? 'Interliber popusti' : 'Pronađi svoju knjigu' }}</span>
        </nav>

        <section class="interliber-page__hero" aria-labelledby="interliber-page-title">
            <div class="interliber-page__intro">
                <p class="interliber-page__eyebrow">{{ $offerActive ? 'Povodom Interlibera' : 'ZUZI knjiški izbor' }}</p>
                @if($offerActive)
                    <h1 id="interliber-page-title">Interliber popusti online.<span>Gužva na tvojoj polici.</span></h1>
                    <p class="interliber-page__lead">Romani koje ne ispuštaš iz ruku, omiljeni autori i dugo traženi naslovi. Ostvari <strong>20 % popusta na sve</strong> uz kod <strong>ZUZI20</strong>.</p>
                    <div class="interliber-page__offer">
                        <div><strong class="interliber-page__percent">−20 %</strong><span>na sve</span></div>
                        <div class="interliber-page__code"><span>Kod za košaricu</span><strong id="interliber-coupon">ZUZI20</strong><button type="button" id="interliber-copy" hidden>Kopiraj kod</button></div>
                    </div>
                    <p id="interliber-copy-status" class="interliber-page__copy-status" role="status" aria-live="polite"></p>
                @else
                    <h1 id="interliber-page-title">Tvoja sljedeća knjiga.<span>Pronađi joj mjesto.</span></h1>
                    <p class="interliber-page__lead">Interliber kupon trenutačno nije dostupan. Pronađi svoj novi roman, istraži bestselere i provjeri aktualnu ZUZI ponudu.</p>
                @endif
                <a class="interliber-page__button" href="{{ url('izdvojeno/najprodavanije-ovaj-mjesec') }}">Istraži najprodavanije knjige <span aria-hidden="true">→</span></a>
            </div>
            <div class="interliber-page__art">
                <img src="{{ asset('media/img/campaigns/interliber-2026/books.webp') }}" width="1684" height="934" alt="Izbor romana i bestselera iz ZUZI ponude" fetchpriority="high" decoding="async">
                <p>Još samo jedna knjiga? Znamo kako to ide.</p>
            </div>
        </section>

        <section class="interliber-page__choose" aria-labelledby="interliber-choose-title">
            <p class="interliber-page__eyebrow">Tvoj sljedeći knjiški ulov</p>
            <h2 id="interliber-choose-title">Kamo te vodi sljedeća priča?</h2>
            <div class="interliber-page__links">
                <a href="{{ url('izdvojeno/najprodavanije-ovaj-mjesec') }}"><span class="interliber-page__number" aria-hidden="true">01</span><h3>Najprodavanije knjige</h3><p>Naslovi koje čitatelji već biraju.</p><span aria-hidden="true">Pogledaj izbor →</span></a>
                <a href="{{ url('kategorija-proizvoda/beletristika') }}"><span class="interliber-page__number" aria-hidden="true">02</span><h3>Beletristika i romani</h3><p>Odaberi priču kojoj ćeš se vraćati.</p><span aria-hidden="true">Pronađi svoj roman →</span></a>
                <a href="{{ url('savjeti-za-poklone') }}"><span class="interliber-page__number" aria-hidden="true">03</span><h3>Knjige za poklon</h3><p>Ideje za ljude koji vole dobre priče.</p><span aria-hidden="true">Potraži inspiraciju →</span></a>
                <a href="{{ url('kategorija-proizvoda') }}"><span class="interliber-page__number" aria-hidden="true">04</span><h3>Cijela ZUZI ponuda</h3><p>Istraži naslove za sebe i svoje najdraže.</p><span aria-hidden="true">Otvori katalog →</span></a>
            </div>
        </section>

        @if($offerActive)
            <section class="interliber-page__steps" aria-labelledby="interliber-steps-title">
                <div><p class="interliber-page__eyebrow">Jedan kod. Puno novih priča.</p><h2 id="interliber-steps-title">Kako do 20 % popusta?</h2></div>
                <ol><li>Odaberi svoje favorite i dodaj ih u košaricu.</li><li>U polje za kupon upiši <strong>ZUZI20</strong> i primijeni kod.</li><li>Provjeri popust u izračunu i dovrši narudžbu.</li></ol>
            </section>
        @else
            <p class="interliber-page__current"><a href="{{ url('akcijska-ponuda') }}">Pogledaj aktualnu akcijsku ponudu →</a></p>
        @endif

        <section class="interliber-page__faq" id="pitanja" aria-labelledby="interliber-faq-title">
            <h2 id="interliber-faq-title">Dobro je znati</h2>
            @foreach($faqs as $faq)
                <details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>
            @endforeach
        </section>
    </article>
@endsection

@if($offerActive)
    @push('js_after')
        <script>
            (function () {
                var button = document.getElementById('interliber-copy');
                var status = document.getElementById('interliber-copy-status');
                if (!button || !navigator.clipboard || !window.isSecureContext) return;
                button.hidden = false;
                button.addEventListener('click', function () {
                    navigator.clipboard.writeText('ZUZI20').then(function () {
                        status.textContent = 'Kod ZUZI20 je kopiran. Zalijepi ga u košarici.';
                    }).catch(function () {
                        status.textContent = 'U košaricu upiši kod ZUZI20.';
                    });
                });
            }());
        </script>
    @endpush
@endif
