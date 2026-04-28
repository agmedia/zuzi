<section id="footer-newsletter" class="footer-newsletter">
    <div class=" py-5 border-0 ">
        <div class=" py-md-4 py-3 px-4 text-center">
            <h3 class="mb-3">Prijavi se na newsletter</h3>
            <p class="mb-4 pb-2">Novi naslovi, posebni primjerci i tihe preporuke iz antikvarijata.</p>

            <div class="widget mx-auto" style="max-width: 600px;">
                @if (session('newsletter_success'))
                    <div class="alert alert-success mb-3 text-start" role="alert">
                        {{ session('newsletter_success') }}
                    </div>
                @endif

                @if ($errors->has('newsletter_form'))
                    <div class="alert alert-danger mb-3 text-start" role="alert">
                        {{ $errors->first('newsletter_form') }}
                    </div>
                @endif

                <form class="newsletter-signup-form needs-validation" action="{{ route('newsletter.subscribe') }}" method="post" novalidate>
                    @csrf

                    <div class="input-group has-validation position-relative">
                        <i class="ci-mail position-absolute text-muted fs-base ms-3" style="top: 1.5rem; transform: translateY(-50%); z-index: 5;"></i>
                        <input
                            class="form-control rounded-start ps-5 @error('newsletter_email') is-invalid @enderror"
                            id="footer-newsletter-email"
                            type="email"
                            value="{{ old('newsletter_email') }}"
                            name="newsletter_email"
                            placeholder="Upišite svoju e-mail adresu"
                            autocomplete="email"
                            required
                        >
                        <button class="btn btn-primary" type="submit">Prijavi se</button>

                        <div class="invalid-feedback w-100 text-start">
                            {{ $errors->first('newsletter_email') ?: 'Upišite ispravnu email adresu.' }}
                        </div>
                    </div>

                    <div class="form-check text-start mt-3">
                        <input
                            class="form-check-input @error('newsletter_consent') is-invalid @enderror"
                            id="footer-newsletter-consent"
                            type="checkbox"
                            name="newsletter_consent"
                            value="1"
                            {{ old('newsletter_consent') ? 'checked' : '' }}
                            required
                        >
                        <label class="form-check-label" for="footer-newsletter-consent">
                            Dajem privolu za primanje newslettera i obradu podataka u skladu s GDPR-om.
                        </label>

                        <div class="invalid-feedback">
                            {{ $errors->first('newsletter_consent') ?: 'Potrebna je privola za prijavu na newsletter.' }}
                        </div>
                    </div>

                    <div class="form-text mt-3">
                        Mailchimp prijava je povezana s ovom formom i podaci se koriste samo za slanje newslettera.
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
<hr class="hr-dark mb-2">
