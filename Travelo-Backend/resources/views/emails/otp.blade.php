<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ __('travelo.otp.title') }}</title>
</head>
<body style="margin:0;padding:0;background:#FFF9F7;font-family:'Nunito Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#493747;">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
    {{ __('travelo.otp.preheader') }}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF9F7;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#FFFFFF;border:1px solid #E7D9DF;border-radius:24px;overflow:hidden;">
                <tr>
                    <td style="background:#A95372;padding:32px 28px;text-align:center;">
                        <div style="font-family:'Nunito',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:26px;font-weight:800;color:#FFFFFF;letter-spacing:.5px;">
                            Travelo
                        </div>
                        <h1 style="margin:14px 0 6px;color:#FFFFFF;font-family:'Nunito',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:22px;line-height:1.3;font-weight:700;">
                            {{ __('travelo.otp.title') }}
                        </h1>
                        <p style="margin:0;color:#F9E7ED;font-size:14px;line-height:1.6;">
                            {{ __('travelo.otp.subtitle') }}
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px 28px;">
                        <p style="margin:0 0 18px;color:#493747;font-size:14px;line-height:1.7;">
                            {{ __('travelo.otp.greeting', ['minutes' => $ttlMinutes]) }}
                        </p>

                        <div style="text-align:center;margin:0 0 22px;">
                            <span style="display:inline-block;background:#F9E7ED;border:1px solid #E7D9DF;border-radius:16px;padding:16px 22px;font-family:'Nunito',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:34px;line-height:1.2;font-weight:800;letter-spacing:10px;color:#A95372;white-space:nowrap;">
                                {{ $code }}
                            </span>
                        </div>

                        <p style="margin:0 0 10px;color:#6E5A68;font-size:13px;line-height:1.7;">
                            {{ __('travelo.otp.security') }}
                        </p>
                        <p style="margin:0;color:#6E5A68;font-size:13px;line-height:1.7;">
                            {{ __('travelo.otp.ignore') }}
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="background:#F9E7ED;padding:18px 28px;text-align:center;">
                        <p style="margin:0;color:#6E5A68;font-size:12px;line-height:1.6;">
                            {{ __('travelo.otp.footer', ['year' => date('Y')]) }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
