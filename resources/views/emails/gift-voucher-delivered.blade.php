@extends('emails.layouts.base')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="ag-mail-tableset">
                Pozdrav {{ $giftVoucher->recipient_name ?: '!' }}
            </td>
        </tr>
        <tr>
            <td class="ag-mail-tableset">
                Za vas je kupljen Zuzi poklon bon u iznosu <strong>€ {{ number_format($giftVoucher->amount, 2, ',', '.') }}</strong>.
            </td>
        </tr>
        @if($giftVoucher->message)
            <tr>
                <td class="ag-mail-tableset">
                    <strong>Poruka:</strong><br>
                    {!! nl2br(e($giftVoucher->message)) !!}
                </td>
            </tr>
        @endif
        <tr>
            <td class="ag-mail-tableset">
                <strong>Kod za popust:</strong><br>
                <span style="display:inline-block; margin-top:8px; padding:12px 16px; border:1px dashed #e50077; border-radius:8px; font-size:20px; font-weight:700; letter-spacing:1px;">
                    {{ $giftVoucher->code }}
                </span>
            </td>
        </tr>
        <tr>
            <td class="ag-mail-tableset">
                Kod unesite u polje za popust u košarici i iznos bona bit će obračunat kao popust na narudžbu.
            </td>
        </tr>
        @if($giftVoucher->sender_name || $giftVoucher->buyer_name)
            <tr>
                <td class="ag-mail-tableset">
                    Poklon šalje: <strong>{{ $giftVoucher->sender_name ?: $giftVoucher->buyer_name }}</strong>
                </td>
            </tr>
        @endif
    </table>
@endsection
