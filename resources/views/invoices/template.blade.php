<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 30px;
        }
        .header {
            border-bottom: 2px solid #6366f1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            color: #1e1b4b;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .badge {
            background-color: #10b981;
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        .grid {
            width: 100%;
            margin-bottom: 30px;
        }
        .grid td {
            vertical-align: top;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table th {
            background-color: #f3f4f6;
            text-align: left;
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid #e5e7eb;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        .total-box {
            float: right;
            width: 40%;
            margin-top: 10px;
        }
        .total-box table {
            width: 100%;
        }
        .total-box td {
            padding: 6px 0;
        }
        .grand-total {
            font-size: 18px;
            font-weight: bold;
            color: #1e1b4b;
            border-top: 2px solid #e5e7eb;
            padding-top: 8px;
        }
        .footer {
            clear: both;
            margin-top: 60px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="title">PAYMENT RESILIENT ENGINE</div>
                    <div style="color: #6b7280; font-size: 13px;">Fintech & E-Commerce API</div>
                </td>
                <td style="text-align: right;">
                    <span class="badge">{{ strtoupper($status) }}</span>
                    <div style="font-size: 14px; margin-top: 8px; font-weight: bold;">{{ $invoiceNumber }}</div>
                    <div style="color: #6b7280; font-size: 13px;">Date: {{ $date }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="grid">
        <tr>
            <td style="width: 50%;">
                <strong style="color: #6b7280; font-size: 12px; text-transform: uppercase;">Billed To:</strong>
                <div style="font-size: 15px; font-weight: bold; margin-top: 4px;">{{ $customerName }}</div>
                <div style="color: #4b5563;">{{ $customerEmail }}</div>
            </td>
            <td style="width: 50%; text-align: right;">
                <strong style="color: #6b7280; font-size: 12px; text-transform: uppercase;">Payment Details:</strong>
                <div style="color: #4b5563; margin-top: 4px;">Gateway: {{ ucfirst($gateway) }}</div>
                <div style="color: #4b5563;">Tx ID: {{ $transactionId ?? 'N/A' }}</div>
            </td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: center; width: 80px;">Qty</th>
                <th style="text-align: right; width: 120px;">Price</th>
                <th style="text-align: right; width: 120px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $description }}</td>
                <td style="text-align: center;">1</td>
                <td style="text-align: right;">{{ $formattedSubtotal }}</td>
                <td style="text-align: right;">{{ $formattedSubtotal }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total-box">
        <table>
            <tr>
                <td>Subtotal:</td>
                <td style="text-align: right;">{{ $formattedSubtotal }}</td>
            </tr>
            <tr>
                <td>Tax (0%):</td>
                <td style="text-align: right;">{{ $formattedTax }}</td>
            </tr>
            <tr class="grand-total">
                <td>Total Paid:</td>
                <td style="text-align: right;">{{ $formattedTotal }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Thank you for your business! This is an electronically generated receipt for your payment.
    </div>
</body>
</html>
