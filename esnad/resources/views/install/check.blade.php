<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Install check | ESNAD</title>
    <style>
        body { font: 15px/1.5 system-ui, sans-serif; margin: 0; padding: 24px; color: #141413; background: #faf9f5; }
        main { max-width: 960px; margin: auto; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #d4d4d2; }
        th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #e2e2e2; vertical-align: top; }
        .pass { color: #0e8345; } .fail { color: #de1135; font-weight: 600; } .warn { color: #9f6402; }
        code { font-size: 13px; word-break: break-all; }
    </style>
</head>
<body>
<main>
    <h1>Install check</h1>
    <p>{{ count($checks) }} checks, {{ $failed }} failed, {{ $warned }} warnings. Mode <strong>{{ config('esnad.hosting_mode') }}</strong>, environment <strong>{{ app()->environment() }}</strong>, run at {{ now()->toDateTimeString() }} Africa/Cairo.</p>
    <table>
        <thead><tr><th>Check</th><th>Result</th><th>Detail</th></tr></thead>
        <tbody>
        @foreach ($checks as $c)
            <tr><td>{{ $c['label'] }}</td><td class="{{ $c['status'] }}">{{ strtoupper($c['status']) }}</td><td><code>{{ $c['detail'] }}</code></td></tr>
        @endforeach
        </tbody>
    </table>
    <p>Not testable from inside PHP, so ask the host in writing: SSH access, email sending limits, the data centre country, and whether heavy processes are allowed.</p>
</main>
</body>
</html>
