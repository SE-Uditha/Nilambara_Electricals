<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $data['title'] }}</title>
    <style>
        @font-face { font-family: NotoSinhala; src: url('{{ public_path('fonts/NotoSansSinhala-Regular.ttf') }}') format('truetype'); }
        @page { margin: 12mm 8mm 16mm; }
        body { font-family: NotoSinhala, DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p { color: #475569; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; text-align: left; }
        th { background: #f1f5f9; }
    </style>
</head>
<body>
    <h1>{{ $data['title'] }}</h1>
    <p>{{ config('business.name') }} · {{ now(config('business.timezone', 'Asia/Colombo'))->format('Y-m-d H:i') }}</p>
    @foreach ($data['warnings'] as $warning)<p>{{ $warning }}</p>@endforeach
    <p>@foreach (($filters ?? []) as $name => $value){{ $name }}: {{ $value }} · @endforeach</p>
    <p>@foreach ($data['summary'] as $label => $value){{ __('reports.'.$label) }}: {{ $value }} · @endforeach</p>
    <table>
        <thead><tr>@foreach ($data['columns'] as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead>
        <tbody>@foreach ($data['query']->limit(1000)->get() as $row)<tr>@foreach ($data['columns'] as $column)<td>{{ data_get($row, $column['field'], '') }}</td>@endforeach</tr>@endforeach</tbody>
    </table>
</body>
</html>
