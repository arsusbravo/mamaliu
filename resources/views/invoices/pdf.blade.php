<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            margin: 0;
            padding: 20px;
        }
        .header {
            margin-bottom: 30px;
        }
        .invoice-title {
            font-size: 24pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .invoice-info {
            margin-bottom: 20px;
        }
        .invoice-info table {
            width: 100%;
        }
        .invoice-info td {
            padding: 5px 0;
        }
        .client-info {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f5f5f5;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #333;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
        }
        .items-table tr:last-child td {
            border-bottom: 2px solid #333;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            margin-left: auto;
            width: 300px;
            margin-top: 20px;
        }
        .totals table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals td {
            padding: 8px;
        }
        .totals .total-row {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 12pt;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8pt;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="invoice-title">INVOICE</div>
        <div class="invoice-info">
            <table>
                <tr>
                    <td style="width: 50%;"><strong>Invoice Number:</strong> {{ $invoice->invoice_number }}</td>
                    <td style="width: 50%;"><strong>Date:</strong> {{ $invoice->invoice_date->format('d-m-Y') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="client-info">
        <strong>Bill To:</strong><br>
        {{ $invoice->user->name }}<br>
        @if($invoice->user->email)
            {{ $invoice->user->email }}<br>
        @endif
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="width: 80px;" class="text-right">Quantity</th>
                <th style="width: 100px;" class="text-right">Unit Price</th>
                <th style="width: 100px;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>{{ $item['menu_label'] }}</td>
                <td class="text-right">{{ $item['quantity'] }}</td>
                <td class="text-right">€{{ number_format($item['price'], 2) }}</td>
                <td class="text-right">€{{ number_format($item['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Subtotal (Excl. VAT):</td>
                <td class="text-right">€{{ number_format($totalExclTax, 2) }}</td>
            </tr>
            <tr>
                <td>VAT (9%):</td>
                <td class="text-right">€{{ number_format($vat, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Total (Incl. VAT):</td>
                <td class="text-right">€{{ number_format($totalInclTax, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Thank you for your business!
    </div>
</body>
</html>