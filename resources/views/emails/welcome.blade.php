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
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #C62828 0%, #FF6F00 100%);
            color: white !important;
            text-decoration: none;
            border-radius: 25px;
            margin-top: 20px;
            font-weight: 600;
        }
        .footer {
            background: #f5f5f5;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 10px 10px;
            color: #666;
            font-size: 14px;
        }
        .highlight {
            background: #FFF3E0;
            padding: 15px;
            border-left: 4px solid #FF6F00;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Welcome to Mama Liu!</h1>
    </div>
    
    <div class="content">
        <p>Dear {{ $user->name }},</p>
        
        <p>You've been accepted by Mama Liu!</p>
        
        <div class="highlight">
            <strong>📧 Your login email:</strong> {{ $user->username }}<br>
            @if($user->group)
                <strong>📍 Delivery group:</strong> {{ $user->group->name }}
            @endif
        </div>
        
        <p>You can now:</p>
        <ul>
            <li>Browse our weekly menu</li>
            <li>Place orders for delivery</li>
            <li>View your order history</li>
            <li>Place pre-order</li>
        </ul>
        
        <p>Ready to get started? Log in to explore this week's delicious offerings!</p>
        
        <center>
            <a href="{{ config('app.url') }}/login" class="button">Login to Order</a>
        </center>
        
        <p style="margin-top: 30px; font-size: 14px; color: #666;">
            If you have any questions, feel free to contact me at {{ config('mail.owner') }}
        </p>
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