@extends('emails.layouts.base')

@section('content')
    @php($customerName = trim((string) $order->payment_fname))
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="ag-mail-tableset" style="padding-bottom: 8px;">
                <h2 style="margin: 0 0 16px; font-size: 28px; line-height: 1.3; color: #1f2937;">
                    Bok{{ $customerName ? ' ' . $customerName : '' }} 👋
                </h2>

                <p style="margin: 0 0 14px; font-size: 16px; line-height: 1.8; color: #4b5563;">
                    primijetili smo da tvoja narudžba nije dovršena.
                </p>

                <p style="margin: 0; font-size: 16px; line-height: 1.8; color: #4b5563;">
                    Ako još želiš knjige koje si odabrao/la, možeš se vratiti na Zuzi i dovršiti kupnju kad ti odgovara.
                </p>
            </td>
        </tr>

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td>
                            <a href="{{ route('index') }}" class="ag-btn" style="width: 220px; color: #ffffff !important;">Vrati se na Zuzi</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 0;">
                <p style="margin: 0; font-size: 15px; line-height: 1.8; color: #4b5563;">
                    Vidimo se uskoro,<br>
                    tvoj Zuzi tim 💜
                </p>
            </td>
        </tr>
    </table>
@endsection
