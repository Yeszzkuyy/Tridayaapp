<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $proposal->proposal_number }}</title>
</head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;">
        <p style="margin:0 0 16px;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">
            {{ __('Quotation') }} {{ $proposal->proposal_number }}
        </p>
        <div style="font-size:14px;line-height:1.6;">
            {!! nl2br(e($bodyText)) !!}
        </div>
    </div>
</body>
</html>
