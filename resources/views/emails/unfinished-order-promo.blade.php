@extends('emails.layouts.base')

@section('content')
    @php($expiresAt = \Illuminate\Support\Carbon::make($promoAction->date_end))
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="ag-mail-tableset" style="padding-bottom: 8px;">
                <h2 style="margin: 0 0 16px; font-size: 28px; line-height: 1.3; color: #1f2937;">Bok 👋</h2>

                <p style="margin: 0 0 14px; font-size: 16px; line-height: 1.8; color: #4b5563;">
                    hvala ti na kupnji na Zuzi 📚<br>
                    tvoja narudžba je upravo ono što volimo vidjeti - još jedan ljubitelj knjiga ❤️
                </p>

                <p style="margin: 0; font-size: 16px; line-height: 1.8; color: #4b5563;">
                    Imamo nešto za tebe 👇
                </p>
            </td>
        </tr>

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #fff5fa; border: 1px solid #f7c2dd; border-radius: 10px;">
                    <tr>
                        <td style="padding: 22px 20px; font-size: 15px; line-height: 1.8; color: #373f50;">
                            <p style="margin: 0 0 10px;">
                                🎁 <strong>TVOJA NAGRADA: -{{ (int) $promoAction->discount }}% na sljedeću kupnju</strong>
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
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 0;">
                <p style="margin: 0 0 14px; font-size: 16px; line-height: 1.8; color: #4b5563;">
                    📖 Ako si već u "čitateljskom moodu", sad je savršen trenutak da uzmeš još nešto:
                </p>

                <p style="margin: 0 0 22px; font-size: 16px; line-height: 1.9; color: #1f2937;">
                    👉 Najčitanije knjige ovog tjedna<br>
                    👉 Preporuke baš za tebe
                </p>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td>
                            <a href="{{ route('index') }}" class="ag-btn" style="width: 220px; color: #ffffff !important;">Pogledaj knjige</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 0;">
                <p style="margin: 0; font-size: 15px; line-height: 1.8; color: #4b5563;">
                    Vidimo se opet uskoro,<br>
                    tvoj Zuzi tim 💜
                </p>
            </td>
        </tr>
    </table>
@endsection
