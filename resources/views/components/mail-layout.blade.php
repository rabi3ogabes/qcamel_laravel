@props(['logo' => null, 'preview' => '', 'title' => null, 'footer' => '', 'font' => null])
@php($rtl = app()->getLocale() !== 'en')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#F5EFE7;font-family:{{ $font ?? "'Segoe UI', Tahoma, Arial, sans-serif" }};color:#14110E;">
    <span style="display:none!important;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;">{{ $preview ?? '' }}</span>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5EFE7;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border-radius:18px;overflow:hidden;border:1px solid #EDE6DA;">
                    <tr><td style="height:4px;background:linear-gradient(90deg,#C9A227,#9C1638,#C9A227);background-color:#C9A227;"></td></tr>
                    <tr>
                        <td style="padding:32px 32px 8px 32px;text-align:center;" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
                            @if(!empty($logo))
                                <img src="{{ $logo }}" alt="{{ __('common.logo') }}" height="48" style="height:48px;max-width:200px;object-fit:contain;display:inline-block;">
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 32px 32px;text-align:{{ $rtl ? 'right' : 'left' }};" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px;background:#FCFAF6;border-top:1px solid #EDE6DA;text-align:center;color:#6B6257;font-size:12px;line-height:1.7;">
                            {{ $footer ?? '' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
