@extends('emails.layouts.base')

@section('content')
    @php
        $buttonUrl = trim((string) ($notice['button_url'] ?? ''));

        if ($buttonUrl === '') {
            $buttonUrl = route('index');
        } elseif (\Illuminate\Support\Str::startsWith($buttonUrl, '/')) {
            $buttonUrl = url($buttonUrl);
        }
    @endphp

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td class="ag-mail-tableset" style="padding-bottom: 8px; text-align: center;">
                <h2 style="margin: 0 0 20px; font-size: 30px; line-height: 1.25; color: #e50077; font-weight: 700;">
                    {{ $notice['title'] }}
                </h2>

                @if(! empty($notice['intro']))
                    <p style="margin: 0; font-size: 18px; line-height: 1.75; color: #2f3746;">
                        {!! nl2br(e($notice['intro'])) !!}
                    </p>
                @endif
            </td>
        </tr>

        @if(! empty($notice['coupon_code']) || ! empty($notice['coupon_label']) || ! empty($notice['discount_text']))
            <tr>
                <td class="ag-mail-tableset" style="padding-top: 0;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 2px dashed #e50077;">
                        <tr>
                            <td style="padding: 26px 20px; text-align: center;">
                                @if(! empty($notice['coupon_label']))
                                    <div style="font-size: 18px; line-height: 1.5; color: #2f3746;">
                                        {{ $notice['coupon_label'] }}
                                    </div>
                                @endif

                                @if(! empty($notice['coupon_code']))
                                    <div style="margin-top: 12px; font-size: 34px; line-height: 1.2; color: #e50077; font-weight: 700;">
                                        {{ $notice['coupon_code'] }}
                                    </div>
                                @endif

                                @if(! empty($notice['discount_text']))
                                    <div style="margin-top: 12px; font-size: 18px; line-height: 1.5; color: #1f2937; font-weight: 700;">
                                        {{ $notice['discount_text'] }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif

        @if(! empty($notice['outro']))
            <tr>
                <td class="ag-mail-tableset" style="padding-top: 0; text-align: center;">
                    <p style="margin: 0; font-size: 18px; line-height: 1.75; color: #2f3746;">
                        {!! nl2br(e($notice['outro'])) !!}
                    </p>
                </td>
            </tr>
        @endif

        @if(! empty($notice['button_text']))
            <tr>
                <td class="ag-mail-tableset" style="padding-top: 0;">
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                        <tr>
                            <td>
                                <a href="{{ $buttonUrl }}" class="ag-btn" style="width: 260px; color: #ffffff !important; font-weight: 700;">
                                    {{ $notice['button_text'] }}
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif

        @if($validUntil)
            <tr>
                <td class="ag-mail-tableset" style="padding-top: 0; text-align: center;">
                    <p style="margin: 0; font-size: 14px; line-height: 1.7; color: #6b7280;">
                        Kupon vrijedi do: <strong>{{ $validUntil }}</strong>
                    </p>
                </td>
            </tr>
        @endif
    </table>
@endsection
