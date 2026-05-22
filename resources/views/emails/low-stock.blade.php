<p>Hello,</p>

<p><strong>{{ $product->name }}</strong> is running low on stock.</p>

<p>
    Current stock: {{ $product->stock_quantity }}<br>
    Alert level: {{ $product->low_stock_threshold }}
</p>

<p>Please restock soon.</p>
