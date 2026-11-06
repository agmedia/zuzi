@extends('emails.layouts.base')

@section('content')
    @php($customerName = trim((string) $order->payment_fname))
    @php($reminderSequence = $sequence ?? 1)
    @php($recoveryLink = $recoveryUrl ?? app(\App\Services\AbandonedCartReminderService::class)->recoveryUrl($order))
    @php($selectedProducts = $order->products->filter(fn ($item) => (int) $item->product_id > 0))
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="ag-mail-tableset" style="padding-bottom: 8px;">
                <h2 style="margin: 0 0 16px; font-size: 28px; line-height: 1.3; color: #1f2937;">
                    Bok{{ $customerName ? ' ' . $customerName : '' }} 👋
                </h2>

                <p style="margin: 0 0 14px; font-size: 16px; line-height: 1.8; color: #4b5563;">
                    @if ($reminderSequence === 2)
                        tvoj odabir knjiga još je spremljen, ali narudžba nije dovršena.
                    @else
                        primijetili smo da tvoja narudžba nije dovršena.
                    @endif
                </p>

                <p style="margin: 0; font-size: 16px; line-height: 1.8; color: #4b5563;">
                    Ako još želiš knjige koje si odabrao/la, možeš se vratiti na Zuzi i dovršiti kupnju kad ti odgovara.
                </p>
            </td>
        </tr>

        @if ($selectedProducts->isNotEmpty())
            <tr>
                <td class="ag-mail-tableset" style="padding-top: 8px;">
                    <h3 style="margin: 0 0 12px; color: #373f50; font-size: 18px; line-height: 24px;">Tvoj odabir</h3>
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                        @foreach ($selectedProducts as $item)
                            <tr>
                                <td style="padding: 12px 8px 12px 0; border-bottom: 1px solid #ececf0; color: #373f50; font-size: 14px; line-height: 21px;">
                                    <strong>{{ $item->name }}</strong><br>
                                    Količina: {{ (int) $item->quantity }}
                                </td>
                                <td align="right" width="130" style="padding: 12px 0; border-bottom: 1px solid #ececf0; color: #373f50; font-size: 14px; line-height: 21px;">
                                    {{ number_format((float) $item->price, 2, ',', '.') }} € / kom.
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <p style="margin: 12px 0 0; color: #626a78; font-size: 12px; line-height: 18px;">Prikazane su cijene pri odabiru. Aktualne cijene i dostupne količine provjeri u košarici.</p>
                </td>
            </tr>
        @endif

        @if ($showPromotion ?? false)
            <tr>
                <td class="ag-mail-tableset" style="padding-top: 0;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #f3c8da; background-color: #fff7fb;">
                        <tr>
                            <td align="center" style="padding: 20px; color: #373f50; font-size: 15px; line-height: 23px;">
                                <strong style="color: #e50077; font-size: 18px;">20 % popusta na sve artikle</strong><br>
                                U košarici upiši kod<br>
                                <strong style="color: #e50077; font-size: 28px; line-height: 40px; letter-spacing: 3px;">ZUZI20</strong><br>
                                Vrijedi do <strong>15.11.2026.</strong><br>
                                <span style="font-size: 12px;">Popust se ne primjenjuje na poklon-bonove.</span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td>
                            <a href="{{ $recoveryLink }}" class="ag-btn" style="width: 220px; color: #ffffff !important;">Dovrši kupnju</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td class="ag-mail-tableset" style="padding-top: 0;">
                <p style="margin: 0; color: #626a78; font-size: 12px; line-height: 18px;">Poveznica vraća dostupne knjige u košaricu. Ako si u međuvremenu dovršio/la kupnju, ovaj podsjetnik možeš zanemariti.</p>
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
