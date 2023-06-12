<h4 class="text-secondary my-4 mt-4">{{ __('front/checkout.personal_info') }}</h4>
<table class="table table-striped mb-5">
    <tbody>
    <tr>
        <th scope="row">{{ __('front/checkout.name') }}</th>
        <td>{{ $data['firstname'] }}</td>

    </tr>
    <tr>
        <th scope="row">{{ __('front/checkout.surname') }}</th>
        <td>{{ $data['lastname'] }}</td>

    </tr>
    <tr>
        <th scope="row">{{ __('front/checkout.mobile_number') }}</th>
        <td>{{ $data['telephone'] }}</td>
    </tr>
    <tr>
        <th scope="row">{{ __('front/checkout.email_address') }}</th>
        <td>{{ $data['email'] }}</td>
    </tr>
    </tbody>
</table>


<form name="pay" class="w-100" action="{{ $data['action'] }}" method="POST">

    <input type="hidden" name="amount" value="{{ $data['total'] }}">
    <input id="cart" name="cart" value="Web shop kupnja {{ $data['order_id'] }}" type="hidden"/>
    <input id="currency" name="currency" value="{{ $data['currency'] }}" type="hidden"/>
    <input id="language" name="language" value="{{ $data['lang'] }}" type="hidden"/>
    <input type="hidden" name="order_number" value="{{ $data['order_id'] }}">
    <input id="require_complete" name="require_complete" value="false" hidden="true"/>
    <input type="hidden" name="store_id" value="{{ $data['merchant'] }}">
    <input type="hidden" name="signature" value="{{ $data['md5'] }}">
    <input type="hidden" name="cardholder_name" value="{{ $data['firstname'] }}">
    <input type="hidden" name="cardholder_surname" value="{{ $data['lastname'] }}">
    <input type="hidden" name="cardholder_phone" value="{{ $data['telephone'] }}">
    <input type="hidden" name="cardholder_email" value="{{ $data['email'] }}">
    <input type="hidden" name="payment_all" value="{{ $data['number_of_installments'] }}">
    <input type="hidden" name="version" value="1.3">

    <div class="d-flex mt-3">
        <div class="w-50 pe-3"><a class="btn btn-secondary d-block w-100" href="{{ route('index') }}"><i class="ci-arrow-left  me-1"></i><span class="d-none d-sm-inline">{{ __('front/checkout.backbtnpayment') }}</span><span class="d-inline d-sm-none">{{ __('front/checkout.backbtn') }}</span></a></div>
        <div class="w-50 ps-2">
            <button class="btn btn-primary d-block w-100" type="submit"><span class="d-none d-sm-inline">{{ __('front/checkout.finishorder') }}</span><span class="d-inline d-sm-none">{{ __('front/checkout.finishshoping') }}</span><i class="ci-arrow-right ms-1"></i></button>
        </div>
    </div>
    <div class="clearfix"></div>
</form>
