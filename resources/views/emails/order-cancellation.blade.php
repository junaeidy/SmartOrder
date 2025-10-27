<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembatalan Pesanan</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
        .header { text-align: center; padding: 20px 0; background-color: #dc2626; color: white; border-radius: 6px 6px 0 0; }
        .header h1 { margin: 0; font-size: 26px; font-weight: 700; }
        .header p { margin: 5px 0 0; opacity: 0.9; }

        .content { padding: 20px; background: white; border-left: 1px solid #eaeaea; border-right: 1px solid #eaeaea; }

        .alert { background: #fee2e2; color: #7f1d1d; border-left: 4px solid #dc2626; padding: 12px 15px; border-radius: 4px; margin: 10px 0 20px; }

        .customer-details { margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 6px; }
        .customer-details h2 { margin-top: 0; font-size: 18px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; }
        .customer-detail { margin-bottom: 5px; }
        .label { font-weight: 600; min-width: 120px; display: inline-block; }

        .order-details { margin-bottom: 20px; }
        .order-details h2 { font-size: 18px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f8f9fa; text-align: left; padding: 10px; font-weight: 600; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        tr:last-child td { border-bottom: none; }
        .summary-label { text-align: right; }
        .summary-value { text-align: right; white-space: nowrap; }
        .total-row { background-color: #fef2f2; font-weight: 700; color: #7f1d1d; }
        .muted { color: #666; font-weight: 500; }

        .footer { text-align: center; padding: 20px; background-color: #333; color: white; font-size: 14px; border-radius: 0 0 6px 6px; }
        .footer p { margin: 5px 0; }

        @media screen and (max-width: 600px) { .container { width: 100%; } }
    </style>
    @php
        $items = collect($transaction->items ?? []);
        $subtotal = $items->sum(function($it){ return (float)($it['subtotal'] ?? (($it['harga'] ?? ($it['price'] ?? 0)) * ($it['quantity'] ?? 0))); });
        $discountAmount = (float)($transaction->discount_amount ?? 0);
        $taxAmount = (float)($transaction->tax_amount ?? 0);
        $preTaxAmount = max($subtotal - $discountAmount, 0);
        $taxPercent = $preTaxAmount > 0 ? round(($taxAmount / $preTaxAmount) * 100, 2) : null;
    @endphp
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Pembatalan Pesanan</h1>
            <p>Kode: {{ $transaction->kode_transaksi }}</p>
        </div>

        <div class="content">
            <div class="alert">
                Pesanan Anda telah dibatalkan karena pembayaran tidak diselesaikan dalam waktu 15 menit.
            </div>

            <div class="customer-details">
                <h2>Detail Pelanggan</h2>
                <div class="customer-detail"><span class="label">Nama:</span> <span>{{ $transaction->customer_name }}</span></div>
                <div class="customer-detail"><span class="label">Email:</span> <span>{{ $transaction->customer_email }}</span></div>
                <div class="customer-detail"><span class="label">Telepon:</span> <span>{{ $transaction->customer_phone }}</span></div>
                <div class="customer-detail"><span class="label">Tanggal Pesan:</span> <span>{{ optional($transaction->created_at)->format('F j, Y, g:i a') }}</span></div>
                <div class="customer-detail"><span class="label">Metode Bayar:</span> <span>{{ ucfirst($transaction->payment_method) }}</span></div>
            </div>

            <div class="order-details">
                <h2>Detail Pesanan</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Jumlah</th>
                            <th>Harga</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transaction->items as $item)
                        @php
                            $nama = $item['nama'] ?? ($item['name'] ?? 'Produk');
                            $qty = (int)($item['quantity'] ?? 0);
                            $harga = (float)($item['harga'] ?? ($item['price'] ?? 0));
                            $rowTotal = (float)($item['subtotal'] ?? ($harga * $qty));
                        @endphp
                        <tr>
                            <td>{{ $nama }}</td>
                            <td>{{ $qty }}</td>
                            <td>Rp {{ number_format($harga, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($rowTotal, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="summary-label muted">Total Item</td>
                            <td class="summary-value">{{ $transaction->total_items }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="summary-label">Subtotal</td>
                            <td class="summary-value">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                        </tr>
                        @if($discountAmount > 0)
                        <tr>
                            <td colspan="3" class="summary-label">Diskon</td>
                            <td class="summary-value">- Rp {{ number_format($discountAmount, 0, ',', '.') }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td colspan="3" class="summary-label">Pajak{{ !is_null($taxPercent) ? ' ('.rtrim(rtrim(number_format($taxPercent, 2, ',', '.'), '0'), ',').'% )' : '' }}</td>
                            <td class="summary-value">Rp {{ number_format($taxAmount, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="3" class="summary-label">Total</td>
                            <td class="summary-value">Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="alert" style="background:#fff7ed;color:#7a2e0e;border-left-color:#fb923c;">
                Stok produk pada pesanan ini telah dikembalikan. Anda dapat melakukan pemesanan ulang kapan saja.
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} SmartOrder. All rights reserved.</p>
            <p>Jika Anda memiliki pertanyaan, silakan hubungi layanan pelanggan kami.</p>
        </div>
    </div>
</body>
</html>