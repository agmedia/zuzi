<button class="interliber-promo-trigger" type="button" data-bs-toggle="modal" data-bs-target="#interliber-promo-modal" aria-haspopup="dialog" aria-controls="interliber-promo-modal">
    <span class="interliber-promo-trigger__icon" aria-hidden="true">%</span>
    <span>Ugrabi 20 %</span>
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg>
</button>

<div class="modal fade interliber-promo-modal" id="interliber-promo-modal" tabindex="-1" aria-labelledby="interliber-modal-title" aria-describedby="interliber-modal-description" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <button class="interliber-promo-modal__close" type="button" data-bs-dismiss="modal" aria-label="Zatvori ponudu">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg>
            </button>
            <div class="modal-body">
                <h2 class="visually-hidden" id="interliber-modal-title">Stvaramo gužvu na tvojoj polici.</h2>
                <p class="visually-hidden" id="interliber-modal-description">Povodom Interlibera ostvari 20 % popusta na sve uz kod ZUZI20.</p>
                @include('front.layouts.partials.interliber-promo-artwork', ['artworkId' => 'interliber-modal-art'])
                <div class="interliber-promo-modal__actions">
                    <button class="interliber-promo-modal__copy" type="button" data-interliber-copy-code="ZUZI20">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h4"/></svg>
                        <span data-interliber-copy-label>Kopiraj kod</span>
                    </button>
                    <a class="interliber-promo-modal__shop" href="{{ url('kategorija-proizvoda') }}">Pronađi svoj ulov <span aria-hidden="true">→</span></a>
                    <p class="interliber-promo-modal__hint" id="interliber-copy-status" role="status" aria-live="polite">Kod upiši u košarici.</p>
                </div>
            </div>
        </div>
    </div>
</div>
