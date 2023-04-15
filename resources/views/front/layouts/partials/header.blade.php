<header class="bg-dark shadow-sm navbar-sticky">
    <div class="navbar navbar-expand-lg navbar-dark">
        <div class="container"><a class="navbar-brand d-none d-sm-block flex-shrink-0 me-4 order-lg-1 p-0" href="{{ route('index') }}"><img src="{{ asset('media/img/logobijeli.svg') }}" width="180" height="76" alt="Antikvarijat Biblos"></a><a class="navbar-brand d-sm-none me-0 order-lg-1 p-0" href="{{ route('index') }}"><img src="{{ asset('media/img/logobijeli.svg') }}" width="140" alt="Antikvarijat Biblos"></a>
            <!-- Toolbar-->
            <div class="navbar-toolbar d-flex align-items-center order-lg-3">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse"><span class="navbar-toggler-icon"></span></button>
                <a class="navbar-tool d-none d-lg-flex" href="javascript:void(0)" data-bs-toggle="collapse" data-bs-target="#searchBox" role="button" aria-expanded="false" aria-controls="searchBox"><span class="navbar-tool-tooltip">Pretraži</span>
                    <div class="navbar-tool-icon-box"><i class="navbar-tool-icon ci-search"></i></div>
                </a>
                <a class="navbar-tool ms-12" href="{{ route('login') }}" ><span class="navbar-tool-tooltip">Korisnički račun</span>
                    <div class="navbar-tool-icon-box"><i class="navbar-tool-icon ci-user"></i></div>
                </a>
                <div style="width:46px">
                    <cart-nav-icon carturl="{{ route('kosarica') }}" checkouturl="{{ route('naplata') }}"></cart-nav-icon>
                </div>
            </div>

            <div class="collapse navbar-collapse me-auto mx-auto order-lg-2" id="navbarCollapse">
                <form action="{{ route('pretrazi') }}" id="search-form-mobile" method="get">
                    <div class="input-group d-lg-none my-3"><i class="ci-search position-absolute top-50 start-0 translate-middle-y text-muted fs-base ms-3"></i>
                        <input class="form-control rounded-start" type="text" name="{{ config('settings.search_keyword') }}" value="{{ request()->query('pojam') ?: '' }}" placeholder="Pretražite po nazivu ili autoru">
                        <button type="submit" class="btn btn-primary btn-lg fs-base"><i class="ci-search"></i></button>
                    </div>
                </form>

                <ul class="navbar-nav pe-lg-2 me-lg-2">
                    <li class="nav-item  d-none d-lg-block"><a class="nav-link" href="{{ route('catalog.route', ['group' => 'knjige']) }}"><i class="ci-book d-none d-xl-inline-block align-middle mt-n1 me-1"></i>Knjige</a></li>
                    <li class="nav-item d-inloine-block d-lg-none"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="ci-book d-none d-xl-inline-block align-middle mt-n1 me-1"></i>Knjige</a>
                        <ul class="dropdown-menu">
                            <li class="dropdown"><a class="nav-link dropdown-item " href="{{ route('catalog.route', ['group' => 'knjige']) }}">Sve knjige</a></li>
                            @foreach($knjige as $navitem)
                            <li class="dropdown"><a class="nav-link dropdown-item " href="{{ url(\Illuminate\Support\Str::slug($navitem->group) . '/' . $navitem->slug) }}">{{ $navitem->title}}</a></li>
                            @endforeach
                        </ul>
                    </li>
                    <li class="nav-item d-none d-lg-block"><a class="nav-link" href="{{ route('catalog.route', ['group' => 'zemljovidi-i-vedute']) }}"><i class="ci-map d-none d-xl-inline-block align-middle mt-n1 me-1"></i>Zemljovidi i vedute</a></li>
                    <li class="nav-item d-inloine-block d-lg-none"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="ci-book d-none d-xl-inline-block align-middle mt-n1 me-1"></i>Zemljovidi i vedute</a>
                        <ul class="dropdown-menu">
                            <li class="dropdown"><a class="nav-link dropdown-item " href="{{ route('catalog.route', ['group' => 'zemljovidi-i-vedute']) }}">Svi zemljovidi i vedute</a></li>
                            @foreach($zemljovidi_vedute as $nav_item)
                                <li class="dropdown"><a class="nav-link dropdown-item " href="{{ url(\Illuminate\Support\Str::slug($nav_item->group) . '/' . $nav_item->slug) }}">{{ $nav_item->title}}</a></li>
                            @endforeach
                        </ul>
                    </li>

                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.author') }}"><i class="ci-user-circle d-none d-xl-inline-block align-middle mt-n1 me-1"></i>Autori</a>
                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.publisher') }}"><i class="ci-bookmark d-none d-xl-inline-block align-middle mt-n1 me-1"></i>Nakladnici</a>

{{--                    <li class="nav-item"><a class="nav-link" href="{{ route('catalog.route.actions') }}"><i class="ci-discount d-none d-xl-inline-block align-middle mt-n1 me-1"></i>Sniženje</a></li>--}}

                    <li class="nav-item d-inloine-block d-lg-none"><a class="nav-link" href="{{ route('catalog.route.page',['page' => 'o-nama']) }}">O nama</a></li>
                    <li class="nav-item d-inloine-block d-lg-none"><a class="nav-link" href="{{ route('catalog.route.blog') }}">Iz medija</a></li>
                    <li class="nav-item d-inloine-block d-lg-none"><a class="nav-link" href="{{ route('faq') }}">Česta pitanja</a></li>
                    <li class="nav-item d-inloine-block d-lg-none"><a class="nav-link" href="{{ route('kontakt') }}">Kontakt</a></li>
                </ul>

                <div class="offcanvas-cap d-block d-lg-none px-grid-gutter pt-4 pb-3 mb-2">
                    <div class="d-flex mb-3"><i class="ci-phone h4 mb-0 fw-normal text-primary mt-1 me-1"></i>
                        <div class="ps-2">
                            <div class="text-white fs-sm">Telefon</div><a class="nav-link-style text-white fs-md" href="tel:+385148165740">+385 1 48 16 574</a>
                        </div>
                    </div>
                    <div class="d-flex mb-3"><i class="ci-mail h5 mb-0 fw-normal text-primary mt-1 me-1"></i>
                        <div class="ps-2">
                            <div class="text-white fs-sm">Email</div><a class="nav-link-style text-white fs-md" href="mailto:info@antikvarijat-biblos.hr">info@antikvarijat-biblos.hr</a>
                        </div>
                    </div>
                    <h6 class="pt-2 pb-1 text-white">Pratite nas</h6>
                    <a class="btn-social bs-light bs-instagram me-2 mb-2" href="https://www.instagram.com/antikvarijat_biblos/"><i class="ci-instagram"></i></a>
                    <a class="btn-social bs-light bs-facebook me-2 mb-2" href="https://www.facebook.com/AntikvarijatBiblos/"><i class="ci-facebook"></i></a>
                </div>

            </div>
        </div>
    </div>
    <!-- Search collapse-->
    <div class="search-box collapse" id="searchBox">
        <div class="card bg-accent pt-3 pb-3 border-0 rounded-0">
            <div class="container">
                <form action="{{ route('pretrazi') }}" id="search-form" method="get">
                    <div class="input-group">
                        <input class="form-control rounded-start" type="text" name="{{ config('settings.search_keyword') }}" value="{{ request()->query('pojam') ?: '' }}" placeholder="Pretražite po nazivu ili autoru">
                        <button type="submit" class="btn btn-primary btn-lg fs-base"><i class="ci-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</header>
