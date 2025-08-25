<aside class="col-lg-4 pt-4 pt-lg-0 pe-xl-5">
    <div class="bg-white rounded-3 shadow-lg pt-1 mb-5 mb-lg-0">
        <div class="d-md-flex justify-content-between align-items-center text-center text-md-start p-4">
            <div class="d-md-flex align-items-center">
                <div class="ps-md-0">
                    <h3 class="fs-base mb-0">{{ $user->details->fname ? $user->details->fname . ' ' . $user->details->lname: $user->name }}</h3><span class="text-accent fs-sm">{{ $user->email }}</span>
                </div>
            </div><a class="btn btn-primary d-lg-none mb-2 mt-3 mt-md-0" href="#account-menu" data-bs-toggle="collapse" aria-expanded="false"><i class="ci-menu me-2"></i>Navigacija</a>
        </div>
        <div class="d-lg-block collapse" id="account-menu">
            <div class="bg-secondary px-4 py-3">
                <h3 class="fs-sm mb-0 text-muted">Moj korisnički račun</h3>
            </div>
            <ul class="list-unstyled mb-0 fs-sm">
                <li class="border-bottom mb-0">
                    <a class="nav-link-style d-flex align-items-center px-4 py-3 {{ request()->routeIs('moj-racun') ? 'active' : '' }}" href="{{ route('moj-racun') }}">
                        <i class="ci-user opacity-60 me-2"></i>Moji podaci
                    </a>
                </li>

                <li class="border-bottom mb-0">
                    <a class="nav-link-style d-flex align-items-center px-4 py-3 {{ request()->routeIs('moje-narudzbe') ? 'active' : '' }}" href="{{ route('moje-narudzbe') }}">
                        <i class="ci-bag opacity-60 me-2"></i>Narudžbe
                    </a>
                </li>

                <li class="border-bottom mb-0">
                    <a class="nav-link-style d-flex align-items-center px-4 py-3 {{ request()->routeIs('loyalty') ? 'active' : '' }}" href="{{ route('loyalty') }}">
                        <i class="ci-coins opacity-60 me-2"></i>Loyalty
                    </a>
                </li>

                <li class="mb-0">
                    <a class="nav-link-style d-flex align-items-center px-4 py-3" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="ci-sign-out opacity-60 me-2"></i>Odjava
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </li>
            </ul>
        </div>
    </div>

    @if (request()->routeIs(['loyalty']))
        <div class="rounded-3 pt-1 my-4 mb-lg-0">
            <div class="alert alert-info d-flex" role="alert">
                <div class="alert-icon">
                    <i class="ci-announcement"></i>
                </div>
                <div>
                    <small>Preporuči Loyalty Klub prijatelju i oboje dobivate 50 bodova kada prijatelj napravi prvu kupnju.</small>
                    <button class="btn btn-primary mb-2 mt-3" onclick="copyToClipboard('{{ route('index', [config('settings.loyalty.link_tag') => auth()->user()->getAffiliateLink()]) }}')">
                        <i class="ci-star-filled pb-2"></i> Preporuči Loyalty Klub
                    </button>
                </div>
            </div>
        </div>
    @endif
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