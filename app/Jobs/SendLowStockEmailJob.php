<?php

namespace App\Jobs;

use App\Mail\LowStockAlertMail;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendLowStockEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Product $product)
    {
    }

    public function handle(): void
    {
        $email = env('LOW_STOCK_NOTIFY_EMAIL');

        if (! $email) {
            return;
        }

        Mail::to($email)->send(new LowStockAlertMail($this->product));
    }
}
