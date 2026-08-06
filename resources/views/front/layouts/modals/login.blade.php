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
                    @if (session('auth_status'))
                        <div class="alert alert-success py-2 px-3 mb-3" role="alert">
                            {{ session('auth_status') }}
                        </div>
                    @endif
                    @if (session('auth_error'))
                        <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
                            {{ session('auth_error') }}
                        </div>
                    @endif
                    @if ($googleLoginEnabled ?? false)
                        <a class="google-login-button" href="{{ route('google.login.redirect', ['redirect' => request()->fullUrl()]) }}">
                            <svg aria-hidden="true" viewBox="0 0 18 18" focusable="false">
                                <path fill="#4285f4" d="M17.64 9.205c0-.638-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 0 1-1.797 2.716v2.259h2.909c1.702-1.567 2.684-3.875 2.684-6.615z"/>
                                <path fill="#34a853" d="M9 18c2.43 0 4.468-.806 5.956-2.18l-2.909-2.259c-.806.54-1.835.859-3.047.859-2.344 0-4.328-1.585-5.037-3.714H.956v2.333A9 9 0 0 0 9 18z"/>
                                <path fill="#fbbc05" d="M3.963 10.706A5.41 5.41 0 0 1 3.682 9c0-.592.102-1.168.281-1.706V4.961H.956A9 9 0 0 0 0 9c0 1.452.347 2.827.956 4.039l3.007-2.333z"/>
                                <path fill="#ea4335" d="M9 3.58c1.321 0 2.507.454 3.44 1.346l2.581-2.581C13.464.892 11.426 0 9 0A9 9 0 0 0 .956 4.961l3.007 2.333C4.672 5.165 6.656 3.58 9 3.58z"/>
                            </svg>
                            <span>Nastavi s Google računom</span>
                        </a>
                        <div class="google-login-divider" aria-hidden="true"><span>ili</span></div>
                    @endif
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
                            <x-jet-checkbox id="si-remember" name="remember" />
                            <label class="form-check-label" for="si-remember">Zapamti me</label>
                        </div>
                        <a class="fs-sm" href="{{ route('forget.password.get') }}">Zaboravljena lozinka?</a>
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
