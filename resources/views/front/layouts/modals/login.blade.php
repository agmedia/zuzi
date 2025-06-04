<div class="modal fade" id="signin-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-secondary">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link fw-medium active" data-bs-target="#signin-tab"  id="pills-signin-tab" data-bs-toggle="tab" role="tab" aria-controls="signin-tab" aria-selected="true"><i class="ci-unlocked me-2 mt-n1"></i>Prijava</a></li>
                    <li class="nav-item"><a class="nav-link fw-medium" data-bs-target="#signup-tab" id="pills-signup-tab" data-bs-toggle="tab" role="tab" aria-controls="signup-tab" aria-selected="false"><i class="ci-user me-2 mt-n1"></i>Registracija</a></li>
                </ul>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body tab-content py-4"  >
                <form method="POST" class="needs-validation tab-pane fade show active" action="{{ route('login') }}" autocomplete="off" novalidate id="signin-tab" aria-controls="pills-signin">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="si-email">Email adresa</label>
                        <input class="form-control" type="email" id="si-email" name="email" placeholder="" required>
                        <div class="invalid-feedback">Molimo unesite ispravnu email adresu.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="si-password">Zaporka</label>
                        <div class="password-toggle">
                            <input class="form-control" type="password" name="password" id="si-password" required>
                            <label class="password-toggle-btn" aria-label="Show/hide password">
                                <input class="password-toggle-check" type="checkbox"><span class="password-toggle-indicator"></span>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3 d-flex flex-wrap justify-content-between">
                        <div class="form-check mb-2 ps-0">
                            <x-jet-checkbox id="remember_me" name="remember" />
                            <label class="form-check-label" for="si-remember">Zapamti me</label>
                        </div><!--<a class="fs-sm" href="#">Zaboravljena lozinka</a>-->
                    </div>
                    <button class="btn btn-primary btn-shadow d-block w-100" type="submit">Prijavi se</button>
                </form>
                <form class="needs-validation tab-pane fade" method="POST" action="{{ route('register') }}" autocomplete="off" novalidate id="signup-tab"  aria-controls="pills-signup" oninput='password_confirmation.setCustomValidity(password_confirmation.value != password.value ? "Passwords do not match." : "")'>



                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="su-name">Korisničko ime</label>
                        <input class="form-control" type="text" name="name" id="su-name" placeholder="" required>
                        <div class="invalid-feedback">Molimo unesite korisničko ime.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="si-email">Email adresa</label>
                        <input class="form-control" type="email" name="email"  id="su-email" placeholder="" required>
                        <div class="invalid-feedback">Molimo unesite ispravnu email adresu.</div>

                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="su-password">Zaporka</label>
                        <div class="password-toggle">
                            <input class="form-control" type="password" name="password" minlength="8" id="su-password" required>

                            <label class="password-toggle-btn" aria-label="Show/hide password">
                                <input class="password-toggle-check" type="checkbox"><span class="password-toggle-indicator"></span>
                            </label>
                        </div>
                        <div id="emailHelp" class="form-text">Minimalno 8 znakova</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="su-password-confirm">Potvrdite zaporku</label>
                        <div class="password-toggle">
                            <input class="form-control" type="password" name="password_confirmation"  minlength="8" id="su-password-confirm" required>
                            <label class="password-toggle-btn" aria-label="Show/hide password">
                                <input class="password-toggle-check" type="checkbox"><span class="password-toggle-indicator"></span>
                            </label>
                        </div>
                    </div>

                    <div class="form-check form-check-inline">
                        <label class="form-check-label" for="ex-check-4">{!! __('Slažem se sa :terms_of_service', [
                                                'terms_of_service' => '<a target="_blank" href="'.route('catalog.route.page',['page' => 'opci-uvjeti-kupnje']).'" class="link-fx">'.__('Uvjetima kupovine').'</a>',
                                                'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="link-fx">'.__('Privacy Policy').'</a>',
                                        ]) !!}</label>
                        <input class="form-check-input" type="checkbox" name="terms" id="terms" required>
                        <div class="invalid-feedback" id="terms">Morate se složiti sa Uvjetima kupnje.</div>
                    </div>


                   {{-- @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                        <div class="form-group mb-3" >
                            <x-jet-label for="terms">
                                <div class="flex items-center">
                                    <x-jet-checkbox name="terms" id="terms"/>
                                    <label class="form-label">
                                        {!! __('Slažem se sa :terms_of_service', [
                                                'terms_of_service' => '<a target="_blank" href="'.route('catalog.route.page',['page' => 'opci-uvjeti-kupnje']).'" class="link-fx">'.__('Uvjetima kupovine').'</a>',
                                                'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="link-fx">'.__('Privacy Policy').'</a>',
                                        ]) !!}
                                    </label>
                                    <div class="invalid-feedback" id="terms">Morate se složiti sa Uvjetima kupnje.</div>
                                </div>
                            </x-jet-label>
                        </div>
                    @endif--}}



                    <button class="btn btn-primary btn-shadow d-block w-100" type="submit">Registriraj se</button>

                    <input type="hidden" name="recaptcha" id="recaptcha">
                    <div class="mt-2 d-block"><small>Ova je stranica zaštićena reCAPTCHA-om i primjenjuju se Googleova
                            <a href="https://policies.google.com/privacy">Pravila o privatnosti</a> i
                            <a href="https://policies.google.com/terms">Uvjeti pružanja usluge</a>.
                        </small>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>


