<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Support\SuperAdmin;

class ProductPolicy
{
    public function update(User $user, Product $product): bool
    {
        return SuperAdmin::check($user) || $user->id === $product->user_id;
    }

    public function delete(User $user, Product $product): bool
    {
        return SuperAdmin::check($user) || $user->id === $product->user_id;
    }
}
