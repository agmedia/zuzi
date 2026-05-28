@extends('emails.layouts.base')

@section('content')
    @php($expiresAt = \Illuminate\Support\Carbon::make($promoAction->date_end))
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="ag-mail-tableset" style="padding-bottom: 4px;">
                <h2 style="margin: 0 0 12px; font-size: 26px; line-height: 1.3; color: #1f2937;">Hvala vam na kupnji, {{ $order->payment_fname }}.</h2>
                <p style="margin: 0; font-size: 15px; line-height: 1.7; color: #4b5563;">
                    Nadamo se da uživate u svojim knjigama. Vaše mišljenje nam je jako važno jer pomaže drugim kupcima pri odabiru, a nama daje jasan signal što još više volite čitati.
                </p>
            </td>
        </tr>

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #fff5fa; border: 1px solid #f7c2dd; border-radius: 10px;">
                    <tr>
                        <td style="padding: 22px 20px; font-size: 15px; line-height: 1.8; color: #373f50;">
                            <p style="margin: 0 0 10px;">
                                🎁 <strong>TVOJA NAGRADA: -{{ (int) $promoAction->discount }}% na sve artikle</strong>
                            </p>

                            <p style="margin: 0 0 10px;">
                                Kod: <strong>{{ $promoAction->coupon }}</strong>
                            </p>
                            <p style="margin: 0;">
                                ⏳ Vrijedi samo sljedećih <strong>7 dana</strong>
                                @if ($expiresAt)
                                    <br>Točnije do <strong>{{ $expiresAt->format('d.m.Y. H:i') }}</strong>
                                @endif
                            </p>

                            <p style="margin: 12px 0 0;">
                                @if ((int) $order->user_id > 0)
                                    <strong>Kao registrirani kupac</strong> za svaki odobreni komentar dobivate i <strong>{{ \App\Models\Back\Marketing\Review::rewardPoints() }} loyalty bodova</strong>, do najviše <strong>{{ \App\Models\Back\Marketing\Review::monthlyLimit() }} komentara mjesečno</strong>.
                                @else
                                    <strong>Registrirani kupci</strong> za svaki odobreni komentar dobivaju i <strong>{{ \App\Models\Back\Marketing\Review::rewardPoints() }} loyalty bodova</strong>, do najviše <strong>{{ \App\Models\Back\Marketing\Review::monthlyLimit() }} komentara mjesečno</strong>.
                                @endif
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 0;">
                <h3 style="margin: 0 0 12px; font-size: 18px; color: #1f2937;">Odaberite knjigu i ostavite svoj komentar</h3>
            </td>
        </tr>

        @foreach ($reviewItems as $item)
            <tr>
                <td class="ag-mail-tableset" style="padding-top: 0;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #e5e7eb; border-radius: 8px;">
                        <tr>
                            <td style="padding: 18px 20px;">
                                <p style="margin: 0 0 14px; font-size: 17px; line-height: 1.5; color: #111827; font-weight: 600;">
                                    {{ $item['name'] }}
                                </p>
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td style="padding: 0 10px 10px 0;">
                                            <a href="{{ $item['product_url'] }}" class="ag-btn" style="width: 180px; color: #ffffff !important;">Pogledaj knjigu</a>
                                        </td>
                                        <td style="padding: 0 0 10px;">
                                            <a href="{{ $item['review_url'] }}" class="ag-btn" style="width: 180px; background-color: #111827; color: #ffffff !important;">Napiši komentar</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endforeach

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 6px;">
                <p style="margin: 0; font-size: 14px; line-height: 1.8; color: #4b5563;">
                    Hvala vam još jednom na povjerenju i na svakoj preporuci koju podijelite s nama.
                </p>
                <p style="margin: 14px 0 0; font-size: 14px; line-height: 1.8; color: #4b5563;">
                    Lijep pozdrav,<br>Zuzi Shop
                </p>
            </td>
        </tr>
    </table>
@endsection
