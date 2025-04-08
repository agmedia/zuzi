<form name="pay" class="needs-validation w-100" action="{{ route('checkout') }}" novalidate method="GET">
    <input type="hidden" name="provjera" value="{{ $data['order_id'] }}">
    <div class="d-block pt-0 pb-2  text-start" >
        <div class="alert alert-info  d-flex"  role="alert">
            <div class="alert-icon">
                <i class="ci-announcement"></i>
            </div>
            <div><small>Prije kupnje potrebno je prihvatiti Opće uvjete korištenja i privatnosti</small></div>
        </div>

    </div>

    <div class="form-check form-check-inline">
        <label class="form-check-label" for="ex-check-4">{!! __('Slažem se sa :terms_of_service', [
                                                'terms_of_service' => '<a data-bs-toggle="modal" data-bs-target="#exampleModal" class="link-fx">'.__('Općim uvjetima korištenja i privatnosti').'</a>',
                                                'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="link-fx">'.__('Privacy Policy').'</a>',
                                        ]) !!}</label>
        <input class="form-check-input" type="checkbox" name="terms" id="terms" required>
        <div class="invalid-feedback" id="terms">Morate se složiti sa Uvjetima kupnje.</div>
    </div>
    <div class="d-flex mt-3">
    <div class="w-50 pe-3">
        <a class="btn btn-outline-primaryy d-block w-100" href="{{ route('naplata') }}"><i class="ci-arrow-left  me-1"></i><span class="d-none d-sm-inline">Povratak na plaćanje</span><span class="d-inline d-sm-none">Povratak</span></a>
    </div>
    <div class="w-50 ps-2">
        <button class="btn btn-primary d-block w-100" type="submit"><span class="d-none d-sm-inline">Završite narudžbu</span><span class="d-inline d-sm-none">Završi kupnju</span><i class="ci-arrow-right  ms-1"></i></button>
    </div>
    </div>
</form>



