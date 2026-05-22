<?php

namespace App\Listeners;

use App\Events\ProductLowStock;
use App\Models\StockNotification;

class CreateStockNotification
{
    public function handle(ProductLowStock $event): void
    {
        $product = $event->product;

        if (! $product->is_low_stock) {
            return;
        }

        StockNotification::create([
            'product_id' => $product->id,
            'message' => "{$product->name} is low on stock ({$product->stock_quantity} left, alert at {$product->low_stock_threshold})",
        ]);
    }
}
