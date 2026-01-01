<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVuSansCondensed', Arial, Helvetica, sans-serif; font-size: 12px; margin: 20px; font-weight: 800; }
        h1 { text-align: center; font-size: 24px; margin: 10px 0; }
        .week { text-align: center; color: #666; margin-bottom: 20px; font-size: 14px; }
        .card-border { border: 2px solid #000; padding: 0; }
        .card-inner { padding: 12px; background: #fff; }
        .name { font-size: 16px; font-weight: bold; padding-bottom: 5px; }
        .meta { font-size: 11px; color: #666; padding-bottom: 10px; }
        .notes { background: #f5f5f5; padding: 8px; font-size: 11px; }
        .total-row { font-weight: bold; font-size: 14px; padding-top: 8px; }
    </style>
</head>
<body>
    <h1>Orders</h1>
    <div class="week">Week {{ $week }}, {{ $year }}</div>
    
    <table width="100%" cellpadding="0" cellspacing="15">
        @foreach($groupedOrders->chunk(2) as $chunk)
            <tr>
                @foreach($chunk as $order)
                    <td width="48%" valign="top">
                        <table width="100%" class="card-border" cellpadding="0" cellspacing="0">
                            <tr>
                                <td class="card-inner">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td class="name">{{ $order['user']->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="meta">{{ $order['group']->name ?? 'No Group' }} | {{ \Carbon\Carbon::parse($order['order_date'])->format('M d, Y') }}</td>
                                        </tr>
                                        
                                        @php $notes = collect($order['orders'])->pluck('notes')->filter()->unique(); @endphp
                                        @if($notes->count() > 0)
                                            <tr>
                                                <td class="notes"><strong>Notes:</strong> @foreach($notes as $note){{ $note }} @endforeach</td>
                                            </tr>
                                        @endif
                                        
                                        @foreach($order['orders'] as $item)
                                            <tr>
                                                <td style="padding: 3px 0; font-size: 12px;">
                                                    <table width="100%" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td width="70%">{{ $item->quantity }}× {{ $item->weekmenu->menu->label }}</td>
                                                            <td width="30%" align="right">€{{ number_format(($item->special_price ?? $item->weekmenu->menu->price) * $item->quantity, 2) }}</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        @endforeach
                                        
                                        <tr>
                                            <td class="total-row">
                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td width="70%">Total</td>
                                                        <td width="30%" align="right">€{{ number_format($order['total'], 2) }}</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                @endforeach
                @if($chunk->count() == 1)<td width="48%"></td>@endif
            </tr>
        @endforeach
    </table>
</body>
</html>