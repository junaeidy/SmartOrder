<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan</title>
    <style>
        /* Reset styles */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: #ffffff;
        }
        
        .header {
            text-align: center;
            padding: 20px 0;
            background-color: #ff6b00;
            color: white;
            border-radius: 6px 6px 0 0;
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        
        .header p {
            margin: 5px 0 0;
            opacity: 0.8;
        }
        
        .content {
            padding: 20px;
            background: white;
            border-left: 1px solid #eaeaea;
            border-right: 1px solid #eaeaea;
        }
        
        .customer-details {
            margin-bottom: 25px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }
        
        .customer-details h2 {
            margin-top: 0;
            font-size: 18px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        
        .customer-detail {
            margin-bottom: 5px;
        }
        
        .label {
            font-weight: 600;
            min-width: 120px;
            display: inline-block;
        }
        
        .order-details {
            margin-bottom: 25px;
        }
        
        .order-details h2 {
            font-size: 18px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 10px;
            font-weight: 600;
        }
        
        table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        .total-row {
            background-color: #f8f9fa;
            font-weight: 700;
        }
        .summary-label { text-align: right; }
        .summary-value { text-align: right; white-space: nowrap; }
        .muted { color: #666; font-weight: 500; }
        
        .queue-number {
            text-align: center;
            margin: 25px 0;
            padding: 15px;
            background-color: #ff6b00;
            color: white;
            border-radius: 6px;
        }
        
        .queue-number h2 {
            margin: 0;
            font-size: 20px;
        }
        
        .queue-number .number {
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0;
            letter-spacing: 5px;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            background-color: #333;
            color: white;
            font-size: 14px;
            border-radius: 0 0 6px 6px;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        .thanks {
            text-align: center;
            padding: 20px 0;
        }
        
        .thanks h2 {
            color: #333;
        }
        
        @media screen and (max-width: 600px) {
            .container {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SmartOrder</h1>
            <p>Konfirmasi Pesanan</p>
        </div>
        
        <div class="content">
            <div class="customer-details">
                <h2>Detail Pelanggan</h2>
                <div class="customer-detail">
                    <span class="label">Nama:</span>
                    <span>{{ $transaction->customer_name }}</span>
                </div>
                <div class="customer-detail">
                    <span class="label">Email:</span>
                    <span>{{ $transaction->customer_email }}</span>
                </div>
                <div class="customer-detail">
                    <span class="label">Telepon:</span>
                    <span>{{ $transaction->customer_phone }}</span>
                </div>
                <div class="customer-detail">
                    <span class="label">Tanggal Pesan:</span>
                    <span>{{ $transaction->created_at->format('j F Y, H:i') }}</span>
                </div>
                <div class="customer-detail">
                    <span class="label">Metode Pembayaran:</span>
                    <span>{{ $transaction->payment_method == 'cash' ? 'Tunai' : 'Online' }}</span>
                </div>
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
                        @php
                            $items = collect($transaction->items ?? []);
                            $subtotal = $items->sum(function($it){ return (float)($it['subtotal'] ?? 0); });
                            $discountAmount = (float)($transaction->discount_amount ?? 0);
                            $taxAmount = (float)($transaction->tax_amount ?? 0);
                            $preTaxAmount = max($subtotal - $discountAmount, 0);
                            $taxPercent = $preTaxAmount > 0 ? round(($taxAmount / $preTaxAmount) * 100, 2) : null;
                        @endphp
                        @foreach($transaction->items as $item)
                        <tr>
                            <td>{{ $item['nama'] }}</td>
                            <td>{{ $item['quantity'] }}</td>
                            <td>Rp {{ number_format($item['harga'], 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</td>
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
            
            <div class="queue-number">
                <h2>Nomor Antrian Anda</h2>
                <div class="number">{{ $transaction->queue_number }}</div>
                <p>Harap tunjukkan nomor ini saat mengambil pesanan Anda</p>
            </div>
            
            <div class="thanks">
                <h2>Terima Kasih atas Pesanan Anda!</h2>
                <p>Pesanan Anda telah diterima dan akan segera diproses oleh staf kami. Kami sangat menghargai kepercayaan Anda dan berharap dapat melayani Anda lagi.</p>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} SmartOrder. All rights reserved.</p>
            <p>Jika Anda memiliki pertanyaan, silakan hubungi layanan pelanggan kami.</p>
        </div>
    </div>
</body>
</html>