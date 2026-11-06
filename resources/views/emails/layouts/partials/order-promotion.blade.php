@if (now('Europe/Zagreb')->lt('2026-11-15 00:00:00') && app(\App\Support\InterliberOffer::class)->isActive())
    <tr>
        <td class="ag-mail-tableset" style="padding: 20px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #f3c8da; background-color: #fff7fb;">
                <tr>
                    <td align="center" style="padding: 24px 20px; color: #373f50; font-family: Arial, sans-serif; font-size: 14px; line-height: 22px;">
                        <h3 style="margin: 0 0 12px; color: #e50077; font-size: 20px; line-height: 26px;">Poklon za vašu sljedeću kupnju</h3>
                        <p style="margin: 0 0 16px;">Još jedna dobra priča za vas ili poklon dragoj osobi?<br>Ostvarite <strong>20 % popusta na sve artikle</strong> uz poklon kod:</p>
                        <div style="margin: 0 0 16px; color: #e50077; font-size: 30px; font-weight: bold; line-height: 38px; letter-spacing: 4px;">ZUZI20</div>
                        <p style="margin: 0 0 18px;">Kod upišite u košarici pri sljedećoj kupnji.<br>Vrijedi do <strong>15.11.2026.</strong></p>
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                            <tr>
                                <td align="center" bgcolor="#e50077" style="border-radius: 4px;">
                                    <a href="{{ route('interliber.popusti') }}?utm_source=order_confirmation&amp;utm_medium=email&amp;utm_campaign=zuzi20_2026" style="display: inline-block; padding: 13px 20px; color: #ffffff; font-size: 15px; font-weight: bold; line-height: 22px; text-decoration: none;">Iskoristi 20 % popusta</a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin: 16px 0 0; color: #626a78; font-size: 12px; line-height: 18px;">Popust se ne primjenjuje na poklon-bonove.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
@endif
