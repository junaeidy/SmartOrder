<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
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
            <p>Order Confirmation</p>
        </div>
        
        <div class="content">
            <div class="customer-details">
                <h2>Customer Details</h2>
                <div class="customer-detail">
                    <span class="label">Name:</span>
                    <span>{{ $transaction->customer_name }}</span>
                </div>
                <div class="customer-detail">
                    <span class="label">Email:</span>
                    <span>{{ $transaction->customer_email }}</span>
                </div>
                <div class="customer-detail">
                    <span class="label">Phone:</span>
                    <span>{{ $transaction->customer_phone }}</span>
                </div>
                <div class="customer-detail">
                    <span class="label">Order Date:</span>
                    <span>{{ $transaction->created_at->format('F j, Y, g:i a') }}</span>
                </div>
                <div class="customer-detail">
                    <span class="label">Payment Method:</span>
                    <span>{{ ucfirst($transaction->payment_method) }}</span>
                </div>
            </div>
            
            <div class="order-details">
                <h2>Order Details</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transaction->items as $item)
                        <tr>
                            <td>{{ $item['nama'] }}</td>
                            <td>{{ $item['quantity'] }}</td>
                            <td>Rp {{ number_format($item['harga'], 0, ',', '.') }}</td>
                            <td>Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        <tr class="total-row">
                            <td colspan="2">Total Items: {{ $transaction->total_items }}</td>
                            <td colspan="2" align="right">Total: Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="queue-number">
                <h2>Your Queue Number</h2>
                <div class="number">{{ $transaction->queue_number }}</div>
                <p>Please present this number when collecting your order</p>
            </div>
            
            <div class="thanks">
                <h2>Thank You for Your Order!</h2>
                <p>Your order has been received and will be processed by our staff shortly. We appreciate your business and hope to serve you again soon.</p>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} SmartOrder. All rights reserved.</p>
            <p>If you have any questions, please contact our customer service.</p>
        </div>
    </div>
</body>
</html>