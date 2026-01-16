

<div class="modal fade" id="wishlist-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-secondary">
                <span class="fs-lg fw-bolder">Obavijesti me o dostupnosti</span>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body tab-content py-4">
                <form method="POST" class="needs-validation" action="{{ route('wishlist') }}" autocomplete="on" novalidate id="wishlist-tab">
                    @csrf
                    <div class="mb-4 text-center">
                        <p class="fs-md fw-light">Molimo vas da upišete vašu Email adresu na koju želite da vas kontaktiramo kada artikl bude dostupan.</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="wishlist-email">Email adresa</label>
                        <input class="form-control" type="email" id="wishlist-email" name="email" placeholder="" required>
                        <div class="invalid-feedback">Molimo unesite ispravnu email adresu.</div>
                    </div>

                    <input type="hidden" name="recaptcha" id="recaptcha_wishlist">
                    <input type="hidden" name="product_id" value="{{ $prod->id }}">
                    <button class="btn btn-primary btn-shadow d-block w-100" type="submit">Obavjesti me</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('wishlist-modal');
        modalEl.addEventListener('shown.bs.modal', () => {
            grecaptcha.ready(() => {
                grecaptcha.execute('{{ config('services.recaptcha.sitekey') }}', {action: 'wishlist'})
                    .then(token => {
                        const el = document.getElementById('recaptcha_wishlist');
                        if (el) el.value = token;
                    });
            });
        });
    });
</script>
