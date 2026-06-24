<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #000; margin: 40px; }
        .store-name { font-size: 22px; font-weight: bold; }
        .muted { color: #555; }
        .title { font-size: 14px; font-weight: bold; margin-top: 16px; }
        .header-row { width: 100%; margin-bottom: 20px; }
        .header-left { float: left; width: 55%; }
        .header-right { float: right; width: 40%; text-align: left; }
        .clear { clear: both; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.items th { border-bottom: 1px solid #ccc; text-align: left; padding: 6px 0; }
        table.items td { padding: 6px 0; vertical-align: top; }
        .qty, .total { text-align: right; }
        .totals { width: 100%; margin-top: 16px; }
        .totals td { padding: 4px 0; }
        .totals .label { text-align: right; padding-right: 12px; }
        .totals .value { text-align: right; width: 90px; }
        .grand-total { font-weight: bold; font-size: 11px; }
        .ship-to { margin-top: 20px; }
        .footer { position: fixed; bottom: 30px; width: 100%; text-align: center; color: #888; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header-row">
        <div class="header-left">
            <div class="store-name">{{ $storeName }}</div>
            @if($storeAddress)<div class="muted">{{ $storeAddress }}</div>@endif
            @if($storeEmail)<div class="muted">{{ $storeEmail }}</div>@endif
            @if($storePhone)<div class="muted">{{ $storePhone }}</div>@endif
            <div class="title">INVOICE</div>
            <div>Order: {{ $orderNumber }}</div>
            <div>Date: {{ $createdAt }}</div>
        </div>
        <div class="header-right">
            <div><strong>Bill to</strong></div>
            <div>{{ $guestEmail }}</div>
            <div style="margin-top:12px;"><strong>Payment</strong></div>
            <div>{{ $paymentMethodLabel }}</div>
            <div>{{ $paymentStatus }}</div>
        </div>
        <div class="clear"></div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="qty">Qty</th>
                <th class="total">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orderItems as $item)
            <tr>
                <td>
                    {{ $item['productName'] }}
                    @if(!empty($item['variantLabel'])) ({{ $item['variantLabel'] }}) @endif
                </td>
                <td class="qty">{{ $item['quantity'] }}</td>
                <td class="total">{{ $item['lineTotal'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td></td><td class="label">Subtotal</td><td class="value">{{ $subtotal }}</td></tr>
        @if($discount !== '₱0.00')
        <tr><td></td><td class="label">Discount</td><td class="value">-{{ $discount }}</td></tr>
        @endif
        @if($tax !== '₱0.00')
        <tr><td></td><td class="label">Tax</td><td class="value">{{ $tax }}</td></tr>
        @endif
        <tr><td></td><td class="label">Shipping</td><td class="value">{{ $shipping }}</td></tr>
        @if($installation !== '₱0.00')
        <tr><td></td><td class="label">Installation</td><td class="value">{{ $installation }}</td></tr>
        @endif
        <tr class="grand-total"><td></td><td class="label">Total</td><td class="value">{{ $grandTotal }}</td></tr>
    </table>

    @if(!empty($shippingAddressLines))
    <div class="ship-to">
        <strong>Ship to</strong>
        @foreach($shippingAddressLines as $line)
        <div>{{ $line }}</div>
        @endforeach
    </div>
    @endif

    <div class="footer">Thank you for your purchase.</div>
</body>
</html>
