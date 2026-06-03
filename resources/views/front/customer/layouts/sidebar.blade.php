@php
    $accountDisplayName = $user->details->fname ? trim($user->details->fname . ' ' . $user->details->lname) : $user->name;
    $accountInitials = collect(explode(' ', $accountDisplayName))
        ->filter()
        ->take(2)
        ->map(function ($part) {
            return \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1));
        })
        ->implode('');
@endphp

<aside class="col-lg-4 col-xl-3 account-sidebar-column pt-4 pt-lg-0">
    <div class="account-sidebar-card mb-5 mb-lg-0">
        <div class="account-user-panel">
            <span class="account-avatar">{{ $accountInitials ?: 'ZR' }}</span>
            <div class="min-w-0">
                <h3 class="account-user-name mb-1">{{ $accountDisplayName }}</h3>
                <span class="account-user-email">{{ $user->email }}</span>
            </div>
            <a class="btn btn-primary d-lg-none account-mobile-nav mt-2 mt-md-0 ms-md-auto" href="#account-menu" data-bs-toggle="collapse" aria-expanded="false">
                <i class="ci-menu me-2"></i>Navigacija
            </a>
        </div>
        <div class="d-lg-block collapse" id="account-menu">
            <div class="account-sidebar-kicker">
                Moj korisnički račun
            </div>
            <ul class="account-nav-list list-unstyled mb-0 fs-sm">
                <li class="account-nav-item">
                    <a class="account-nav-link nav-link-style d-flex align-items-center px-3 py-2 {{ request()->routeIs('moj-racun') ? 'active' : '' }}" href="{{ route('moj-racun') }}">
                        <i class="ci-announcement"></i>Obavijesti
                    </a>
                </li>

                <li class="account-nav-item">
                    <a class="account-nav-link nav-link-style d-flex align-items-center px-3 py-2 {{ request()->routeIs('moj-racun.podaci') ? 'active' : '' }}" href="{{ route('moj-racun.podaci') }}">
                        <i class="ci-user"></i>Moji podaci
                    </a>
                </li>

                <li class="account-nav-item">
                    <a class="account-nav-link nav-link-style d-flex align-items-center px-3 py-2 {{ request()->routeIs('moje-narudzbe*') ? 'active' : '' }}" href="{{ route('moje-narudzbe') }}">
                        <i class="ci-bag"></i>Narudžbe
                    </a>
                </li>

                <li class="account-nav-item">
                    <a class="account-nav-link nav-link-style d-flex align-items-center px-3 py-2 {{ request()->routeIs('moji-dojmovi') ? 'active' : '' }}" href="{{ route('moji-dojmovi') }}">
                        <i class="ci-star"></i>Moji dojmovi
                    </a>
                </li>

                <li class="account-nav-item">
                    <a class="account-nav-link nav-link-style d-flex align-items-center px-3 py-2 {{ request()->routeIs('loyalty') ? 'active' : '' }}" href="{{ route('loyalty') }}">
                        <i class="ci-coins"></i>Loyalty
                    </a>
                </li>

                <li class="account-nav-item">
                    <a class="account-nav-link nav-link-style d-flex align-items-center px-3 py-2" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="ci-sign-out"></i>Odjava
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </li>
            </ul>

            @if (request()->routeIs(['loyalty']))
                <div class="account-referral-panel">
                    <small class="d-block">Preporuči Loyalty Klub prijatelju i oboje dobivate 50 bodova kada prijatelj napravi prvu kupnju.</small>
                    <button class="btn btn-primary btn-sm mt-3" onclick="copyToClipboard('{{ route('index', [config('settings.loyalty.link_tag') => auth()->user()->getAffiliateLink()]) }}')">
                        <i class="ci-star-filled pb-1 me-1"></i>Preporuči Loyalty Klub
                    </button>
                </div>
            @endif
        </div>
    </div>
</aside>


@push('js_after')
    <script>
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text);

            } else {
                // Use the 'out of viewport hidden text area' trick
                const textArea = document.createElement("textarea");
                textArea.value = text;

                // Move textarea out of the viewport so it's not visible
                textArea.style.position = "absolute";
                textArea.style.left = "-999999px";

                document.body.prepend(textArea);
                textArea.select();

                try {
                    document.execCommand('copy');
                } catch (error) {
                    console.error(error);
                } finally {
                    textArea.remove();
                }
            }

            alert("Pošaljite prijatelju ovaj link koji ste upravo kopirali: " + text);
        }
    </script>
@endpush
