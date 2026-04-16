@if (! empty($loyaltyMailData))
    <tr>
        <td class="ag-mail-tableset">
            <div style="padding: 18px; border: 1px solid #f3c8da; border-radius: 10px; background-color: #fff7fb;">
                <strong style="display: block; margin-bottom: 10px; color: #e50077; font-size: 16px;">Loyalty bodovi</strong>

                @if (($context ?? 'order-sent') === 'status-paid')
                    <div>Uspješnim plaćanjem ove narudžbe ostvarili ste <strong>{{ $loyaltyMailData['earned'] }} Loyalty bodova</strong>.</div>
                @elseif ($loyaltyMailData['is_paid'])
                    <div>Ovom narudžbom ostvarili ste <strong>{{ $loyaltyMailData['earned'] }} Loyalty bodova</strong>.</div>
                @else
                    <div>Ova narudžba donosi vam <strong>{{ $loyaltyMailData['earned'] }} Loyalty bodova</strong>.</div>
                @endif

                @if ($loyaltyMailData['spent'] > 0)
                    <div style="margin-top: 8px;">Na ovoj narudžbi iskoristili ste <strong>{{ $loyaltyMailData['spent'] }} bodova</strong>.</div>
                @endif

                <div style="margin-top: 8px;">Stanje i povijest bodova možete vidjeti na svom korisničkom računu.</div>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top: 14px;">
                    <tr>
                        <td>
                            <a href="{{ $loyaltyMailData['url'] }}" class="ag-btn" style="width: 240px;">Pogledaj Loyalty bodove</a>
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
@endif
