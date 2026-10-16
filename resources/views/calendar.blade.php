@php
    $time = fn (?Carbon\Carbon $t) => $t ? $t->format('d M Y, H:i') : '-';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>{{ __('Calendar') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            padding: 2rem 1rem;
        }
        .container { max-width: 720px; margin: 0 auto; }
        h1 { margin-bottom: 1.5rem; font-size: 1.5rem; }
        .status { font-size: .85rem; color: #64748b; margin-bottom: 1rem; }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
        }
        .card h2 { font-size: 1.05rem; margin-bottom: .35rem; }
        .card .time { color: rgb(var(--accent-600) / 1); font-size: .9rem; font-weight: 600; margin-bottom: .5rem; }
        .card .desc {
            color: #475569;
            font-size: .9rem;
            white-space: pre-line;
            line-height: 1.5;
        }
        .empty { text-align: center; color: #64748b; padding: 3rem 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ __('Upcoming Calendar') }}</h1>
        <div class="status" id="status">{{ __('Diperbarui: :time — refresh otomatis tiap 30 detik', ['time' => now()->format('H:i:s')]) }}</div>

        @forelse ($events as $event)
            <div class="card">
                <h2>{{ $event['title'] }}</h2>
                <div class="time">{{ $time($event['start']) }} – {{ $time($event['end']) }}</div>
                @if ($event['description'])
                    <div class="desc">{{ $event['description'] }}</div>
                @endif
            </div>
        @empty
            <div class="empty">
                @if ($error)
                    {{ __('Failed to load data:') }} <strong>{{ $error }}</strong><br>
                    {{ __('Check') }} <code>GOOGLE_CALENDAR_ID</code> / <code>GOOGLE_API_KEY</code> {{ __('in .env, make sure the calendar is public, then run') }} <code>php artisan config:clear</code>.
                @else
                    {{ __('No upcoming events. Check the API key, make sure the calendar is public, and run') }} <code>php artisan config:clear</code> {{ __('if you just changed .env.') }}
                @endif
            </div>
        @endforelse
    </div>

    <script>
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
