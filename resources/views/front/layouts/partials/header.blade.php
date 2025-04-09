<!-- Navbar-->
<header class="bg-dark shadow-sm fixed-top" data-fixed-element>
    <div class="navbar navbar-expand-lg navbar-dark py-0">
        <div class="container-fluid">
            <a class="navbar-brand d-none d-md-block me-1 flex-shrink-0 py-0" href="{{ route('index') }}">
                <div class="logo-bg" style="background-color:#fff;margin-left:-30px; padding: 0 0 0 30px; ">
                    <img src="{{ asset('media/img/zuzi-logo.webp') }}" width="90"  alt="Web shop | ZUZI Shop | Prodaja knjiga | Otkup knjiga | Webshop">
                    <span class="arrow"></span>
                </div>
            </a>
            <a class="navbar-brand pt-0 pb-0 d-md-none me-0" href="{{ route('index') }}">
                <div class="logo-bg" style="background-color:#fff;margin-left:-30px; padding: 0 0 0 30px; ">
                <img src="{{ asset('media/img/zuzi-logo.webp') }}" width="55" alt="Žuži Shop">
                <span class="arrow-mb"></span>
                </div>
            </a>
            <!-- Search-->
            <form action="{{ route('pretrazi') }}" id="search-form-first" class="w-100 d-none d-lg-flex flex-nowrap mx-4" method="get">
                <div class="input-group "><i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                    <input class="form-control rounded-start w-100" type="text" name="{{ config('settings.search_keyword') }}" value="{{ request()->query('pojam') ?: '' }}" placeholder="Pretražite po nazivu ili autoru">
                </div>
            </form>
            <!-- Toolbar-->
            <div class="navbar-toolbar d-flex flex-shrink-0 align-items-center ms-xl-2">
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" aria-label="Open the menu" data-bs-target="#sideNav"><span class="navbar-toggler-icon" aria-hidden="true"></span></button><a class="navbar-tool d-flex d-lg-none" href="#searchBox" data-bs-toggle="collapse" aria-label="Search" role="button" aria-expanded="false" aria-controls="searchBox"><span class="navbar-tool-tooltip">Pretraži</span>
                    <div class="navbar-tool-icon-box"><i class="navbar-tool-icon ci-search"></i></div></a>

                @if(auth()->user())
                    <a class="navbar-tool ms-1 ms-lg-0 me-n1 me-lg-2" aria-label="My account" href="{{ route('login') }}" >
                        <div class="navbar-tool-icon-box"><i class="navbar-tool-icon ci-user"></i></div>
                        <div class="navbar-tool-text ms-n3"><small>{{ auth()->user()->details->fname }} {{ auth()->user()->details->lname }}</small>Moj Račun</div>
                    </a>
                @else
                    <a class="navbar-tool ms-1 ms-lg-0 me-n1 me-lg-2" data-tab-id="pills-signin-tab" aria-label="Prijavi se" href="signin-tab"  role="button" data-bs-toggle="modal" data-bs-target="#signin-modal">
                        <div class="navbar-tool-icon-box"><i class="navbar-tool-icon ci-user"></i></div>
                        <div class="navbar-tool-text ms-n3">Prijavi se</div>
                    </a>
                @endif

                <cart-nav-icon carturl="{{ route('kosarica') }}" checkouturl="{{ route('naplata') }}"></cart-nav-icon>

            </div>
        </div>
    </div>
    <!-- Search collapse-->
    <div class="collapse" id="searchBox">
        <div class="card pt-2 pb-4 border-0 rounded-0">
            <div class="container">
                <form action="{{ route('pretrazi') }}" id="search-form" method="get">
                    <div class="input-group"><i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                        <input class="form-control rounded-start" type="text" name="{{ config('settings.search_keyword') }}" value="{{ request()->query('pojam') ?: '' }}" placeholder="Pretražite proizvode">
                        <button type="submit" class="btn btn-primary btn-lg fs-base"><i class="ci-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</header>

<!-- Sidebar menu-->
<aside class="offcanvas offcanvas-expand w-100 border-end zindex-lg-5 pt-lg-5" id="sideNav" style="max-width: 18.875rem;">

    <ul class="nav nav-tabs nav-justified mt-0 mt-lg-5 mb-0" role="tablist" >
        <li class="nav-item"><a class="nav-link fw-medium active" href="#categories" data-bs-toggle="tab" role="tab">Kategorije</a></li>
        <li class="nav-item"><a class="nav-link fw-medium" href="#menu" data-bs-toggle="tab" role="tab">Info</a></li>
        <li class="nav-item d-lg-none"><a class="nav-link " href="#" data-bs-dismiss="offcanvas" aria-label="Close Navigation" role="tab"><i class="ci-close fs-xs me-2"></i></a></li>
    </ul>
    <div class="offcanvas-body px-0 pt-3 pb-0" data-simplebar>
        <div class="tab-content">
            <filter-view ids="{{ isset($ids) ? $ids : null }}"
                         group="kategorija-proizvoda"
                         cat="{{ isset($cat) ? $cat : null }}"
                         subcat="{{ isset($subcat) ? $subcat : null }}"
                         author="{{ isset($author) ? $author['slug'] : null }}"
                         publisher="{{ isset($publisher) ? $publisher['slug'] : null }}">
            </filter-view>
            <!-- Menu-->
            <div class="sidebar-nav tab-pane fade" id="menu" role="tabpanel">
                <div class="widget widget-categories">
                    <div class="accordion" id="shop-menu">
                        <!-- Homepages-->
                        @foreach ($uvjeti_kupnje->sortBy('title') as $page)
                            <div class="accordion-item border-bottom">
                                <h3 class="accordion-header px-grid-gutter"><a class="nav-link-style d-block fs-md  py-3" href="{{ route('catalog.route.page', ['page' => $page]) }}"><span class="d-flex align-items-center">{{ $page->title }}</span></a></h3>
                            </div>
                        @endforeach

                        <div class="accordion-item border-bottom">
                            <h3 class="accordion-header px-grid-gutter"><a class="nav-link-style d-block fs-md  py-3" href="{{ route('kontakt') }}"><span class="d-flex align-items-center">Kontaktirajte nas</span></a></h3>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="offcanvas-footer d-block px-grid-gutter pt-4 pb-3 mb-2">


     <a class="btn-social bs-light bg-primary bs-facebook me-2 mb-2" href="https://www.facebook.com/zuziobrt/"><i class="ci-facebook"></i></a><a class="btn-social bs-light bg-primary bs-instagram me-2 mb-2" href="https://www.instagram.com/zuziobrt/"><i class="ci-instagram"></i></a>





    </div>
</aside>
