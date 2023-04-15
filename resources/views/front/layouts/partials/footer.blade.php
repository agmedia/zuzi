<!-- Footer-->
<footer class="bg-dark pt-5">

    <div class="container pt-2 pb-3">
        <div class="row">
            <div class="col-md-6 text-center text-md-start mb-4">
                <div class="text-nowrap mb-3 d-none d-sm-block"><a class="d-inline-block align-middle mt-n2 me-2" href="#"><img class="d-block" src="{{ asset('media/img/logobijeli.svg') }}" width="180" height="76" alt="Antikvarijat Biblos"></a></div>
                <p class="fs-sm text-white opacity-70 pb-1 d-none d-sm-block">Otkup i prodaja starih i rijetkih izdanja hrvatskih i stranih knjiga,<br> te zemljovida i veduta</p>
                <h6 class="d-inline-block pe-3 me-3 border-end border-light"><span class="text-primary">{{ $products }} </span><span class="fw-normal text-white">Artikala</span></h6>
                <h6 class="d-inline-block pe-3 me-3 "><span class="text-primary">{{ $users + 850 }} </span><span class="fw-normal text-white">Kupaca</span></h6>

                <div class="widget mt-4 text-md-nowrap text-center text-md-start">
                    <a class="btn-social bs-light bs-instagram me-2 mb-2" href="https://www.instagram.com/antikvarijat_biblos/"><i class="ci-instagram"></i></a>
                    <a class="btn-social bs-light bs-facebook me-2 mb-2" href="https://www.facebook.com/AntikvarijatBiblos/"><i class="ci-facebook"></i></a>
                </div>
            </div>
            <!-- Mobile dropdown menu (visible on screens below md)-->
            <div class="col-12 d-md-none text-center mb-4 pb-2">
                <div class="btn-group dropdown d-block mx-auto mb-3">
                    <button class="btn btn-outline-light border-light dropdown-toggle" type="button" data-bs-toggle="dropdown">Uvjeti kupnje</button>
                    <ul class="dropdown-menu my-1">
                        @foreach ($uvjeti_kupnje as $page)
                            <li><a class="dropdown-item" href="{{ route('catalog.route.page', ['page' => $page]) }}">{{ $page->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <!-- Desktop menu (visible on screens above md)-->
            <div class="col-md-3 d-none d-md-block text-center text-md-start mb-4">
                <div class="widget widget-links widget-light pb-2">
                    <h3 class="widget-title text-light">Uvjeti kupnje</h3>
                    <ul class="widget-list">
                        @foreach ($uvjeti_kupnje as $page)
                            <li class="widget-list-item"><a class="widget-list-link" href="{{ route('catalog.route.page', ['page' => $page]) }}">{{ $page->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div class="col-md-3 d-none d-md-block text-center text-md-start mb-4">
                <div class="widget widget-links widget-light pb-2">
                    <h3 class="widget-title text-light">Načini plaćanja</h3>
                    <ul class="widget-list  ">
                        <li class="widget-list-item"><a class="widget-list-link" > kreditnom karticom jednokratno ili na rate</a></li>
                        <li class="widget-list-item"><a class="widget-list-link" > virmanom / općom uplatnicom / internet bankarstvom</a></li>
                        <li class="widget-list-item"><a class="widget-list-link" >gotovinom prilikom pouzeća</a></li>

                        <li class="widget-list-item"><a class="widget-list-link" >osobno preuzimanje i plaćanje u antikvarijatu</a></li>
                    </ul>

                </div>
            </div>
        </div>
    </div>
    <!-- Second row-->
    <div class="pt-4 bg-accent">


        <div class="container">

            <div class="row pt-3 pb-3 d-none d-sm-flex">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="d-flex">
                        <i class="ci-gift text-primary" style="font-size: 2.25rem;"></i>
                        <div class="ps-3">
                            <h6 class="fs-base text-light mb-1">Besplatna dostava</h6>
                            <p class="mb-0 fs-ms text-light opacity-50">Za sve narudžbe u RH iznad 70 EUR.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="d-flex">
                        <i class="ci-security-check text-primary" style="font-size: 2.25rem;"></i>
                        <div class="ps-3">
                            <h6 class="fs-base text-light mb-1">Zaštita kupca</h6>
                            <p class="mb-0 fs-ms text-light opacity-50">Od narudžbe pa sve do dostave</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="d-flex">
                        <i class="ci-message text-primary" style="font-size: 2.25rem;"></i>
                        <div class="ps-3">
                            <h6 class="fs-base text-light mb-1">Korisnička podrška</h6>
                            <p class="mb-0 fs-ms text-light opacity-50">Prije i nakon vaše kupnje</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="d-flex">
                        <i class="ci-card text-primary" style="font-size: 2.25rem;"></i>
                        <div class="ps-3">
                            <h6 class="fs-base text-light mb-1">100% sigurna kupnja</h6>
                            <p class="mb-0 fs-ms text-light opacity-50">Sigurno i pozdano plaćanje karticama</p>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="hr-light mb-4 d-none d-sm-block">

            <div class="d-md-flex justify-content-between pt-2">
                <div class="pb-4 fs-xs text-light opacity-50 text-center text-md-start">© Sva prava pridržana. Web by <a class="text-light" title="Izrada web shopa - B2C ili B2B web trgovina - AG media" href="https://www.agmedia.hr/usluge/izrada-web-shopa/" target="_blank" rel="noopener">AG media</a></div>
                <div class="widget widget-links widget-light pb-4 text-center text-md-end">
                    <img class="d-inline-block" style="width: 55px;margin-right:3px" src="{{ config('settings.images_domain') }}media/cards/visa.svg" width="55" height="35" alt="Visa"/>
                    <img class="d-inline-block" style="width: 55px;margin-right:3px" src="{{ config('settings.images_domain') }}media/cards/maestro.svg" width="55" height="35" alt="Maestro"/>
                    <img class="d-inline-block" style="width: 55px;margin-right:3px" src="{{ config('settings.images_domain') }}media/cards/mastercard.svg" width="55" height="35" alt="MasterCard"/>
                    <img class="d-inline-block" style="width: 55px;margin-right:3px" src="{{ config('settings.images_domain') }}media/cards/diners.svg" width="55" height="35" alt="Diners"/>


                </div>
            </div>
        </div>
    </div>
</footer>
