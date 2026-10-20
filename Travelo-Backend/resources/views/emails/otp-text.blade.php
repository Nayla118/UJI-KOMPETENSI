{{ __('travelo.otp.text.subject', ['app' => config('mail.from.name', 'Travelo')]) }}

{{ __('travelo.otp.text.intro') }}

    {{ $code }}

{{ __('travelo.otp.text.expiry', ['minutes' => $ttlMinutes]) }}

{{ __('travelo.otp.text.security') }}

{{ __('travelo.otp.text.ignore') }}

--
{{ __('travelo.otp.text.footer') }}
