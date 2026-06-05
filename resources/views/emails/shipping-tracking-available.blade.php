@extends('emails.layouts.base')

@section('content')
    @php
        $customerName = trim((string) $order->payment_fname);
        $trackingCode = $order->tracking_code ?: $order->shipping_parcel_id;
        $trackingUrl = app(\App\Services\Shipping\OrderTrackingService::class)->trackingUrlForOrder($order);
    @endphp

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="ag-mail-tableset" style="padding-bottom: 8px;">
                <h2 style="margin: 0 0 14px; font-size: 26px; line-height: 1.3; color: #1f2937;">
                    Vaša narudžba je poslana
                </h2>

                <p style="margin: 0; font-size: 15px; line-height: 1.8; color: #4b5563;">
                    Bok{{ $customerName ? ' ' . $customerName : '' }},<br>
                    vaša narudžba <strong>#{{ $order->id }}</strong> predana je dostavnoj službi {{ $carrierLabel }}.
                </p>
            </td>
        </tr>

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #fff5fa; border: 1px solid #f7c2dd; border-radius: 10px;">
                    <tr>
                        <td style="padding: 20px; font-size: 15px; line-height: 1.8; color: #373f50;">
                            <p style="margin: 0 0 8px;">
                                <strong>Broj pošiljke:</strong> {{ $trackingCode }}
                            </p>

                            @if($order->shipping_tracking_status)
                                <p style="margin: 0 0 8px;">
                                    <strong>Trenutni status:</strong> {{ $order->shipping_tracking_status }}
                                </p>
                            @endif

                            <p style="margin: 0;">
                                <strong>Način dostave:</strong> {{ $order->shipping_method }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        @if($trackingUrl)
            <tr>
                <td class="ag-mail-tableset" style="padding-top: 0;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                        <tr>
                            <td>
                                <a href="{{ $trackingUrl }}" class="ag-btn" style="width: 220px; color: #ffffff !important;">Prati pošiljku</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 0;">
                <p style="margin: 0; font-size: 14px; line-height: 1.8; color: #4b5563;">
                    Tracking status se može promijeniti tek nakon što dostavna služba obradi pošiljku.
                    Ako link ne prikaže novi status odmah, pokušajte ponovno malo kasnije.
                </p>

                <p style="margin: 14px 0 0; font-size: 14px; line-height: 1.8; color: #4b5563;">
                    Lijep pozdrav,<br>Zuzi Shop
                </p>
            </td>
        </tr>
    </table>
@endsection
