@extends('emails.layouts.base')

@section('content')
    <div class="ag-mail-tableset" style="padding: 30px;">
        <h1 style="margin: 0 0 18px; color: #2b3445; font-size: 25px; line-height: 1.3;">
            Potvrda primitka izjave o raskidu
        </h1>

        <p style="margin: 0 0 18px; color: #596273; font-size: 15px; line-height: 1.65;">
            Potvrđujemo da smo zaprimili vašu elektroničku izjavu o jednostranom raskidu ugovora.
            Ova poruka je potvrda na trajnom mediju; sačuvajte je.
        </p>

        <div style="margin-bottom: 20px; padding: 14px 16px; border: 1px solid #f1b4d3; background: #fff5fa; line-height: 1.65;">
            <strong>Referenca:</strong> {{ $withdrawal->reference }}<br>
            <strong>Datum i vrijeme podnošenja:</strong> {{ optional($withdrawal->submitted_at)->format('d.m.Y. H:i:s T') }}<br>
            <strong>Sredstvo potvrde:</strong> E-mail — {{ $withdrawal->email }}
        </div>

        <div style="margin: 20px 0; padding: 16px; border-left: 4px solid #e50077; background: #f8f9fb; color: #2b3445; font-size: 15px; font-weight: 700; line-height: 1.6;">
            {{ $withdrawal->declaration }}
        </div>

        <table role="presentation" style="width: 100%; margin: 0 !important; table-layout: auto !important; font-size: 14px;">
            <tr><td style="width: 38%; padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Ime i prezime</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ $withdrawal->full_name }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">E-mail</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ $withdrawal->email }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Telefon</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ $withdrawal->phone ?: '—' }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Adresa</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ $withdrawal->address_line }}, {{ $withdrawal->postal_code }} {{ $withdrawal->city }}, {{ $withdrawal->country_code }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Broj narudžbe / ugovora</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ $withdrawal->order_number }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Datum narudžbe</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ optional($withdrawal->contract_date)->format('d.m.Y.') ?: '—' }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Datum primitka robe</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ optional($withdrawal->received_date)->format('d.m.Y.') ?: '—' }}</td></tr>
        </table>

        <div style="margin-top: 22px;">
            <strong>Proizvodi / dio ugovora</strong>
            <p style="margin: 7px 0 0; white-space: pre-line; line-height: 1.65;">{{ $withdrawal->items }}</p>
        </div>

        @if ($withdrawal->note)
            <div style="margin-top: 20px;">
                <strong>Dodatna napomena</strong>
                <p style="margin: 7px 0 0; white-space: pre-line; line-height: 1.65;">{{ $withdrawal->note }}</p>
            </div>
        @endif

        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e9f0;">
            <strong>Adresa za povrat robe</strong>
            <p style="margin: 7px 0 0; white-space: pre-line; line-height: 1.65;">{{ $withdrawalSettings['return_address'] }}</p>
            <p style="margin: 12px 0 0; line-height: 1.65;">{{ $returnCostText }}</p>
            @if (($withdrawalSettings['instructions'] ?? '') !== '')
                <p style="margin: 12px 0 0; white-space: pre-line; line-height: 1.65;">{{ $withdrawalSettings['instructions'] }}</p>
            @endif
        </div>
    </div>
@endsection
