@extends('emails.layouts.base')

@section('content')
    <div class="ag-mail-tableset" style="padding: 30px;">
        <h1 style="margin: 0 0 18px; color: #2b3445; font-size: 25px; line-height: 1.3;">
            Nova izjava o jednostranom raskidu ugovora
        </h1>

        <p style="margin: 0 0 18px; color: #596273; font-size: 15px; line-height: 1.65;">
            Kupac je putem webshopa podnio i potvrdio izjavu o raskidu ugovora.
        </p>

        <div style="margin: 20px 0; padding: 16px; border-left: 4px solid #e50077; background: #f8f9fb; color: #2b3445; font-size: 15px; font-weight: 700; line-height: 1.6;">
            {{ $withdrawal->declaration }}
        </div>

        <table role="presentation" style="width: 100%; margin: 0 !important; table-layout: auto !important; font-size: 14px;">
            <tr><td style="width: 38%; padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Referenca</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ $withdrawal->reference }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Podneseno</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ optional($withdrawal->submitted_at)->format('d.m.Y. H:i:s T') }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Ime i prezime</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ $withdrawal->full_name }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">E-mail</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ $withdrawal->email }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Telefon</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ $withdrawal->phone ?: '—' }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Adresa</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ $withdrawal->address_line }}, {{ $withdrawal->postal_code }} {{ $withdrawal->city }}, {{ $withdrawal->country_code }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Broj narudžbe</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0;">{{ $withdrawal->order_number }}</td></tr>
            <tr><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; color: #747d8c;">Proizvodi</td><td style="padding: 8px; border-bottom: 1px solid #e5e9f0; white-space: pre-line;">{{ $withdrawal->items }}</td></tr>
        </table>

        <a
            href="{{ $adminUrl }}"
            style="display: inline-block; margin-top: 24px; padding: 12px 20px; border-radius: 4px; background: #e50077; color: #fff; font-weight: 700; text-decoration: none;"
        >Otvori u administraciji</a>
    </div>
@endsection
