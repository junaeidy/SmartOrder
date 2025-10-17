<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; margin-bottom: 0; }
        .muted { color: #666; font-size: 12px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; }
        th { background: #f3f4f6; text-align: left; }
        tfoot td { font-weight: bold; }
    </style>
    </head>
<body>
    <h1>{{ $title }}</h1>
    <div class="muted">Dibuat: {{ $generatedAt }}</div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Kode</th>
                <th>Customer</th>
                <th>Metode</th>
                <th>Status Bayar</th>
                <th>Status</th>
                <th>Total</th>
                <th>Items</th>
            </tr>
        </thead>
        <tbody>
            @php $paidStatuses = ['paid','settlement','capture']; @endphp
            @foreach ($rows as $t)
                <tr>
                    <td>{{ optional($t->created_at)->toDateTimeString() }}</td>
                    <td>{{ $t->kode_transaksi }}</td>
                    <td>{{ $t->customer_name }}</td>
                    <td>{{ $t->payment_method === 'midtrans' ? 'online' : $t->payment_method }}</td>
                    <td>{{ $t->payment_status }}</td>
                    <td>{{ $t->status }}</td>
                    <td>
                        @if(in_array(strtolower((string)$t->payment_status), $paidStatuses))
                            {{ number_format($t->total_amount, 0, ',', '.') }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $t->total_items }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @php
                $sumTotal = $rows->filter(function($r) use ($paidStatuses) { return in_array(strtolower((string)$r->payment_status), $paidStatuses); })->sum('total_amount');
                $sumItems = $rows->sum('total_items');
            @endphp
            <tr>
                <td colspan="6">TOTAL</td>
                <td>{{ number_format($sumTotal, 0, ',', '.') }}</td>
                <td>{{ $sumItems }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
