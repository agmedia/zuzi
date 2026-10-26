{{-- Temporary homepage campaign, through 1 December 2026 inclusive. --}}
<section class="interliber-hero" aria-labelledby="interliber-hero-title">
    <a class="interliber-hero__media" href="{{ url('kategorija-proizvoda') }}" aria-label="Pogledaj sve knjige uz 20 % popusta s kodom ZUZI20">
        <img class="interliber-hero__books" src="{{ asset('media/img/campaigns/interliber-2026/books.webp') }}" width="1684" height="934" alt="" fetchpriority="high" decoding="async">
        @include('front.layouts.partials.interliber-promo-artwork', ['artworkId' => 'interliber-hero-art'])
    </a>
    <div class="interliber-hero__copy">
        <p class="interliber-hero__eyebrow">Povodom Interlibera</p>
        <h1 class="interliber-hero__title" id="interliber-hero-title">Stvaramo gužvu.<span>Na tvojoj polici.</span></h1>
        <div class="interliber-hero__offer">
            <div class="interliber-hero__discount"><strong>−20 %</strong><span>na sve</span></div>
            <div class="interliber-hero__coupon"><span>Uz kod</span><strong>ZUZI20</strong></div>
        </div>
        <div class="interliber-hero__actions">
            <a class="interliber-hero__cta" href="{{ url('kategorija-proizvoda') }}">Pronađi svoj ulov <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a>
            <span class="interliber-hero__hint">Kod upiši u košarici.</span>
        </div>
    </div>
</section>
