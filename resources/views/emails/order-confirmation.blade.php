<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #C62828 0%, #FF6F00 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e0e0e0;
        }
        .order-info {
            background: #FFF3E0;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #FF6F00;
        }
        .order-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .item-details {
            flex: 1;
        }
        .item-name {
            font-weight: 600;
            color: #C62828;
            margin-bottom: 5px;
        }
        .item-notes {
            font-size: 14px;
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        .item-price {
            text-align: right;
            font-weight: 600;
        }
        .total {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: right;
        }
        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #C62828;
        }
        .footer {
            background: #f5f5f5;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 10px 10px;
            color: #666;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #C62828 0%, #FF6F00 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            margin-top: 20px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Thank You for Your Order!</h1>
    </div>
    
    <div class="content">
        <p>Dear {{ $user->name }},</p>
        
        <p>Thank you! Your order has been successfully placed!</p>
        
        <div class="order-info">
            <strong>📅 Delivery Week:</strong> Week {{ $week }}, {{ $year }}<br>
            <strong>📦 Total Items:</strong> {{ count($orders) }}
        </div>
        
        <h3 style="color: #C62828; border-bottom: 2px solid #FF6F00; padding-bottom: 10px;">Your Complete Order</h3>
        
        @foreach($orders as $order)
            <div class="order-item">
                <div class="item-details">
                    <div class="item-name">
                        {{ $order->quantity }}× {{ $order->weekmenu->menu->label }}
                    </div>
                    <div style="font-size: 14px; color: #666;">
                        €{{ number_format($order->special_price ?? $order->weekmenu->menu->price, 2) }} each
                    </div>
                    @if($order->notes)
                        <div class="item-notes">
                            📝 {{ $order->notes }}
                        </div>
                    @endif
                </div>
                <div class="item-price">
                    €{{ number_format(($order->special_price ?? $order->weekmenu->menu->price) * $order->quantity, 2) }}
                </div>
            </div>
        @endforeach
        
        <div class="total">
            <div style="font-size: 16px; color: #666; margin-bottom: 10px;">Total Amount</div>
            <div class="total-amount">€{{ number_format($total, 2) }}</div>
        </div>
        
        <p style="margin-top: 30px;">The order will be delivered to this region: <strong style="color: #C62828;">{{ $groupName }}</strong></p>
        
        <center>
            <a href="{{ config('app.url') }}/orders" class="button">View My Orders</a>
        </center>
    </div>
    
    <div class="footer">
        <p style="margin: 0;">
            <strong>Mama Liu</strong><br>
            Authentic Taiwanese Cuisine<br>
            {{ config('mail.owner') }}
        </p>
    </div>
</body>
</html>